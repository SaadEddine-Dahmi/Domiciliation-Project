<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\AppNotification;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ContratController extends Controller
{
    public function __construct(private TemplateService $templateService) {}

    private function tenantIdOrFail(Request $request): int
    {
        $user = $request->user();
        if (!$user)
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        return (int) $user->id;
    }

    // ── Index ────────────────────────────────────────────
    public function index(Request $request)
    {
        $user     = $request->user();
        $tenantId = (int) $user->id;

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $tenantId)->first();
            if (!$entreprise)
                return response()->json(['success' => true, 'data' => []]);

            $contrats = Contrat::where('entreprise_id', $entreprise->id)
                ->where('statut', 'active')
                ->with(['entreprise:id,raison_sociale'])
                ->latest()
                ->get();

            return response()->json(['success' => true, 'data' => $contrats]);
        }

        $query = Contrat::query()
            ->with(['entreprise:id,raison_sociale', 'articles:id,title,body'])
            ->where('domiciliataire_id', $tenantId)
            ->latest();

        if ($request->filled('entreprise_id'))
            $query->where('entreprise_id', (int) $request->input('entreprise_id'));
        if ($request->filled('statut'))
            $query->where('statut', $request->input('statut'));

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // ── Store ────────────────────────────────────────────
    public function store(Request $request)
    {
        $tenantId = $this->tenantIdOrFail($request);

        $data = $request->validate([
            'entreprise_id'            => ['required', 'integer', 'exists:entreprises,id'],
            'date_signature'           => ['nullable', 'date'],
            'date_debut'               => ['required', 'date'],
            'date_fin'                 => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois'               => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'             => ['nullable', 'numeric', 'min:0'],
            'prix_total'               => ['nullable', 'numeric', 'min:0'],
            'caution'                  => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'            => ['nullable', 'string', 'max:100'],
            'statut'                   => ['nullable', 'in:draft,active,expired,terminated'],
            'notification_delay_months'=> ['nullable', 'integer', 'in:1,3,6'],
            'article_ids'              => ['nullable', 'array'],
            'article_ids.*'            => ['nullable'],
        ]);

        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail((int) $data['entreprise_id']);

        $contrat = DB::transaction(function () use ($data, $tenantId) {
            $row = Contrat::create([
                'domiciliataire_id'        => $tenantId,
                'entreprise_id'            => $data['entreprise_id'],
                'date_signature'           => $data['date_signature']            ?? null,
                'date_debut'               => $data['date_debut'],
                'date_fin'                 => $data['date_fin']                  ?? null,
                'duree_mois'               => $data['duree_mois']                ?? null,
                'prix_mensuel'             => $data['prix_mensuel']              ?? null,
                'prix_total'               => $data['prix_total']                ?? null,
                'caution'                  => $data['caution']                   ?? null,
                'mode_paiement'            => $data['mode_paiement']             ?? null,
                'statut'                   => 'draft',
                'notification_delay_months'=> $data['notification_delay_months'] ?? 1,
            ]);
            $this->syncArticles($row, $data['article_ids'] ?? []);
            return $row;
        });

        return response()->json([
            'success' => true,
            'data'    => $contrat->load(['entreprise:id,raison_sociale', 'articles:id,title,body']),
        ], 201);
    }

    // ── Show ─────────────────────────────────────────────
    public function show(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat  = Contrat::query()
            ->with(['entreprise:id,raison_sociale', 'articles' => fn($q) => $q->orderBy('contrat_articles.ordre')])
            ->where('domiciliataire_id', $tenantId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ── Update ───────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'entreprise_id'            => ['required', 'integer', 'exists:entreprises,id'],
            'date_signature'           => ['nullable', 'date'],
            'date_debut'               => ['required', 'date'],
            'date_fin'                 => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois'               => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'             => ['nullable', 'numeric', 'min:0'],
            'prix_total'               => ['nullable', 'numeric', 'min:0'],
            'caution'                  => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'            => ['nullable', 'string', 'max:100'],
            'statut'                   => ['required', 'in:draft,active,expired,terminated'],
            'notification_delay_months'=> ['nullable', 'integer', 'in:1,3,6'],
            'article_ids'              => ['nullable', 'array'],
            'article_ids.*'            => ['nullable'],
        ]);

        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail((int) $data['entreprise_id']);

        DB::transaction(function () use ($contrat, $data) {
            $contrat->update([
                'entreprise_id'            => $data['entreprise_id'],
                'date_signature'           => $data['date_signature']            ?? null,
                'date_debut'               => $data['date_debut'],
                'date_fin'                 => $data['date_fin']                  ?? null,
                'duree_mois'               => $data['duree_mois']                ?? null,
                'prix_mensuel'             => $data['prix_mensuel']              ?? null,
                'prix_total'               => $data['prix_total']                ?? null,
                'caution'                  => $data['caution']                   ?? null,
                'mode_paiement'            => $data['mode_paiement']             ?? null,
                'statut'                   => $data['statut'],
                'notification_delay_months'=> $data['notification_delay_months'] ?? $contrat->notification_delay_months,
            ]);
            $this->syncArticles($contrat, $data['article_ids'] ?? []);
        });

        return response()->json([
            'success' => true,
            'data'    => $contrat->fresh(['entreprise:id,raison_sociale', 'articles:id,title,body']),
        ]);
    }

    // ── Generate PDF ─────────────────────────────────────
    public function generatePdf(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);

        // Load contrat with all relations needed for variable resolution
        $contrat = Contrat::query()
            ->with([
                'entreprise.representant', // needed for {{gerant_nom}}, {{gerant_cin}} etc.
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
                'domiciliataire',
            ])
            ->where('domiciliataire_id', $tenantId)
            ->findOrFail($id);

        // Build variable map from contrat data
        // This resolves all {{variable}} placeholders
        $templateData = $this->templateService->dataFromContrat($contrat);

        // Render each article body — replace variables + wrap in <strong>
        $articlesHtml = $contrat->articles->map(function ($article, $i) use ($templateData) {
            // Replace {{variables}} in article body with real bold values
            $renderedBody = $this->templateService->render($article->body ?? '', $templateData);

            return "
                <div style='margin:14px 0'>
                    <p style='font-weight:700;margin:0 0 6px;font-size:13px'>
                        ARTICLE " . ($i + 1) . " — " . e($article->title) . "
                    </p>
                    <p style='margin:0;line-height:1.7;text-align:justify'>
                        {$renderedBody}
                    </p>
                </div>
            ";
        })->implode('');

        // Header values — also use template data for consistency
        $company  = e($contrat->entreprise->raison_sociale ?? '-');
        $manager  = e(trim(($contrat->domiciliataire->nom ?? '') . ' ' . ($contrat->domiciliataire->prenom ?? '')));
        $dateDebut = $templateData['date_debut'] ?: '-';
        $dateFin   = $templateData['date_fin']   ?: '-';
        $mensuel   = $templateData['redevance_mensuelle'];
        $annuel    = $templateData['redevance_annuelle'];
        $instrNo   = $templateData['instruction_no'] ?: '';
        $ville     = $templateData['ville_signature'] ?: 'AGADIR';

        // Full PDF HTML — professional contract layout
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.6;
            margin: 0;
            padding: 24px 32px;
        }
        h1 {
            text-align: center;
            font-size: 16px;
            margin: 0 0 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .sub {
            text-align: center;
            color: #555;
            font-size: 10px;
            margin: 0 0 14px;
        }
        .hr-gold {
            border: none;
            border-top: 1.5px solid #c8a96e;
            margin: 12px 0;
        }
        .hr-light {
            border: none;
            border-top: 1px solid #ddd;
            margin: 10px 0;
        }
        .label { font-weight: bold; }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin: 14px 0 6px;
        }
        .signatures {
            display: table;
            width: 100%;
            margin-top: 50px;
        }
        .sig-left  { display: table-cell; width: 50%; }
        .sig-right { display: table-cell; width: 50%; text-align: right; }
        strong { font-weight: bold; }
    </style>
