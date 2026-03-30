<?php
// ============================================================
// routes/console.php — Cron jobs Laravel 11
// 1. Expiration auto des contrats (minuit)
// 2. Alertes multi-délais selon préférences user (8h)
// ============================================================

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Contrat;
use App\Models\Alerte;
use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Cron 1 : Expiration automatique ───────────────────────
Schedule::call(function () {
    $expired = Contrat::where('statut', 'active')
        ->whereNotNull('date_fin')
        ->where('date_fin', '<', Carbon::today())
        ->with('entreprise:id,raison_sociale')
        ->get();

    foreach ($expired as $contrat) {
        $contrat->update(['statut' => 'expired']);

        AppNotification::create([
            'user_id' => $contrat->domiciliataire_id,
            'contrat_id' => $contrat->id,
            'message' => "⚠️ Le contrat de {$contrat->entreprise->raison_sociale} a expiré le {$contrat->date_fin->format('d/m/Y')}.",
            'is_read' => false,
        ]);
    }
})->daily()->name('contrats:expire')->withoutOverlapping();

// ── Cron 2 : Alertes multi-délais personnalisées ──────────
// Vérifie chaque domiciliataire et ses préférences
// Envoie des alertes pour chaque délai configuré
Schedule::call(function () {
    $today = Carbon::today();

    // Récupérer tous les domiciliataires avec leurs préférences
    $domiciliataires = User::where('role', 'domiciliataire')->get();

    foreach ($domiciliataires as $user) {
        // Lire les délais configurés — défaut: [1]
        $prefs = json_decode($user->notification_preferences ?? '{"delays":[1]}', true);
        $delays = $prefs['delays'] ?? [1];

        foreach ($delays as $delayMonths) {
            // Date cible = aujourd'hui + delayMonths
            $targetDate = $today->copy()->addMonths($delayMonths);

            // Contrats actifs dont date_fin = targetDate
            $contrats = Contrat::where('domiciliataire_id', $user->id)
                ->where('statut', 'active')
                ->whereDate('date_fin', $targetDate)
                ->with('entreprise:id,raison_sociale')
                ->get();

            foreach ($contrats as $contrat) {
                // Éviter les doublons : vérifier si alerte déjà envoyée ce jour
                $alreadySent = AppNotification::where('user_id', $user->id)
                    ->where('contrat_id', $contrat->id)
                    ->whereDate('created_at', $today)
                    ->whereNull('from_user_id')
                    ->exists();

                if ($alreadySent)
                    continue;

                AppNotification::create([
                    'user_id' => $user->id,
                    'contrat_id' => $contrat->id,
                    'message' => "🔔 Rappel : le contrat de {$contrat->entreprise->raison_sociale} expire dans {$delayMonths} mois (le {$contrat->date_fin->format('d/m/Y')}).",
                    'is_read' => false,
                ]);

                // Mettre à jour les alertes DB
                Alerte::updateOrCreate(
                    ['contrat_id' => $contrat->id, 'date_alerte' => $today],
                    ['envoye' => true]
                );
            }
        }
    }
})->dailyAt('08:00')->name('alertes:send')->withoutOverlapping();