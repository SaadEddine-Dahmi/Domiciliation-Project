<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Observers\EntrepriseObserver;
use App\Observers\RepresentantObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Automatically track all changes to entreprises and representants
        Entreprise::observe(EntrepriseObserver::class);
        Representant::observe(RepresentantObserver::class);
    }
}
