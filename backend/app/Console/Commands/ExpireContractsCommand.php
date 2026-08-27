<?php
// app/Console/Commands/ExpireContractsCommand.php
//
// Daily scheduled job: contracts:expire-check
//
// 1. Flips any 'active' contract whose date_fin has passed to 'expired',
//    notifying the domiciliataire in-app.
// 2. For expired contracts with no open renewal draft yet, sends a
//    one-time "please renew" nudge to the domiciliataire. Deduped
//    against previous nudges so re-running the command the same day
//    doesn't spam.
//
// FIX: the previous version looped over an undefined $expired variable
// (only $toExpire was ever populated) — this threw an "Undefined
// variable" error on every run and duplicated the notification logic.
// Consolidated into a single loop below. The "already notified" dedupe
// also used to match on `message LIKE '%a expiré%'`, which is fragile
// and would match either message type — replaced with a check on the
// new `type` column.

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

        // ── Step 1: expire contracts whose end date has passed ──────────
        $toExpire = Contrat::where('statut', 'active')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', $today)
            ->with('entreprise:id,raison_sociale')
            ->get();

        foreach ($toExpire as $contrat) {
            $contrat->expire();

            $message = $contrat->hasOpenRenewal()
                ? sprintf(
                    'ℹ️ Le contrat de %s a expiré le %s — un renouvellement est déjà en préparation.',
                    $contrat->entreprise?->raison_sociale ?? 'un client',
                    $contrat->date_fin->format('d/m/Y')
                )
                : sprintf(
                    '⚠️ Le contrat de %s a expiré le %s.',
                    $contrat->entreprise?->raison_sociale ?? 'un client',
                    $contrat->date_fin->format('d/m/Y')
                );

            AppNotification::create([
                'user_id' => $contrat->domiciliataire_id,
                'contrat_id' => $contrat->id,
                'type' => 'contract_expired',
                'message' => $message,
                'is_read' => false,
            ]);

            $this->info("Contrat #{$contrat->id} -> expired");
        }

        // ── Step 2: renewal nudge for expired contracts with no open ────
        //            renewal draft, not yet notified.
        $needsNudge = Contrat::where('statut', 'expired')
            ->with('entreprise:id,raison_sociale')
            ->get()
            ->filter(fn(Contrat $c) => !$c->hasOpenRenewal());

        foreach ($needsNudge as $contrat) {
            $alreadyNotified = AppNotification::where('user_id', $contrat->domiciliataire_id)
                ->where('contrat_id', $contrat->id)
                ->where('type', 'renewal_nudge')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            AppNotification::create([
                'user_id' => $contrat->domiciliataire_id,
                'contrat_id' => $contrat->id,
                'type' => 'renewal_nudge',
                'message' => sprintf(
                    '⚠️ Le contrat de %s a expiré. Pensez à le renouveler.',
                    $contrat->entreprise?->raison_sociale ?? "#{$contrat->id}"
                ),
                'is_read' => false,
            ]);

            $this->info("Rappel de renouvellement envoyé pour le contrat #{$contrat->id}.");
        }

        return self::SUCCESS;
    }
}
