<?php
// app/Services/Payments/PaymentService.php
// Records contract payments and computes payment summaries.

namespace App\Services\Payments;

use App\Models\AppNotification;
use App\Models\Contrat;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function remainingBalance(Contrat $contrat): float
    {
        $paid = Paiement::query()
            ->whereHas('facture', fn($query) => $query->where('contrat_id', $contrat->id))
            ->sum('montant');

        return max(0, (float) ($contrat->prix_total ?? 0) - (float) $paid);
    }

    public function record(Contrat $contrat, int $tenantId, array $data): Paiement
    {
        return DB::transaction(function () use ($contrat, $tenantId, $data) {
            $facture = Facture::create([
                'contrat_id' => $contrat->id,
                'entreprise_id' => $contrat->entreprise_id,
                'montant_total' => $data['montant'],
                'statut' => 'paid',
                'date_facture' => $data['date_paiement'],
            ]);

            $paiement = Paiement::create([
                'facture_id' => $facture->id,
                'montant' => $data['montant'],
                'date_paiement' => $data['date_paiement'],
                'mode_paiement' => $data['mode_paiement'],
            ]);

            AppNotification::create([
                'user_id' => $tenantId,
                'contrat_id' => $contrat->id,
                'type' => 'payment_received',
                'subject' => 'Facture payée',
                'message' => "Paiement de {$data['montant']} DH enregistré pour {$contrat->entreprise?->raison_sociale}.",
                'data' => [
                    'contrat_id' => $contrat->id,
                    'facture_id' => $facture->id,
                    'paiement_id' => $paiement->id,
                    'entreprise' => $contrat->entreprise?->raison_sociale,
                    'montant' => $data['montant'],
                ],
                'is_read' => false,
            ]);

            return $paiement;
        });
    }

    public function summary(Contrat $contrat): array
    {
        $total = (float) ($contrat->prix_total ?? 0);
        $remaining = $this->remainingBalance($contrat);
        $paid = $total - $remaining;

        return [
            'prix_total' => $total,
            'total_paye' => $paid,
            'restant' => $remaining,
            'pourcentage' => $total > 0 ? round(($paid / $total) * 100) : 0,
        ];
    }
}
