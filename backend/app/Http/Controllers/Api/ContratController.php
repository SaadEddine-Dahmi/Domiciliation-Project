<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContratController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();

            if (!$entreprise) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $contrats = Contrat::where('entreprise_id', $entreprise->id)
                ->where('statut', 'active')
                ->with([
                    'entreprise.representant',
                    'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
                ])
                ->latest()
                ->get();

            return response()->json(['success' => true, 'data' => $contrats]);
        }

        $contrats = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $contrats]);
    }

    public function show(string $id)
    {
        $user = auth()->user();

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    /**
     * POST /api/contrats
     *
     * NOTE on 'articles' validation: kept intentionally loose ('array' only,
     * no per-item shape rule). syncArticles() below accepts BOTH:
     *   - object form: [{id: "3", ordre: 1}, ...]
     *   - flat form:   ["3", "14"]
     * A strict 'articles.*.id' => required rule rejects the flat form outright
     * (each element isn't an array with an 'id' key), so we validate shape
     * loosely here and let syncArticles() normalise both formats.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'entreprise_id'  => ['required', 'integer', 'exists:entreprises,id'],
            'titre_contrat'  => ['nullable', 'string', 'max:255'],
            'date_debut'     => ['required', 'date'],
            'date_fin'       => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois'     => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'   => ['nullable', 'numeric', 'min:0'],
            'prix_total'     => ['nullable', 'numeric', 'min:0'],
            'instruction_no' => ['nullable', 'string', 'max:20'],
            'ville_signature'=> ['nullable', 'string', 'max:100'],
            'date_signature' => ['nullable', 'date'],
            'caution'        => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'  => ['nullable', 'string', 'max:100'],
            'statut'         => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'       => ['nullable', 'array'],
        ]);

        // Ownership guard: entreprise must belong to the authenticated tenant.
        $entreprise = Entreprise::where('domiciliataire_id', $user->id)
            ->find($data['entreprise_id']);

        if (!$entreprise) {
            return response()->json([
                'success' => false,
                'message' => 'Entreprise introuvable.',
            ], 404);
        }

        $contrat = Contrat::create([
            'domiciliataire_id' => $user->id,
            'entreprise_id'     => $data['entreprise_id'],
            'titre_contrat'     => ($data['titre_contrat'] ?? '') ?: 'Contrat de Domiciliation',
            'date_debut'        => $data['date_debut'],
            'date_fin'          => $data['date_fin']          ?? null,
            'duree_mois'        => $data['duree_mois']        ?? null,
            'prix_mensuel'      => $data['prix_mensuel']      ?? null,
            'prix_total'        => $data['prix_total']        ?? null,
            'instruction_no'    => $data['instruction_no']    ?? null,
            'ville_signature'   => $data['ville_signature']   ?? null,
            'date_signature'    => $data['date_signature']    ?? null,
            'caution'           => $data['caution']           ?? null,
            'mode_paiement'     => $data['mode_paiement']     ?? null,
            'statut'            => 'draft',
        ]);

        $this->syncArticles($contrat, $request->input('articles', []));

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat], 201);
    }

    public function update(Request $request, string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        $data = $request->validate([
            'entreprise_id'  => ['sometimes', 'integer', 'exists:entreprises,id'],
            'titre_contrat'  => ['nullable', 'string', 'max:255'],
            'date_debut'     => ['sometimes', 'date'],
            'date_fin'       => ['nullable', 'date'],
            'duree_mois'     => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'   => ['nullable', 'numeric', 'min:0'],
            'prix_total'     => ['nullable', 'numeric', 'min:0'],
            'instruction_no' => ['nullable', 'string', 'max:20'],
            'ville_signature'=> ['nullable', 'string', 'max:100'],
            'date_signature' => ['nullable', 'date'],
            'caution'        => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'  => ['nullable', 'string', 'max:100'],
            'statut'         => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'       => ['nullable', 'array'],
        ]);

        if (isset($data['entreprise_id'])) {
            $owned = Entreprise::where('domiciliataire_id', $user->id)
                ->find($data['entreprise_id']);

            if (!$owned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Entreprise introuvable.',
                ], 404);
            }
        }

        $contrat->update(array_filter([
            'entreprise_id'  => $data['entreprise_id']  ?? null,
            'titre_contrat'  => isset($data['titre_contrat'])
                                    ? ($data['titre_contrat'] ?: 'Contrat de Domiciliation')
                                    : null,
            'date_debut'     => $data['date_debut']     ?? null,
            'date_fin'       => $data['date_fin']       ?? null,
            'duree_mois'     => $data['duree_mois']     ?? null,
            'prix_mensuel'   => $data['prix_mensuel']   ?? null,
            'prix_total'     => $data['prix_total']     ?? null,
            'instruction_no' => $data['instruction_no'] ?? null,
            'ville_signature'=> $data['ville_signature']?? null,
            'date_signature' => $data['date_signature'] ?? null,
            'caution'        => $data['caution']        ?? null,
            'mode_paiement'  => $data['mode_paiement']  ?? null,
            'statut'         => $data['statut']         ?? null,
        ], fn($v) => $v !== null));

        if ($request->has('articles')) {
            $this->syncArticles($contrat, $request->input('articles', []));
        }

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    public function activate(string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        if ($contrat->statut !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat en brouillon peut être activé.',
            ], 422);
        }

        $contrat->activate();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function terminate(string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        if ($contrat->statut !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat actif peut être résilié.',
            ], 422);
        }

        $contrat->terminate();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function generatePdf(string $id)
    {
        $user = auth()->user();

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'domiciliataire',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->findOrFail($id);

        $this->prepareEntrepriseForPdf($contrat);
        $tokenMap = $this->buildTokenMap($contrat);

        $resolvedArticles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $clone       = clone $article;
            $clone->body = $this->resolveTokens($article->body ?? '', $tokenMap);
            return $clone;
        });

        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat'  => $contrat,
            'articles' => $resolvedArticles,
        ])->setPaper('a4', 'portrait');

        $filename = 'contrats/contrat_' . $contrat->id . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());
        $contrat->update(['pdf_path' => $filename]);

        return response()->json([
            'success' => true,
            'data'    => [
                'url'      => Storage::disk('public')->url($filename),
                'pdf_path' => $filename,
            ],
        ]);
    }

    /**
     * GET /api/contrats/{id}/pdf/stream
     *
     * MUST remain outside auth:sanctum — registered separately in
     * routes/api.php, before the authenticated group. A browser <iframe
     * src="…"> issues a plain GET with no custom headers, so this route
     * cannot require Authorization: Bearer.
     */
    public function streamPdf(string $id)
    {
        $contrat = Contrat::with([
            'entreprise.representant',
            'domiciliataire',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ])->findOrFail($id);

        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrat_' . $contrat->id . '.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ];

        if ($contrat->pdf_path && Storage::disk('public')->exists($contrat->pdf_path)) {
            return response(Storage::disk('public')->get($contrat->pdf_path), 200, $headers);
        }

        $this->prepareEntrepriseForPdf($contrat);
        $tokenMap         = $this->buildTokenMap($contrat);
        $resolvedArticles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $clone       = clone $article;
            $clone->body = $this->resolveTokens($article->body ?? '', $tokenMap);
            return $clone;
        });

        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat'  => $contrat,
            'articles' => $resolvedArticles,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, $headers);
    }

    private function prepareEntrepriseForPdf(Contrat $contrat): void
    {
        $entreprise   = $contrat->entreprise;
        $representant = $entreprise?->representant;

        if (!$entreprise) return;

        $entreprise->setAttribute('nom_societe', $entreprise->raison_sociale ?? '');
        $entreprise->setAttribute('gerant_nom', $representant
            ? trim(($representant->nom ?? '') . ' ' . ($representant->prenom ?? ''))
            : '');
        $entreprise->setAttribute('gerant_cin', $representant?->cin ?? '');
        $entreprise->setAttribute('tel', $representant?->telephone ?? '');
        $entreprise->setAttribute('email', $representant?->email ?? '');
        $entreprise->setAttribute('adresse', $representant?->adresse ?? $entreprise->adresse ?? '');
    }

    private function buildTokenMap(Contrat $contrat): array
    {
        $entreprise     = $contrat->entreprise;
        $representant   = $entreprise?->representant;
        $domiciliataire = $contrat->domiciliataire;

        $fmt  = fn($v) => $v !== null ? number_format((float) $v, 2, ',', ' ') . ' DH' : '';
        $date = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';

        return [
            'domiciliataire_nom'          => $domiciliataire?->nom_societe          ?? '',
            'domiciliataire_rc'           => $domiciliataire?->rc                   ?? '',
            'domiciliataire_if'           => $domiciliataire?->if_fiscal            ?? '',
            'domiciliataire_tp'           => $domiciliataire?->tp                   ?? '',
            'domiciliataire_adresse'      => $domiciliataire?->adresse              ?? '',
            'domiciliataire_representant' => $domiciliataire?->representant_legal   ?? '',
            'raison_sociale'              => $entreprise?->raison_sociale           ?? '',
            'societe'                     => $entreprise?->raison_sociale           ?? '',
            'forme_juridique'             => $entreprise?->forme_juridique          ?? '',
            'adresse_domiciliation'       => $entreprise?->adresse                  ?? '',
            'ville_client'                => $entreprise?->ville                    ?? '',
            'gerant_nom'                  => trim(($representant?->nom ?? '') . ' ' . ($representant?->prenom ?? '')),
            'gerant_prenom'               => $representant?->prenom                 ?? '',
            'gerant_cin'                  => $representant?->cin                    ?? '',
            'gerant_telephone'            => $representant?->telephone              ?? '',
            'telephone'                   => $representant?->telephone              ?? '',
            'gerant_email'                => $representant?->email                  ?? '',
            'email'                       => $representant?->email                  ?? '',
            'gerant_adresse'              => $representant?->adresse                ?? '',
            'gerant_nationalite'          => $representant?->nationalite            ?? '',
            'date_naissance'              => $date($representant?->date_naissance),
            'date_debut'                  => $date($contrat->date_debut),
            'date_fin'                    => $date($contrat->date_fin),
            'date_signature'              => $date($contrat->date_signature),
            'duree_mois'                  => (string) ($contrat->duree_mois ?? ''),
            'instruction_no'              => $contrat->instruction_no ?? '',
            'ville_signature'             => $contrat->ville_signature ?? '',
            'prix_mensuel'                => $fmt($contrat->prix_mensuel),
            'prix_total'                  => $fmt($contrat->prix_total),
            'caution'                     => $fmt($contrat->caution),
            'mode_paiement'               => $contrat->mode_paiement ?? '',
            'redevance_mensuelle'         => $fmt($contrat->prix_mensuel),
            'redevance_annuelle'          => $fmt($contrat->prix_total),
        ];
    }

    private function resolveTokens(string $text, array $tokenMap): string
    {
        foreach ($tokenMap as $key => $value) {
            $text = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $value, $text);
        }
        return $text;
    }

    /**
     * Accepts both:
     *   Object form: [{id: "3", ordre: 1}, ...]
     *   Flat form:   ["3", "14"]
     */
    private function syncArticles(Contrat $contrat, array $rawArticles): void
    {
        if (empty($rawArticles)) {
            $contrat->articles()->sync([]);
            return;
        }

        $syncData = [];

        foreach ($rawArticles as $index => $item) {
            if (is_array($item)) {
                $articleId = (int) ($item['id']    ?? 0);
                $ordre     = (int) ($item['ordre'] ?? ($index + 1));
            } else {
                $articleId = (int) $item;
                $ordre     = $index + 1;
            }

            if ($articleId > 0) {
                $syncData[$articleId] = ['ordre' => $ordre];
            }
        }

        $contrat->articles()->sync($syncData);
    }
}
