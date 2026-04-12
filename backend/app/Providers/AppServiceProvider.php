<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Observers\EntrepriseObserver;
use App\Observers\RepresentantObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Observers ──────────────────────────────────────────
        Entreprise::observe(EntrepriseObserver::class);
        Representant::observe(RepresentantObserver::class);

        // ── Rate Limiters ──────────────────────────────────────

        // Auth endpoints: 10 attempts per minute per IP
        // Brute-force and credential stuffing protection
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de tentatives. Veuillez réessayer dans une minute.',
                    ], 429);
                });
        });

        // General API: 120 requests per minute per user (or IP if unauthenticated)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Heavy endpoints (PDF generation, file upload): 20 per minute per user
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
