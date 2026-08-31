<?php
// app/Observers/EntrepriseObserver.php
// Records company history snapshots.

namespace App\Observers;

use App\Models\Entreprise;
use App\Models\EntrepriseHistory;
use Illuminate\Support\Facades\Auth;

class EntrepriseObserver
{
    public function updating(Entreprise $entreprise): void
    {
        $dirty = $entreprise->getDirty();
        if (empty($dirty)) {
            return;
        }

        EntrepriseHistory::create([
            'entreprise_id' => $entreprise->id,
            'changed_by' => Auth::id(),
            'data' => $entreprise->getOriginal(),
            'changed_fields' => array_keys($dirty),
            'action' => 'update',
        ]);
    }

    public function deleting(Entreprise $entreprise): void
    {
        EntrepriseHistory::create([
            'entreprise_id' => $entreprise->id,
            'changed_by' => Auth::id(),
            'data' => $entreprise->toArray(),
            'changed_fields' => null,
            'action' => 'delete',
        ]);
    }
}
