<?php
// app/Services/AccountHistoryService.php
// Builds tenant-scoped audit history feeds and export payloads.

namespace App\Services;

use App\Models\Contrat;
use App\Models\ContratHistory;
use App\Models\Entreprise;
use App\Models\EntrepriseHistory;
use App\Models\Representant;
use App\Models\RepresentantHistory;
use Illuminate\Support\Collection;

class AccountHistoryService
{
    public function feed(int $tenantId, ?int $limit = null): Collection
    {
        $entries = $this->collect($tenantId);

        return $limit ? $entries->take($limit)->values() : $entries->values();
    }

    private function collect(int $tenantId): Collection
    {
        return $this->entreprises($tenantId)
            ->concat($this->representants($tenantId))
            ->concat($this->contrats($tenantId))
            ->sortByDesc('created_at')
            ->values();
    }

    private function entreprises(int $tenantId): Collection
    {
        $rows = EntrepriseHistory::where('domiciliataire_id', $tenantId)
            ->with(['changedBy:id,nom,prenom', 'entreprise:id,raison_sociale'])
            ->orderBy('entreprise_id')
            ->orderBy('created_at')
            ->get();

        $liveById = Entreprise::whereIn('id', $rows->pluck('entreprise_id')->unique())
            ->where('domiciliataire_id', $tenantId)
            ->get()
            ->keyBy('id');

        return $this->formatGrouped($rows, 'entreprise_id', $liveById, 'entreprise', function ($history, $id) {
            return $history->entreprise?->raison_sociale ?? "Entreprise #{$id}";
        });
    }

    private function representants(int $tenantId): Collection
    {
        $rows = RepresentantHistory::where('domiciliataire_id', $tenantId)
            ->with(['changedBy:id,nom,prenom', 'representant:id,nom,prenom,representable_id,representable_type'])
            ->orderBy('representant_id')
            ->orderBy('created_at')
            ->get();

        $tenantEntrepriseIds = Entreprise::where('domiciliataire_id', $tenantId)->pluck('id');
        $liveById = Representant::whereIn('id', $rows->pluck('representant_id')->unique())
            ->where('representable_type', Entreprise::class)
            ->whereIn('representable_id', $tenantEntrepriseIds)
            ->get()
            ->keyBy('id');

        return $this->formatGrouped($rows, 'representant_id', $liveById, 'representant', function ($history, $id) {
            return $history->representant
                ? trim("{$history->representant->prenom} {$history->representant->nom}")
                : "Représentant #{$id}";
        });
    }

    private function contrats(int $tenantId): Collection
    {
        $rows = ContratHistory::whereHas('contrat', fn($query) => $query->where('domiciliataire_id', $tenantId))
            ->with(['changedBy:id,nom,prenom', 'contrat:id,titre_contrat'])
            ->orderBy('contrat_id')
            ->orderBy('created_at')
            ->get();

        $liveById = Contrat::whereIn('id', $rows->pluck('contrat_id')->unique())
            ->where('domiciliataire_id', $tenantId)
            ->get()
            ->keyBy('id');

        return $this->formatGrouped($rows, 'contrat_id', $liveById, 'contrat', function ($history, $id) {
            return $history->contrat?->titre_contrat ?? "Contrat #{$id}";
        });
    }

    private function formatGrouped(Collection $rows, string $groupKey, Collection $liveById, string $type, callable $label): Collection
    {
        return $rows
            ->groupBy($groupKey)
            ->flatMap(function (Collection $group, $entityId) use ($groupKey, $liveById, $type, $label) {
                $group = $group->values();

                return $group->map(function ($history, int $index) use ($group, $groupKey, $liveById, $entityId, $type, $label) {
                    $before = $this->safeArray($history->old_values ?? $history->data ?? []);
                    $next = $group->get($index + 1);
                    $after = $next
                        ? $this->safeArray($next->new_values ?? $next->data ?? [])
                        : $this->safeArray(optional($liveById->get($entityId))->toArray());
                    $fields = $this->changedFields($history->changed_fields, $before, $after);

                    return [
                        'id' => $type . '-' . $history->id,
                        'type' => $type,
                        'action' => $history->action,
                        'label' => $label($history, $entityId),
                        'changed_fields' => $fields,
                        'diff' => $this->diff($fields, $before, $after),
                        'changed_by' => $history->changedBy ? trim("{$history->changedBy->prenom} {$history->changedBy->nom}") : null,
                        'created_at' => $history->created_at,
                    ];
                });
            })
            ->values();
    }

    private function diff(array $fields, array $before, ?array $after): array
    {
        return collect($fields)
            ->map(fn($field) => [
                'field' => $field,
                'before' => $before[$field] ?? null,
                'after' => $after[$field] ?? null,
            ])
            ->values()
            ->all();
    }

    private function safeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function changedFields(mixed $value, array $before, array $after): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn($field) => is_string($field) && $field !== ''));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->changedFields($decoded, $before, $after);
            }
        }

        return array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
    }
}
