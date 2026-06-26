<?php

// app/Http/Controllers/Api/ContratController.php
//
// Manages the full lifecycle of domiciliation contracts.
//
// Routes served:
//   GET    /api/contrats                   → index
//   POST   /api/contrats                   → store  (create draft)
//   GET    /api/contrats/{id}              → show
//   PUT    /api/contrats/{id}              → update
//   POST   /api/contrats/{id}/activate     → activate
//   POST   /api/contrats/{id}/terminate    → terminate
//   POST   /api/contrats/{id}/pdf          → generatePdf  (render + save to disk)
//   GET    /api/contrats/{id}/pdf/stream   → streamPdf    (inline iframe preview)
//
// ── CRITICAL: entreprise.representant eager-load ───────────────────────────────
//   The Blade template reads gerant_nom, gerant_cin, tel, email from the
//   entreprise object. These fields do NOT exist on the entreprises table.
//   They live in the representants table (hasOne Representant on Entreprise).
//   Every query that feeds the PDF must eager-load entreprise.representant
//   and the Blade receives a virtual-attribute-enriched entreprise object
//   via prepareEntrepriseForPdf().
//
// ── titre_contrat ──────────────────────────────────────────────────────────────
//   Stored exactly as the domiciliataire typed it in wizard step 1.
//   The fallback 'Contrat de Domiciliation' is applied ONLY in store()
//   when the field arrives null or empty.
//
// ── syncArticles() ─────────────────────────────────────────────────────────────
//   Article PKs are integer auto-increment.
//   The frontend sends them as strings (String(a.id) in the wizard).
//   Accepted formats: [{id:"3",ordre:1}] or flat [3,14].
//   IDs resolving to <= 0 are silently skipped.
//
// ── streamPdf() ────────────────────────────────────────────────────────────────
//   Registered OUTSIDE auth:sanctum in routes/api.php.
//   A browser <iframe src="…"> cannot attach Authorization: Bearer.
//   If inside sanctum every preview attempt receives 401 → blank iframe.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContratController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats
     *
     * Returns all contracts belonging to the authenticated domiciliataire,
     * with their related entreprise and ordered articles eager-loaded.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $contrats = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $contrats]);
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{id}
     *
     * Returns one contract with its ordered articles eagerly loaded.
     * IDOR guard: scoped to the authenticated domiciliataire.
     */
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

    // ── Store ──────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats
     *
     * Creates a new draft contract and writes the selected articles with their
     * display order into the contrat_articles pivot table via syncArticles().
     *
     * titre_contrat:
     *   Defaults to 'Contrat de Domiciliation' when null or empty.
     *   This is the ONLY place the default is applied.
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
            'articles.*.id'  => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $user->id,
            'entreprise_id'     => $data['entreprise_id'],
            'titre_contrat'     => $data['titre_contrat'] ?: 'Contrat de Domiciliation',
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
            'statut'            => $data['statut']            ?? 'draft',
        ]);

        $this->syncArticles($contrat, $request->input('articles', []));

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat], 201);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    /**
     * PUT /api/contrats/{id}
     *
     * Updates an existing draft contract and re-syncs article selections.
     * IDOR guard: scoped to the authenticated domiciliataire.
     */
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
            'articles.*.id'  => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

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

    // ── Activate ───────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/activate
     *
     * Transitions a draft contract to active.
     */
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

    // ── Terminate ──────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/terminate
     *
     * Transitions an active contract to terminated status.
     */
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

    // ── Generate PDF (save to disk) ────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/pdf
     *
     * Renders the Blade template via dompdf, resolves all {{variable}} tokens
     * in article bodies, saves the output to storage/app/public/contrats/,
     * updates pdf_path on the record, and returns the public Storage URL.
     */
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

        // Enrich the entreprise object with virtual attributes the Blade expects.
        // The Blade reads $contrat->entreprise->gerant_nom etc. which are not real
        // columns — they come from the representants table via the representant relation.
        $this->prepareEntrepriseForPdf($contrat);

        // Build the {{variable}} → resolved value map
        $tokenMap = $this->buildTokenMap($contrat);

        // Resolve tokens in every article body before passing to Blade.
        // The Blade uses {!! nl2br(e($article->body)) !!} — e() HTML-escapes,
        // so we must resolve tokens BEFORE the Blade touches the string.
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

    // ── Stream PDF (inline iframe preview) ────────────────────────────────────

    /**
     * GET /api/contrats/{id}/pdf/stream
     *
     * Streams the contract PDF inline for <iframe> preview.
     *
     * WHY THIS ROUTE IS OUTSIDE auth:sanctum:
     *   A browser <iframe src="…"> issues a plain GET with no custom headers.
     *   It is architecturally impossible to attach Authorization: Bearer to
     *   an iframe sub-resource request. Route registered BEFORE the middleware
     *   group in routes/api.php.
     *
     * Fast path: serves the file from disk if pdf_path is set and file exists.
     * Live path: renders on-the-fly with tokens resolved (preview before first save).
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

        // Fast path: serve the previously generated and saved file
        if ($contrat->pdf_path && Storage::disk('public')->exists($contrat->pdf_path)) {
            return response(Storage::disk('public')->get($contrat->pdf_path), 200, $headers);
        }

        // Live preview path: render on-the-fly without saving to disk
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

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Enrich the entreprise relation with virtual attributes the Blade template
     * reads as direct properties.
     *
     * WHY THIS IS NEEDED:
     *   The Blade reads $contrat->entreprise->gerant_nom, ->gerant_cin, ->tel,
     *   ->email, ->nom_societe. None of these (except raison_sociale) exist as
     *   real columns on the entreprises table. The real data is in the
     *   representants table via the entreprise->representant hasOne relation.
     *
     *   Rather than rewriting the Blade, we inject the missing attributes
     *   directly onto the Eloquent model instance using setAttribute().
     *   This is safe and non-destructive — it only affects the in-memory object,
     *   not the database row.
     *
     * @param Contrat $contrat  Must have entreprise.representant eager-loaded
     */
    private function prepareEntrepriseForPdf(Contrat $contrat): void
    {
        $entreprise   = $contrat->entreprise;
        $representant = $entreprise?->representant;

        if (!$entreprise) return;

        // nom_societe: Blade reads this but the column is raison_sociale
        $entreprise->setAttribute(
            'nom_societe',
            $entreprise->raison_sociale ?? ''
        );

        // gerant_nom: from representant nom + prenom (or nom_complet if your model adds it)
        $entreprise->setAttribute(
            'gerant_nom',
            $representant
                ? trim(($representant->nom ?? '') . ' ' . ($representant->prenom ?? ''))
                : ''
        );

        // gerant_cin: from representant.cin
        $entreprise->setAttribute(
            'gerant_cin',
            $representant?->cin ?? ''
        );

        // tel: from representant.telephone
        $entreprise->setAttribute(
            'tel',
            $representant?->telephone ?? ''
        );

        // email: from representant.email
        $entreprise->setAttribute(
            'email',
            $representant?->email ?? ''
        );

        // adresse: use representant adresse for the "Demeurant à" line,
        // fall back to entreprise adresse for the domiciliation address
        $entreprise->setAttribute(
            'adresse',
            $representant?->adresse ?? $entreprise->adresse ?? ''
        );
    }

    /**
     * Build the complete {{variable}} → resolved value map from a loaded Contrat.
     *
     * Every key here is a token the domiciliataire can write inside an article
     * body: {{raison_sociale}}, {{domiciliataire_rc}}, {{date_debut}}, etc.
     *
     * To add a new variable:
     *   1. Add the key → value pair in this method.
     *   2. Document it for the domiciliataire (help text, tooltip, etc.).
     *   resolveTokens() picks it up automatically — no other changes needed.
     *
     * Requires: entreprise.representant + domiciliataire eager-loaded on $contrat.
     *
     * @param  Contrat $contrat
     * @return array<string, string>
     */
    private function buildTokenMap(Contrat $contrat): array
    {
        $entreprise     = $contrat->entreprise;
        $representant   = $entreprise?->representant;
        $domiciliataire = $contrat->domiciliataire;

        // Helper: format a decimal as "1 500,00 DH"
        $fmt = fn($v) => $v !== null
            ? number_format((float) $v, 2, ',', ' ') . ' DH'
            : '';

        // Helper: format a date column as dd/mm/yyyy
        $date = fn($v) => $v
            ? \Carbon\Carbon::parse($v)->format('d/m/Y')
            : '';

        return [
            // ── Domiciliataire (service provider) ────────────────────────────
            'domiciliataire_nom'         => $domiciliataire?->nom_societe          ?? '',
            'domiciliataire_rc'          => $domiciliataire?->rc                   ?? '',
            'domiciliataire_if'          => $domiciliataire?->if_fiscal            ?? '',
            'domiciliataire_tp'          => $domiciliataire?->tp                   ?? '',
            'domiciliataire_adresse'     => $domiciliataire?->adresse              ?? '',
            'domiciliataire_representant'=> $domiciliataire?->representant_legal   ?? '',

            // ── Client / domicilié ────────────────────────────────────────────
            'raison_sociale'             => $entreprise?->raison_sociale           ?? '',
            'societe'                    => $entreprise?->raison_sociale           ?? '',
            'forme_juridique'            => $entreprise?->forme_juridique          ?? '',
            'adresse_domiciliation'      => $entreprise?->adresse                  ?? '',
            'ville_client'               => $entreprise?->ville                    ?? '',

            // ── Legal representative (gérant) ─────────────────────────────────
            'gerant_nom'                 => trim(
                                               ($representant?->nom    ?? '') . ' ' .
                                               ($representant?->prenom ?? '')
                                           ),
            'gerant_prenom'              => $representant?->prenom                 ?? '',
            'gerant_cin'                 => $representant?->cin                    ?? '',
            'gerant_telephone'           => $representant?->telephone              ?? '',
            'telephone'                  => $representant?->telephone              ?? '',
            'gerant_email'               => $representant?->email                  ?? '',
            'email'                      => $representant?->email                  ?? '',
            'gerant_adresse'             => $representant?->adresse                ?? '',
            'gerant_nationalite'         => $representant?->nationalite            ?? '',
            'date_naissance'             => $date($representant?->date_naissance),

            // ── Contract dates and duration ────────────────────────────────────
            'date_debut'                 => $date($contrat->date_debut),
            'date_fin'                   => $date($contrat->date_fin),
            'date_signature'             => $date($contrat->date_signature),
            'duree_mois'                 => (string) ($contrat->duree_mois         ?? ''),
            'instruction_no'             => $contrat->instruction_no               ?? '',
            'ville_signature'            => $contrat->ville_signature              ?? '',

            // ── Financial ─────────────────────────────────────────────────────
            'prix_mensuel'               => $fmt($contrat->prix_mensuel),
            'prix_total'                 => $fmt($contrat->prix_total),
            'caution'                    => $fmt($contrat->caution),
            'mode_paiement'              => $contrat->mode_paiement                ?? '',

            // ── Common aliases ─────────────────────────────────────────────────
            // Allow both spellings so domiciliataires are not blocked by typos
            'redevance_mensuelle'        => $fmt($contrat->prix_mensuel),
            'redevance_annuelle'         => $fmt($contrat->prix_total),
        ];
    }

    /**
     * Replace every {{key}} token in $text with its resolved value.
     *
     * Tokens are matched case-insensitively.
     * Optional whitespace inside braces is handled: {{ key }} works too.
     * Unrecognised tokens are left as-is so the domiciliataire can see
     * which tokens they mistyped, rather than silently erasing them.
     *
     * @param  string               $text      Raw article body from the database
     * @param  array<string,string> $tokenMap  Output of buildTokenMap()
     * @return string
     */
    private function resolveTokens(string $text, array $tokenMap): string
    {
        foreach ($tokenMap as $key => $value) {
            $text = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i',
                $value,
                $text
            );
        }
        return $text;
    }

    /**
     * Sync the contrat_articles pivot table with the provided article list.
     *
     * Accepted input formats:
     *   Object form: [ {id: "3", ordre: 1}, {id: "14", ordre: 2} ]
     *   Flat form:   [ 3, 14 ]
     *
     * Article PKs are integer auto-increment; cast each ID to (int).
     * IDs resolving to <= 0 are skipped silently.
     *
     * Eloquent sync() semantics:
     *   Detaches removed articles, attaches new ones, updates ordre for existing.
     *
     * @param  Contrat  $contrat
     * @param  array    $rawArticles  Contents of $request->input('articles', [])
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
                // Object form: {id: "3", ordre: 1}
                $articleId = (int) ($item['id']    ?? 0);
                $ordre     = (int) ($item['ordre'] ?? ($index + 1));
            } else {
                // Flat form: 3 or "3"
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
