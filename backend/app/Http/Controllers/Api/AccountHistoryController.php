<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\ContratHistory;
use App\Models\Entreprise;
use App\Models\EntrepriseHistory;
use App\Models\Representant;
use App\Models\RepresentantHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountHistoryController extends Controller
{
    /**
     * GET /api/account/history
     *
     * Combined, newest-first feed of every audit trail entry
     * (entreprises, representants, contrats) owned by the authenticated
     * domiciliataire. Each entry now carries a `diff` array — one row
     * per changed field, with its value immediately before and
     * immediately after this particular change — instead of just the
     * bare list of field names.
     */
    public function index(Request $request)
    {
        $limit = min((int) $request->get('limit', 20), 200);

        $entries = $this->collectHistory(auth()->id());

        return response()->json([
            'success' => true,
            'data' => $entries->take($limit)->values(),
        ]);
    }

    /**
     * GET /api/account/history/export?format=json|html
     * Full, unlimited archive download — same diff-enriched entries as
     * index(), just not capped.
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'json');
        abort_unless(in_array($format, ['json', 'html'], true), 422, 'Format invalide.');

        $tenantId = auth()->id();
        $entries = $this->collectHistory($tenantId);
        $filename = 'historique-modifications-' . now()->format('Y-m-d') . '.' . $format;

        if ($format === 'json') {
            return response()->json([
                'generated_at' => now()->toIso8601String(),
                'domiciliataire_id' => $tenantId,
                'total' => $entries->count(),
                'entries' => $entries->values(),
            ], 200, [
                'Content-Disposition' => "attachment; filename={$filename}",
            ]);
        }

        $html = view('exports.account-history', [
            'entries' => $entries,
            'generatedAt' => now(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    /**
     * Merges all three audit trails into one newest-first collection,
     * each entry enriched with a `diff`: [{ field, before, after }] for
     * every field named in changed_fields.
     */
    private function collectHistory(int $tenantId): Collection
    {
        return $this->collectEntrepriseHistory($tenantId)
            ->concat($this->collectRepresentantHistory($tenantId))
            ->concat($this->collectContratHistory($tenantId))
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Given a "before" snapshot and an "after" snapshot (either the next
     * history row's `data`, or the live record for the most recent
     * change), returns one { field, before, after } row per entry in
     * $changedFields. Missing values (e.g. the record was later deleted,
     * so there's no live "after") come back as null rather than erroring.
     */
    private function buildDiff(?array $changedFields, array $before, ?array $after): array
    {
        if (!$changedFields) {
            return [];
        }

        return collect($changedFields)
            ->map(fn($field) => [
                'field' => $field,
                'before' => $before[$field] ?? null,
                'after' => $after[$field] ?? null,
            ])
            ->values()
            ->all();
    }

    private function collectEntrepriseHistory(int $tenantId): Collection
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

        return $rows
            ->groupBy('entreprise_id')
            ->flatMap(function (Collection $group, $entrepriseId) use ($liveById) {
                $group = $group->values();

                return $group->map(function (EntrepriseHistory $h, int $i) use ($group, $liveById, $entrepriseId) {
                    $before = $h->data ?? [];
                    $next = $group->get($i + 1);
                    $after = $next ? ($next->data ?? []) : optional($liveById->get($entrepriseId))->toArray();

                    return [
                        'id' => 'entreprise-' . $h->id,
                        'type' => 'entreprise',
                        'action' => $h->action,
                        'label' => $h->entreprise?->raison_sociale ?? "Entreprise #{$entrepriseId}",
                        'changed_fields' => $h->changed_fields,
                        'diff' => $this->buildDiff($h->changed_fields, $before, $after),
                        'changed_by' => $h->changedBy ? trim("{$h->changedBy->prenom} {$h->changedBy->nom}") : null,
                        'created_at' => $h->created_at,
                    ];
                });
            })
            ->values();
    }

    private function collectRepresentantHistory(int $tenantId): Collection
    {
        $rows = RepresentantHistory::where('domiciliataire_id', $tenantId)
            ->with(['changedBy:id,nom,prenom', 'representant:id,nom,prenom,entreprise_id'])
            ->orderBy('representant_id')
            ->orderBy('created_at')
            ->get();

        $liveById = Representant::whereIn('id', $rows->pluck('representant_id')->unique())
            ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $tenantId))
            ->get()
            ->keyBy('id');

        return $rows
            ->groupBy('representant_id')
            ->flatMap(function (Collection $group, $representantId) use ($liveById) {
                $group = $group->values();

                return $group->map(function (RepresentantHistory $h, int $i) use ($group, $liveById, $representantId) {
                    $before = $h->data ?? [];
                    $next = $group->get($i + 1);
                    $after = $next ? ($next->data ?? []) : optional($liveById->get($representantId))->toArray();

                    $label = $h->representant
                        ? trim("{$h->representant->prenom} {$h->representant->nom}")
                        : "Représentant #{$representantId}";

                    return [
                        'id' => 'representant-' . $h->id,
                        'type' => 'representant',
                        'action' => $h->action,
                        'label' => $label,
                        'changed_fields' => $h->changed_fields,
                        'diff' => $this->buildDiff($h->changed_fields, $before, $after),
                        'changed_by' => $h->changedBy ? trim("{$h->changedBy->prenom} {$h->changedBy->nom}") : null,
                        'created_at' => $h->created_at,
                    ];
                });
            })
            ->values();
    }

    private function collectContratHistory(int $tenantId): Collection
    {
        $rows = ContratHistory::whereHas('contrat', fn($q) => $q->where('domiciliataire_id', $tenantId))
            ->with(['changedBy:id,nom,prenom', 'contrat:id,titre_contrat'])
            ->orderBy('contrat_id')
            ->orderBy('created_at')
            ->get();

        $liveById = Contrat::whereIn('id', $rows->pluck('contrat_id')->unique())
            ->where('domiciliataire_id', $tenantId)
            ->get()
            ->keyBy('id');

        return $rows
            ->groupBy('contrat_id')
            ->flatMap(function (Collection $group, $contratId) use ($liveById) {
                $group = $group->values();

                return $group->map(function (ContratHistory $h, int $i) use ($group, $liveById, $contratId) {
                    $before = $h->data ?? [];
                    $next = $group->get($i + 1);
                    $after = $next ? ($next->data ?? []) : optional($liveById->get($contratId))->toArray();

                    return [
                        'id' => 'contrat-' . $h->id,
                        'type' => 'contrat',
                        'action' => $h->action,
                        'label' => $h->contrat?->titre_contrat ?? "Contrat #{$contratId}",
                        'changed_fields' => $h->changed_fields,
                        'diff' => $this->buildDiff($h->changed_fields, $before, $after),
                        'changed_by' => $h->changedBy ? trim("{$h->changedBy->prenom} {$h->changedBy->nom}") : null,
                        'created_at' => $h->created_at,
                    ];
                });
            })
            ->values();
    }
}