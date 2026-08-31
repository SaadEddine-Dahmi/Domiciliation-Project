<?php
// app/Services/Representants/RepresentantService.php
// Manages polymorphic legal representative records.

namespace App\Services\Representants;

use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RepresentantService
{
    public function tenantEntreprise(int $tenantId, int $entrepriseId): Entreprise
    {
        return Entreprise::where('domiciliataire_id', $tenantId)->findOrFail($entrepriseId);
    }

    public function create(Model $owner, array $data): Representant
    {
        return DB::transaction(fn() => $owner->representant()->create($data));
    }

    public function upsert(Model $owner, array $data): array
    {
        return DB::transaction(function () use ($owner, $data) {
            $representant = $owner->representant;

            if (!$representant) {
                $representant = $owner->representant()->create($data);

                return [$representant, true];
            }

            $representant->update($data);

            return [$representant->fresh(), false];
        });
    }

    public function delete(Model $owner): void
    {
        DB::transaction(fn() => $owner->representant()->firstOrFail()->delete());
    }

    public function needsRequiredIdentity(Model $owner, array $data): bool
    {
        return !$owner->representant && (empty($data['nom']) || empty($data['cin']));
    }
}
