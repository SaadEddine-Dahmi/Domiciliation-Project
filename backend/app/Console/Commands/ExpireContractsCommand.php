<?php
// app/Console/Commands/ExpireContractsCommand.php
//
// Single source of truth for automatic contract expiration.
//
// Scans every 'active' contract whose date_fin has passed, transitions it
// to 'expired' through the model's state-machine method, and notifies the
// domiciliataire so an expired contract doesn't go unnoticed — it also
// becomes eligible for renewal once expired (see Contrat::isRenewable()).
//
// Runs once a day via the scheduler (routes/console.php). Expiration is a
// time-based fact that must happen even if nobody opens the application
// that day, so it cannot depend on an incoming HTTP request — a scheduled
// command is the correct mechanism, not a check inside a controller.

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contrat;
use Illuminate\Console\Command;

class ExpireContractsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'contracts:expire-check';

    /**
     * The console command description.
     */
    protected $description = 'Transition active contracts past their date_fin to expired and notify the domiciliataire';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = Contrat::where('statut', 'active')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', now()->toDateString())
            ->with('entreprise:id,raison_sociale')
            ->get();

        foreach ($expired as $contrat) {
            // Go through the model's state-machine method rather than a
            // raw update() so any future side effects added to expire()
            // (e.g. clearing next_alert_date) apply consistently here too.
            $contrat->expire();

            AppNotification::create([
                'user_id' => $contrat->domiciliataire_id,
                'contrat_id' => $contrat->id,
                'message' => sprintf(
                    '⚠️ Le contrat de %s a expiré le %s.',
                    $contrat->entreprise?->raison_sociale ?? 'un client',
                    $contrat->date_fin->format('d/m/Y')
                ),
                'is_read' => false,
            ]);

            $this->line("Contrat #{$contrat->id} -> expired");
        }

        $this->info("{$expired->count()} contrat(s) passé(s) en 'expired'.");

        return self::SUCCESS;
    }
}