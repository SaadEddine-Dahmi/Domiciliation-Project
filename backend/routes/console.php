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

    $domiciliataires = User::where('role', 'domiciliataire')->get();

    foreach ($domiciliataires as $user) {
        // Delays configured by this domiciliataire — defaults to
        // reminding 1 month before expiration if nothing is configured.
        $prefs = json_decode($user->notification_preferences ?? '{"delays":[1]}', true);
        $delays = $prefs['delays'] ?? [1];

        foreach ($delays as $delayMonths) {
            $targetDate = $today->copy()->addMonths($delayMonths);

            $contrats = Contrat::where('domiciliataire_id', $user->id)
                ->where('statut', 'active')
                ->whereDate('date_fin', $targetDate)
                ->with('entreprise:id,raison_sociale')
                ->get();

            foreach ($contrats as $contrat) {
                // Skip if a reminder for this contract was already sent
                // today — guards against duplicate notifications if the
                // scheduler fires more than once, or if a contract
                // matches more than one configured delay on the same day.
                $alreadySent = AppNotification::where('user_id', $user->id)
                    ->where('contrat_id', $contrat->id)
                    ->whereDate('created_at', $today)
                    ->whereNull('from_user_id')
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                AppNotification::create([
                    'user_id' => $user->id,
                    'contrat_id' => $contrat->id,
                    'message' => sprintf(
                        '🔔 Rappel : le contrat de %s expire dans %d mois (le %s).',
                        $contrat->entreprise?->raison_sociale ?? 'un client',
                        $delayMonths,
                        $contrat->date_fin->format('d/m/Y')
                    ),
                    'is_read' => false,
                ]);

                Alerte::updateOrCreate(
                    ['contrat_id' => $contrat->id, 'date_alerte' => $today],
                    ['envoye' => true]
                );
            }
        }
    }
})->dailyAt('08:00')->name('alertes:send')->withoutOverlapping();