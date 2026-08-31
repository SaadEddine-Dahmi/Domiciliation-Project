<?php
// app/Observers/ContratObserver.php
// Dispatches contract activation notifications.

namespace App\Observers;

use App\Models\Contrat;
use App\Services\NotificationService;

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
