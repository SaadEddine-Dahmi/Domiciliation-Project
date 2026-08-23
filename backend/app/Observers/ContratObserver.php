<?php

namespace App\Observers;

use App\Models\Contrat;
use App\Services\NotificationService;

/**
 * Watches Contrat for the "legalized" transition: draft -> active. This
 * is what happens when the domiciliataire uploads the client-signed PDF
 * via POST /api/contrats/{id}/activate (see contrats.vue -> submitActivate()).
 *
 * Using an observer instead of hardcoding this inside
 * ContratController::activate() means the notification fires no matter
 * which code path flips the status — it's always accurate to what
 * actually happened in the database, not to one specific call site.
 *
 * CAVEAT: this only fires on Eloquent model saves ($contrat->update(...)
 * or $contrat->save()). If activation is ever done via a raw query
 * builder update (Contrat::where(...)->update([...])), Eloquent events
 * don't fire and this observer won't trigger — switch that call site to
 * load the model first if that's the case.
 */
class ContratObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function updated(Contrat $contrat): void
    {
        $becameActive = $contrat->isDirty('statut')
            && $contrat->statut === 'active'
            && $contrat->getOriginal('statut') !== 'active';

        if ($becameActive) {
            $this->notifications->notifyContractLegalized($contrat);
        }
    }
}