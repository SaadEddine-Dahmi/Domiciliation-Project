<?php
// ============================================================
// app/Http/Controllers/Api/ContratController.php
// Gestion complète des contrats avec state machine :
//   draft → active (via upload PDF signé)
//   active → expired (via cron)
//   active → terminated (manuel)
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ContratController extends Controller
{
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
        $user = $request->user();
        $tenantId = (int) $user->id;

        // Client : uniquement ses contrats actifs
        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $tenantId)->first();
            if (!$entreprise)
                return response()->json(['success' => true, 'data' => []]);

            $contrats = Contrat::where('entreprise_id', $entreprise->id)
                ->where('statut', 'active')   // client voit uniquement les actifs
                ->with(['entreprise:id,raison_sociale'])
                ->latest()
                ->get();

            return response()->json(['success' => true, 'data' => $contrats]);
        }

        // Domiciliataire : tous ses contrats
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
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'date_signature' => ['nullable', 'date'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois' => ['nullable', 'integer', 'min:1'],
            'prix_mensuel' => ['nullable', 'numeric', 'min:0'],
            'prix_total' => ['nullable', 'numeric', 'min:0'],
            'caution' => ['nullable', 'numeric', 'min:0'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'in:draft,active,expired,terminated'],
            'notification_delay_months' => ['nullable', 'integer', 'in:1,3,6'],
            'article_ids' => ['nullable', 'array'],
            'article_ids.*' => ['nullable'],
        ]);

        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail((int) $data['entreprise_id']);

        $contrat = DB::transaction(function () use ($data, $tenantId) {
            $row = Contrat::create([
                'domiciliataire_id' => $tenantId,
                'entreprise_id' => $data['entreprise_id'],
                'date_signature' => $data['date_signature'] ?? null,
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'] ?? null,
                'duree_mois' => $data['duree_mois'] ?? null,
                'prix_mensuel' => $data['prix_mensuel'] ?? null,
                'prix_total' => $data['prix_total'] ?? null,
                'caution' => $data['caution'] ?? null,
                'mode_paiement' => $data['mode_paiement'] ?? null,
                'statut' => 'draft',   // toujours draft à la création
                'notification_delay_months' => $data['notification_delay_months'] ?? 1,
            ]);
            $this->syncArticles($row, $data['article_ids'] ?? []);
            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $contrat->load(['entreprise:id,raison_sociale', 'articles:id,title,body']),
        ], 201);
    }

    // ── Show ─────────────────────────────────────────────
    public function show(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat = Contrat::query()
            ->with(['entreprise:id,raison_sociale', 'articles' => fn($q) => $q->orderBy('contrat_articles.ordre')])
            ->where('domiciliataire_id', $tenantId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ── Update ───────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'date_signature' => ['nullable', 'date'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois' => ['nullable', 'integer', 'min:1'],
            'prix_mensuel' => ['nullable', 'numeric', 'min:0'],
            'prix_total' => ['nullable', 'numeric', 'min:0'],
            'caution' => ['nullable', 'numeric', 'min:0'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
            'statut' => ['required', 'in:draft,active,expired,terminated'],
            'notification_delay_months' => ['nullable', 'integer', 'in:1,3,6'],
            'article_ids' => ['nullable', 'array'],
            'article_ids.*' => ['nullable'],
        ]);

        Entreprise::where('domiciliataire_id', $tenantId)->findOrFail((int) $data['entreprise_id']);

        DB::transaction(function () use ($contrat, $data) {
            $contrat->update([
                'entreprise_id' => $data['entreprise_id'],
                'date_signature' => $data['date_signature'] ?? null,
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'] ?? null,
                'duree_mois' => $data['duree_mois'] ?? null,
                'prix_mensuel' => $data['prix_mensuel'] ?? null,
                'prix_total' => $data['prix_total'] ?? null,
                'caution' => $data['caution'] ?? null,
                'mode_paiement' => $data['mode_paiement'] ?? null,
                'statut' => $data['statut'],
                'notification_delay_months' => $data['notification_delay_months'] ?? $contrat->notification_delay_months,
            ]);
            $this->syncArticles($contrat, $data['article_ids'] ?? []);
        });

        return response()->json([
            'success' => true,
            'data' => $contrat->fresh(['entreprise:id,raison_sociale', 'articles:id,title,body']),
        ]);
    }

    // ── Générer PDF (draft → reste draft jusqu'à upload signé) ──
    public function generatePdf(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat = Contrat::query()
            ->with(['entreprise', 'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'), 'domiciliataire'])
            ->where('domiciliataire_id', $tenantId)
            ->findOrFail($id);

        $articlesHtml = $contrat->articles->map(function ($a, $i) {
            return "<div style='margin:12px 0'>
                <p style='font-weight:700;margin:0 0 4px'>ARTICLE " . ($i + 1) . " — " . e($a->title) . "</p>
                <p style='margin:0;line-height:1.6'>" . nl2br(e($a->body ?? '')) . "</p>
            </div>";
        })->implode('');

        $company = e($contrat->entreprise->raison_sociale ?? '-');
        $manager = e(trim(($contrat->domiciliataire->nom ?? '') . ' ' . ($contrat->domiciliataire->prenom ?? '')));
        $dateDebut = $contrat->date_debut?->format('d/m/Y') ?? '-';
        $dateFin = $contrat->date_fin?->format('d/m/Y') ?? '-';
        $total = number_format((float) ($contrat->prix_total ?? 0), 2, '.', ' ');
        $mensuel = number_format((float) ($contrat->prix_mensuel ?? 0), 2, '.', ' ');

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
  body{font-family:DejaVu Sans,sans-serif;color:#111;font-size:12px;line-height:1.6;margin:0;padding:20px}
  h1{text-align:center;font-size:18px;margin:0 0 6px;letter-spacing:1px}
  .sub{text-align:center;color:#666;font-size:11px;margin:0 0 16px}
  .hr{border:none;border-top:1px solid #c8a96e;margin:14px 0}
  .hr2{border:none;border-top:1px solid #ddd;margin:12px 0}
  .signatures{display:table;width:100%;margin-top:50px}
  .sig-left{display:table-cell;width:50%}
  .sig-right{display:table-cell;width:50%;text-align:right}
</style></head><body>
  <h1>CONTRAT DE DOMICILIATION</h1>
  <p class='sub'>Domiciliataire : <b>{$manager}</b></p>
  <hr class='hr'>
  <p><b>Entreprise :</b> {$company}</p>
  <p><b>Période :</b> {$dateDebut} → {$dateFin}</p>
  <p><b>Redevance mensuelle :</b> {$mensuel} DH &nbsp;|&nbsp; <b>Total :</b> {$total} DH</p>
  <hr class='hr2'>{$articlesHtml}
  <div class='signatures'>
    <div class='sig-left'><p style='margin:0 0 50px'>Fait à _________________</p><p>Signature du domiciliataire</p></div>
    <div class='sig-right'><p style='margin:0 0 50px'>Le _________________</p><p>Signature du domicilié</p></div>
  </div>
</body></html>";

        $pdf = PDF::loadHTML($html)->setPaper('a4', 'portrait');
        $filePath = "contrats/contrat_{$contrat->id}.pdf";
        Storage::disk('public')->put($filePath, $pdf->output());
        $contrat->update(['pdf_path' => $filePath]);

        return response()->json([
            'success' => true,
            'data' => ['pdf_path' => $filePath, 'url' => asset('storage/' . $filePath)],
        ]);
    }

    /**
     * Upload du PDF signé/légalisé → active le contrat automatiquement
     * POST /api/contrats/{id}/activate
     */
    public function activate(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat = Contrat::where('domiciliataire_id', $tenantId)
            ->where('statut', 'draft')
            ->findOrFail($id);

        $request->validate([
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        // Stocker le PDF signé
        $path = $request->file('signed_pdf')->store("contrats/signed", 'public');
        $contrat->update(['scanned_pdf_path' => $path]);

        // Transition draft → active + création alerte
        $contrat->activate();

        // Notifier le domiciliataire
        AppNotification::create([
            'user_id' => $tenantId,
            'contrat_id' => $contrat->id,
            'message' => "Le contrat #{$contrat->id} ({$contrat->entreprise->raison_sociale}) est maintenant actif.",
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrat activé avec succès.',
            'data' => $contrat->fresh(['entreprise:id,raison_sociale']),
        ]);
    }

    /**
     * Résiliation manuelle
     * POST /api/contrats/{id}/terminate
     */
    public function terminate(Request $request, int $id)
    {
        $tenantId = $this->tenantIdOrFail($request);
        $contrat = Contrat::where('domiciliataire_id', $tenantId)
            ->whereIn('statut', ['active'])
            ->findOrFail($id);

        $contrat->terminate();

        return response()->json(['success' => true, 'message' => 'Contrat résilié.']);
    }

    private function syncArticles(Contrat $contrat, array $articleIds): void
    {
        $syncPayload = [];
        foreach (array_values(array_filter($articleIds)) as $i => $articleId) {
            $syncPayload[$articleId] = ['ordre' => $i + 1];
        }
        $contrat->articles()->sync($syncPayload);
    }
}