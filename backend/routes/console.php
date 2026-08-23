<?php
// routes/console.php
//
// Scheduled jobs for the application.
//
//   1. contracts:expire-check — daily at 01:00
//      See app/Console/Commands/ExpireContractsCommand.php.
//
//   2. alertes:send — daily at 08:00
//      Sends contract-expiry reminders:
//        - 3 reminders during the contract's last month: 30, 15 and 3
//          days before date_fin.
//        - 1 additional reminder 1-2 days after date_fin (post-expiry),
//          checked on both J+1 and J+2 so a missed scheduler run doesn't
//          silently skip the notification.
//      Each reminder is sent once per contract (guarded by the `alertes`
//      table) and fires in-app + email to BOTH the domiciliataire and
//      the client via NotificationService.
//
// FIX: the previous version of this file called
// sendReminderEmailIfEnabled() but that function was commented out —
// every run of this cron threw "Call to undefined function". Its logic
// now lives in NotificationService::sendReminderEmailIfEnabled(), called
// through notifyContractReminder().

use App\Models\Alerte;
use App\Models\Contrat;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Cron 1: automatic contract expiration ─────────────────────────────────
Schedule::command('contracts:expire-check')
    ->dailyAt('01:00')
    ->name('contracts:expire-check')
    ->withoutOverlapping();

// ── Cron 2: renewal reminder alerts (3 pre-expiry + 1 post-expiry) ───────
Schedule::call(function () {
    $notifications = app(NotificationService::class);
    $today = Carbon::today();

    // "3 rappels durant le dernier mois du contrat": 30, 15 and 3 days
    // before date_fin — all inside the last 30-day window.
    foreach ([30, 15, 3] as $daysBefore) {
        $targetDate = $today->copy()->addDays($daysBefore);
        $reminderType = "pre_expiry_{$daysBefore}";

        $contrats = Contrat::where('statut', 'active')
            ->whereDate('date_fin', $targetDate)
            ->with(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire'])
            ->get();

        foreach ($contrats as $contrat) {
            $alreadySent = Alerte::where('contrat_id', $contrat->id)
                ->where('type', $reminderType)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $notifications->notifyContractReminder($contrat, $reminderType);

            Alerte::create([
                'contrat_id' => $contrat->id,
                'date_alerte' => $today,
                'type' => $reminderType,
                'envoye' => true,
            ]);
        }
    }

    // "1 rappel supplémentaire 1 à 2 jours après la fin du contrat".
    foreach ([1, 2] as $daysAfter) {
        $postExpiryDate = $today->copy()->subDays($daysAfter);
        $reminderType = 'post_expiry';

        $expiredContrats = Contrat::where('statut', 'expired')
            ->whereDate('date_fin', $postExpiryDate)
            ->with(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire'])
            ->get();

        foreach ($expiredContrats as $contrat) {
            $alreadySent = Alerte::where('contrat_id', $contrat->id)
                ->where('type', $reminderType)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $notifications->notifyContractReminder($contrat, $reminderType);

            Alerte::create([
                'contrat_id' => $contrat->id,
                'date_alerte' => $today,
                'type' => $reminderType,
                'envoye' => true,
            ]);
        }
    }
})->dailyAt('08:00')->name('alertes:send')->withoutOverlapping();