</head>
<body>

    <h1>Contrat de Domiciliation</h1>
    <p class='sub'>
        " . ($instrNo ? "Instruction N° : <strong>{$instrNo}</strong> &nbsp;|&nbsp;" : '') . "
        Domiciliataire : <strong>{$manager}</strong>
    </p>

    <hr class='hr-gold'>

    <p class='section-title'>Parties</p>
    <p>
        <span class='label'>Domiciliataire :</span> {$manager}
    </p>
    <p>
        <span class='label'>Domicilié :</span> {$company}
    </p>

    <hr class='hr-light'>

    <p class='section-title'>Durée &amp; Redevance</p>
    <p><span class='label'>Période :</span> {$dateDebut} → {$dateFin}</p>
    <p>
        <span class='label'>Redevance mensuelle :</span> {$mensuel}
        &nbsp;|&nbsp;
        <span class='label'>Annuelle :</span> {$annuel}
    </p>

    <hr class='hr-light'>

    <p class='section-title'>Articles du contrat</p>

    " . ($articlesHtml ?: "<p style='color:#999'>Aucun article sélectionné.</p>") . "

    <hr class='hr-gold'>

    <div class='signatures'>
        <div class='sig-left'>
            <p style='margin:0 0 50px'>Fait à {$ville}, le _________________</p>
            <p style='margin:0'><strong>Signature du domiciliataire</strong></p>
            <p style='margin:4px 0 0;font-size:10px;color:#555'>{$manager}</p>
        </div>
        <div class='sig-right'>
            <p style='margin:0 0 50px'>Lu et approuvé, bon pour accord</p>
            <p style='margin:0'><strong>Signature du domicilié</strong></p>
            <p style='margin:4px 0 0;font-size:10px;color:#555'>{$company}</p>
        </div>
    </div>

