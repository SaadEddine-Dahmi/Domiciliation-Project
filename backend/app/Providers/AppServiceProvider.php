<?php
// app/Providers/AppServiceProvider.php
// Registers application observers and API rate limiters.

namespace App\Providers;

use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Observers\ContratObserver;
use App\Observers\DocumentObserver;
use App\Observers\EntrepriseObserver;
use App\Observers\RepresentantObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Entreprise::observe(EntrepriseObserver::class);
        Representant::observe(RepresentantObserver::class);
        Contrat::observe(ContratObserver::class);
        Document::observe(DocumentObserver::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de tentatives. Veuillez réessayer dans une minute.',
                    ], 429);
                });
        });

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('heavy', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?? $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Limite de requêtes atteinte. Veuillez patienter.',
                    ], 429);
                });
        });
    }
}
