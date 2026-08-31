<?php
// app/Console/Commands/ExpireContractsCommand.php
// Expires overdue contracts and sends renewal nudges.

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contrat;
use Illuminate\Console\Command;

class ExpireContractsCommand extends Command
{
    protected $signature = 'contracts:expire-check';
    protected $description = 'Transition active contracts past date_fin to expired, and nudge domiciliataires to renew.';

    public function handle(): int
    {
        $today = now()->toDateString();

        Contrat::where('statut', 'active')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', $today)
            ->with('entreprise:id,raison_sociale')
            ->lazyById()
            ->each(function (Contrat $contrat) {
                $contrat->expire();
                $this->notifyExpiredContract($contrat);
                $this->info("Contrat #{$contrat->id} -> expired");
            });

        Contrat::where('statut', 'expired')
            ->with('entreprise:id,raison_sociale')
            ->lazyById()
            ->each(function (Contrat $contrat) {
                if ($contrat->hasOpenRenewal() || $this->renewalNudgeExists($contrat)) {
                    return;
                }

                $this->notifyRenewalNudge($contrat);
                $this->info("Rappel de renouvellement envoyé pour le contrat #{$contrat->id}.");
            });

        return self::SUCCESS;
    }

    private function notifyExpiredContract(Contrat $contrat): void
    {
        $company = $contrat->entreprise?->raison_sociale ?? 'un client';
        $date = $contrat->date_fin->format('d/m/Y');
        $message = $contrat->hasOpenRenewal()
            ? "Le contrat de {$company} a expiré le {$date} - un renouvellement est déjà en préparation."
            : "Le contrat de {$company} a expiré le {$date}.";

        AppNotification::create([
            'user_id' => $contrat->domiciliataire_id,
            'contrat_id' => $contrat->id,
            'type' => 'contract_expired',
            'message' => $message,
            'is_read' => false,
        ]);
    }

    private function renewalNudgeExists(Contrat $contrat): bool
    {
        return AppNotification::where('user_id', $contrat->domiciliataire_id)
            ->where('contrat_id', $contrat->id)
            ->where('type', 'renewal_nudge')
            ->exists();
    }

    private function notifyRenewalNudge(Contrat $contrat): void
    {
        $company = $contrat->entreprise?->raison_sociale ?? "#{$contrat->id}";

        AppNotification::create([
            'user_id' => $contrat->domiciliataire_id,
            'contrat_id' => $contrat->id,
            'type' => 'renewal_nudge',
            'message' => "Le contrat de {$company} a expiré. Pensez à le renouveler.",
            'is_read' => false,
        ]);
    }
}
