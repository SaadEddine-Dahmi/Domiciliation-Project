<?php
// routes/console.php
//
// Scheduled jobs for the application.
//
//   1. contracts:expire-check — runs daily at 01:00
//      Transitions active contracts whose date_fin has passed to
//      'expired' and notifies the domiciliataire. Implemented as a
//      dedicated Artisan command (app/Console/Commands/
//      ExpireContractsCommand.php) rather than an inline closure so the
//      logic has a single, testable source of truth. date_fin is a DATE
//      column, not a DATETIME, so a contract's expiration status can only
//      change once every 24 hours — running this more than once a day
//      gives no additional benefit.
//
//   2. Renewal reminder alerts — runs daily at 08:00
//      For each domiciliataire, reads their configured reminder delays
//      (1, 3, and/or 6 months — stored per-user in
//      notification_preferences) and sends a reminder notification for
//      every active contract whose date_fin falls exactly on one of those
//      future dates. A same-day duplicate guard prevents sending the same
//      reminder twice.

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Alerte;
use App\Models\AppNotification;
use App\Models\Contrat;
use App\Models\User;
use Carbon\Carbon;
use App\Mail\ContractReminderMail;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Cron 1: automatic contract expiration ─────────────────────────────────
Schedule::command('contracts:expire-check')
    ->dailyAt('01:00')
    ->name('contracts:expire-check')
    ->withoutOverlapping();

// ── Cron 2: renewal reminder alerts (multi-delay, per-domiciliataire) ────
Schedule::call(function () {
    $today = Carbon::today();

    $reminderOffsets = [
        30 => '⏳ Le contrat de %s expire dans 1 mois (le %s). Pensez à préparer le renouvellement.',
        15 => '⏳ Le contrat de %s expire dans 15 jours (le %s).',
        3  => '⏳ Le contrat de %s expire dans 3 jours (le %s). Dernière ligne droite pour le renouvellement.',
    ];

    foreach ($reminderOffsets as $daysBefore => $template) {
        $targetDate = $today->copy()->addDays($daysBefore);

        $contrats = Contrat::where('statut', 'active')
            ->whereDate('date_fin', $targetDate)
            ->with([
                'entreprise:id,raison_sociale',
                'entreprise.representant', // needed for the client's email
                'domiciliataire',
            ])
            ->get();

        foreach ($contrats as $contrat) {
            $reminderType = "pre_expiry_{$daysBefore}";

            $alreadySent = Alerte::where('contrat_id', $contrat->id)
                ->where('type', $reminderType)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            // In-app notification (unchanged behavior)
            AppNotification::create([
                'user_id' => $contrat->domiciliataire_id,
                'contrat_id' => $contrat->id,
                'message' => sprintf(
                    $template,
                    $contrat->entreprise?->raison_sociale ?? 'un client',
                    $contrat->date_fin->format('d/m/Y')
                ),
                'is_read' => false,
            ]);

            // Email to the client, if the domiciliataire has email alerts on
            // and the client has a reachable email address.
            sendReminderEmailIfEnabled($contrat, $reminderType);

            Alerte::create([
                'contrat_id' => $contrat->id,
                'date_alerte' => $today,
                'type' => $reminderType,
                'envoye' => true,
            ]);
        }
    }

    // ── Post-expiry reminder (J+1) ──────────────────────────────────────
    $postExpiryDate = $today->copy()->subDay();

    $expiredContrats = Contrat::where('statut', 'expired')
        ->whereDate('date_fin', $postExpiryDate)
        ->with([
            'entreprise:id,raison_sociale',
            'entreprise.representant',
            'domiciliataire',
        ])
        ->get();

    foreach ($expiredContrats as $contrat) {
        $reminderType = 'post_expiry';

        $alreadySent = Alerte::where('contrat_id', $contrat->id)
            ->where('type', $reminderType)
            ->exists();

        if ($alreadySent) {
            continue;
        }

        AppNotification::create([
            'user_id' => $contrat->domiciliataire_id,
            'contrat_id' => $contrat->id,
            'message' => sprintf(
                '❌ Le contrat de %s a expiré le %s. Contactez le client pour un renouvellement.',
                $contrat->entreprise?->raison_sociale ?? 'un client',
                $contrat->date_fin->format('d/m/Y')
            ),
            'is_read' => false,
        ]);

        sendReminderEmailIfEnabled($contrat, $reminderType);

        Alerte::create([
            'contrat_id' => $contrat->id,
            'date_alerte' => $today,
            'type' => $reminderType,
            'envoye' => true,
        ]);
    }
})->dailyAt('08:00')->name('alertes:send')->withoutOverlapping();

/**
 * Sends the reminder email to the client's address, provided:
 *   - the domiciliataire has email_alerts_enabled === true
 *   - the client (via representant, falling back to the linked client
 *     user account) has a usable email address
 *
 * Queued (::queue instead of ::send) so a slow/failing mail provider
 * never blocks or crashes the scheduled job for other contracts.
 */
function sendReminderEmailIfEnabled(\App\Models\Contrat $contrat, string $reminderType): void
{
    $domiciliataire = $contrat->domiciliataire;
    if (!$domiciliataire || !$domiciliataire->email_alerts_enabled) {
        return;
    }

    $clientEmail = $contrat->entreprise?->representant?->email
        ?? $contrat->entreprise?->clientUser?->email
        ?? null;

    if (!$clientEmail) {
        return;
    }

    Mail::to($clientEmail)->queue(new \App\Mail\ContractReminderMail($contrat, $reminderType));
}
