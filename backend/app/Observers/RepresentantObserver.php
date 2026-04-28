<?php

namespace App\Observers;

use App\Models\Representant;
use App\Models\RepresentantHistory;
use Illuminate\Support\Facades\Auth;

class RepresentantObserver
{
    /**
     * Fires BEFORE update.
     * Saves a snapshot of the OLD values before they are overwritten.
     */
    public function updating(Representant $representant): void
    {
        $dirty = $representant->getDirty();

        if (empty($dirty)) return;

        RepresentantHistory::create([
            'representant_id' => $representant->id,
            'changed_by'      => Auth::id(),
            'data'            => $representant->getOriginal(),
            'changed_fields'  => array_keys($dirty),
            'action'          => 'update',
        ]);
    }

    /**
     * Fires BEFORE delete.
     * Saves a final snapshot before the record is removed.
     */
    public function deleting(Representant $representant): void
    {
        RepresentantHistory::create([
            'representant_id' => $representant->id,
            'changed_by'      => Auth::id(),
            'data'            => $representant->toArray(),
            'changed_fields'  => null,
            'action'          => 'delete',
        ]);
    }
}
