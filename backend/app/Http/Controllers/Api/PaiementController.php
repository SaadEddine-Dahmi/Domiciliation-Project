<?php
// ============================================================
// app/Http/Controllers/Api/PaiementController.php
// Gestion des paiements liés aux contrats
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    /** Liste les paiements d'un contrat */
    public function index(int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($contratId);

        $paiements = Paiement::query()
            ->whereHas('facture', fn($q) => $q->where('contrat_id', $contrat->id))
            ->with('facture:id,montant_total,statut,date_facture')
            ->latest('date_paiement')
            ->get();

        return response()->json(['success' => true, 'data' => $paiements]);
    }

    /** Crée une facture + enregistre un paiement */
    public function store(Request $request, int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)
            ->whereIn('statut', ['active', 'draft'])
            ->findOrFail($contratId);

        $data = $request->validate([
            'montant'        => ['required', 'numeric', 'min:0'],
            'date_paiement'  => ['required', 'date'],
            'mode_paiement'  => ['required', 'string', 'max:100'],
            'note'           => ['nullable', 'string', 'max:500'],
        ]);

        $paiement = DB::transaction(function () use ($data, $contrat, $tenantId) {
            // Créer une facture liée
            $facture = Facture::create([
                'contrat_id'    => $contrat->id,
                'entreprise_id' => $contrat->entreprise_id,
                'montant_total' => $data['montant'],
                'statut'        => 'paid',
                'date_facture'  => $data['date_paiement'],
            ]);

            // Enregistrer le paiement
            $p = Paiement::create([
                'facture_id'    => $facture->id,
                'montant'       => $data['montant'],
                'date_paiement' => $data['date_paiement'],
                'mode_paiement' => $data['mode_paiement'],
            ]);

            // Notifier le domiciliataire
            AppNotification::create([
                'user_id'    => $tenantId,
                'contrat_id' => $contrat->id,
                'message'    => "💳 Paiement de {$data['montant']} DH enregistré pour {$contrat->entreprise->raison_sociale}.",
                'is_read'    => false,
            ]);

            return $p;
        });

        return response()->json([
            'success' => true,
            'data'    => $paiement->load('facture'),
        ], 201);
    }

    /** Résumé des paiements pour un contrat */
    public function summary(int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($contratId);

        $totalPaye = Paiement::query()
            ->whereHas('facture', fn($q) => $q->where('contrat_id', $contrat->id))
            ->sum('montant');

        $prixTotal  = (float) ($contrat->prix_total ?? 0);
        $restant    = max(0, $prixTotal - (float) $totalPaye);

        return response()->json([
            'success' => true,
            'data'    => [
                'prix_total'  => $prixTotal,
                'total_paye'  => (float) $totalPaye,
                'restant'     => $restant,
                'pourcentage' => $prixTotal > 0 ? round(($totalPaye / $prixTotal) * 100) : 0,
            ],
        ]);
    }
}
