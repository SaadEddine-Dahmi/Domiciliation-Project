<?php
// app/Console/Commands/CheckExpiredContracts.php
//
// Daily scheduled job: contracts:expire-check
//
// 1. Flips any 'active' contract whose date_fin has passed to 'expired'.
// 2. For contracts that just became expired (or were already expired)
//    and have no open renewal draft yet, pushes a one-time reminder
//    notification to the domiciliataire nudging them to renew.
//    Deduped via a simple "already notified" check on existing
//    unread notifications referencing this contrat_id, so re-running
//    the command on the same day doesn't spam.
//
// NOTE: I don't have the original version of this command (if one already
// existed) — this is written fresh from the behavior described in project
// notes ("contracts:expire-check scheduled command transitions
// active → expired daily"). If a version already exists with different
// logic, send it and I'll merge instead of overwrite.

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contrat;
use Illuminate\Console\Command;

class CheckExpiredContracts extends Command
{
    protected $signature = 'contracts:expire-check';
    protected $description = 'Transition active contracts past date_fin to expired, and nudge domiciliataires to renew.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // ── Step 1: expire contracts whose end date has passed ──────────────
        $toExpire = Contrat::where('statut', 'active')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', $today)
            ->get();

        foreach ($toExpire as $contrat) {
            $contrat->expire();
            $this->info("Contrat #{$contrat->id} expiré.");
        }

        foreach ($expired as $contrat) {
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
                'message' => $message,
                'is_read' => false,
            ]);

            $this->line("Contrat #{$contrat->id} -> expired");
        }

        // ── Step 2: renewal nudge for any expired contract without an ───────
        //            open renewal draft, not yet notified.
        $needsNudge = Contrat::where('statut', 'expired')
            ->with('entreprise:id,raison_sociale')
            ->get()
            ->filter(fn(Contrat $c) => !$c->hasOpenRenewal());

        foreach ($needsNudge as $contrat) {
            $alreadyNotified = AppNotification::where('user_id', $contrat->domiciliataire_id)
                ->where('contrat_id', $contrat->id)
                ->whereNull('from_user_id')
                ->where('message', 'like', '%a expiré%')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            AppNotification::create([
                'user_id' => $contrat->domiciliataire_id,
                'contrat_id' => $contrat->id,
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