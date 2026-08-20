<?php
// app/Http/Controllers/Api/PaiementController.php
//
// Manages payments recorded against a contract's invoices.
//
// BUSINESS RULE (fix applied here):
//   A payment can never push the total paid past the contract's prix_total.
//   remainingBalance() is the single source of truth for this check and is
//   used both by store() (to reject over-payments) and summary() (to display
//   the balance) so the two can never drift out of sync.

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
    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Compute how much is still owed on a contract.
     *
     * remaining = max(0, prix_total - sum(all payments on this contract's invoices))
     *
     * Returns 0.0 when prix_total is null — callers must check prix_total
     * separately if they need to distinguish "no amount set" from "fully paid".
     */
    private function remainingBalance(Contrat $contrat): float
    {
        $totalPaye = Paiement::query()
            ->whereHas('facture', fn($q) => $q->where('contrat_id', $contrat->id))
            ->sum('montant');

        $prixTotal = (float) ($contrat->prix_total ?? 0);

        return max(0, $prixTotal - (float) $totalPaye);
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{contrat}/paiements
     *
     * Lists all payments recorded against a contract's invoices,
     * most recent first, with the parent invoice's key fields eager-loaded.
     */
    public function index(int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($contratId);

        $paiements = Paiement::query()
            ->whereHas('facture', fn($q) => $q->where('contrat_id', $contrat->id))
            ->with('facture:id,numero_facture,montant_total,statut,date_facture')
            ->latest('date_paiement')
            ->get();

        return response()->json(['success' => true, 'data' => $paiements]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{contrat}/paiements
     *
     * Creates a new invoice + payment record in a single transaction, then
     * notifies the domiciliataire that a payment was received.
     *
     * VALIDATION FIX:
     *   Before doing anything, the requested montant is checked against
     *   remainingBalance(). If it exceeds the balance, the request is
     *   rejected with 422 and no invoice/payment row is created.
     *   A small epsilon (0.01) absorbs floating-point rounding noise from
     *   decimal(10,2) columns without allowing meaningful over-payment.
     */
    public function store(Request $request, int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)
            ->whereIn('statut', ['active', 'draft'])
            ->findOrFail($contratId);

        $data = $request->validate([
            'montant'       => ['required', 'numeric', 'min:0.01'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['required', 'string', 'max:100'],
            'note'          => ['nullable', 'string', 'max:500'],
        ]);

        // Only enforce the cap when the contract actually has a price set.
        // A contract with no prix_total has nothing to compare against.
        if ($contrat->prix_total !== null) {
            $restant = $this->remainingBalance($contrat);

            if ($data['montant'] > $restant + 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'Le montant saisi (%s DH) dépasse le solde restant du contrat (%s DH).',
                        number_format($data['montant'], 2, ',', ' '),
                        number_format($restant, 2, ',', ' ')
                    ),
                ], 422);
            }
        }

        $paiement = DB::transaction(function () use ($data, $contrat, $tenantId) {
            // Create the invoice linked to this payment.
            $facture = Facture::create([
                'contrat_id'    => $contrat->id,
                'entreprise_id' => $contrat->entreprise_id,
                'montant_total' => $data['montant'],
                'statut'        => 'paid',
                'date_facture'  => $data['date_paiement'],
            ]);

            // Record the payment itself.
            $p = Paiement::create([
                'facture_id'    => $facture->id,
                'montant'       => $data['montant'],
                'date_paiement' => $data['date_paiement'],
                'mode_paiement' => $data['mode_paiement'],
            ]);

            // Notify the domiciliataire that a payment was received.
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

    // ── Summary ────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{contrat}/paiements/summary
     *
     * Returns the payment summary for a contract: total due, total paid,
     * remaining balance, and completion percentage.
     *
     * Now backed by remainingBalance() — the same function used by store()'s
     * over-payment check, so the number shown here always matches what the
     * backend will actually accept.
     */
    public function summary(int $contratId)
    {
        $tenantId = auth()->id();
        $contrat  = Contrat::where('domiciliataire_id', $tenantId)->findOrFail($contratId);

        $prixTotal = (float) ($contrat->prix_total ?? 0);
        $restant   = $this->remainingBalance($contrat);
        $totalPaye = $prixTotal - $restant;

        return response()->json([
            'success' => true,
            'data'    => [
                'prix_total'  => $prixTotal,
                'total_paye'  => $totalPaye,
                'restant'     => $restant,
                'pourcentage' => $prixTotal > 0 ? round(($totalPaye / $prixTotal) * 100) : 0,
            ],
        ]);
    }
}