</body>
</html>";

        $pdf      = PDF::loadHTML($html)->setPaper('a4', 'portrait');
        $filePath = "contrats/contrat_{$contrat->id}.pdf";
        Storage::disk('public')->put($filePath, $pdf->output());
        $contrat->update(['pdf_path' => $filePath]);

        return response()->json([
            'success' => true,
            'data'    => [
                'pdf_path' => $filePath,
                'url'      => asset('storage/' . $filePath),
            ],
        ]);
    }

    // ── Activate ─────────────────────────────────────────
    public function activate(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)
            ->where('statut', 'draft')
            ->findOrFail($id);

        $request->validate([
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('signed_pdf')->store('contrats/signed', 'public');
        $contrat->update(['scanned_pdf_path' => $path]);
        $contrat->activate();

        AppNotification::create([
            'user_id'    => $tenantId,
            'contrat_id' => $contrat->id,
            'message'    => "Le contrat #{$contrat->id} ({$contrat->entreprise->raison_sociale}) est maintenant actif.",
            'is_read'    => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrat activé avec succès.',
            'data'    => $contrat->fresh(['entreprise:id,raison_sociale']),
        ]);
    }

    // ── Terminate ─────────────────────────────────────────
    public function terminate(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)
            ->whereIn('statut', ['active'])
            ->findOrFail($id);

        $contrat->terminate();

        return response()->json(['success' => true, 'message' => 'Contrat résilié.']);
    }

    // ── Helpers ───────────────────────────────────────────
    private function syncArticles(Contrat $contrat, array $articleIds): void
    {
        $syncPayload = [];
        foreach (array_values(array_filter($articleIds)) as $i => $articleId) {
            $syncPayload[$articleId] = ['ordre' => $i + 1];
        }
        $contrat->articles()->sync($syncPayload);
    }
}
