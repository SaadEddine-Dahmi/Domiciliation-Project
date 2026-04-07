<?php

namespace App\Observers;

use App\Models\Entreprise;
use App\Models\EntrepriseHistory;
use Illuminate\Support\Facades\Auth;

class EntrepriseObserver
{
    /**
     * Fires BEFORE update.
     * Saves a snapshot of the OLD values before they are overwritten.
     */
    public function updating(Entreprise $entreprise): void
    {
        $dirty = $entreprise->getDirty();

        // Skip if nothing actually changed
        if (empty($dirty)) return;

        EntrepriseHistory::create([
            'entreprise_id'  => $entreprise->id,
            'changed_by'     => Auth::id(),
            'data'           => $entreprise->getOriginal(), // old values
            'changed_fields' => array_keys($dirty),         // what changed
            'action'         => 'update',
        ]);
    }

    /**
     * Fires BEFORE delete.
     * Saves a final snapshot of the full record before it's removed.
     */
    public function deleting(Entreprise $entreprise): void
    {
        EntrepriseHistory::create([
            'entreprise_id'  => $entreprise->id,
            'changed_by'     => Auth::id(),
            'data'           => $entreprise->toArray(),
            'changed_fields' => null,
            'action'         => 'delete',
        ]);
    }
}
