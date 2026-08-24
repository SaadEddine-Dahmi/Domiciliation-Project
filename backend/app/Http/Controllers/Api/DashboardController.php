<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    // Returns role-appropriate stats: platform-wide for admin,
    // tenant-scoped for domiciliataire, and personal contract/company
    // info for client.
    public function stats()
    {
        $user = auth()->user();
        $role = $user->role;

        if ($role === 'admin') {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_domiciliataires' => $this->safeMetric('admin.total_domiciliataires', fn() => User::where('role', 'domiciliataire')->count(), 0),
                    'total_clients' => $this->safeMetric('admin.total_clients', fn() => Entreprise::count(), 0),
                    'total_contrats' => 0,
                    'contrats_actifs' => 0,
                    'contrats_draft' => 0,
                    'total_documents' => 0,
                    'ca_mensuel' => '0.00',
                    'role' => 'admin',
                ],
            ]);
        }

        if ($role === 'domiciliataire') {
            $tenantId = $user->id;

            $tenantContrats = fn(): Builder => $this->visibleContrats()
                ->where('domiciliataire_id', $tenantId);

            $caMensuel = $this->safeMetric('domiciliataire.ca_mensuel', fn() => $tenantContrats()
                    ->where('statut', 'active')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('prix_total'), 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clients' => $this->safeMetric('domiciliataire.total_clients', fn() => Entreprise::where('domiciliataire_id', $tenantId)->count(), 0),
                    'total_contrats' => $this->safeMetric('domiciliataire.total_contrats', fn() => $tenantContrats()->count(), 0),
                    'contrats_actifs' => $this->safeMetric('domiciliataire.contrats_actifs', fn() => $tenantContrats()->where('statut', 'active')->count(), 0),
                    'contrats_draft' => $this->safeMetric('domiciliataire.contrats_draft', fn() => $tenantContrats()->where('statut', 'draft')->count(), 0),
                    'total_documents' => 0,
                    'ca_mensuel' => number_format((float) $caMensuel, 2, '.', ''),
                    'role' => 'domiciliataire',
                ],
            ]);
        }

        $entreprise = $this->safeMetric('client.entreprise', fn() => Entreprise::where('client_user_id', $user->id)
                ->with([
                    'domiciliataire:id,nom,prenom,email,telephone',
                    'contrats' => fn($q) => $this->applyVisibleContratScope($q)->latest()->limit(1),
                ])
                ->first(), null);

        $contrat = $entreprise ? $entreprise->contrats->first() : null;

        return response()->json([
            'success' => true,
            'data' => [
                'entreprise' => $entreprise ? [
                    'id' => $entreprise->id,
                    'raison_sociale' => $entreprise->raison_sociale,
                    'statut' => $entreprise->statut,
                    'ville' => $entreprise->ville,
                ] : null,
                'domiciliataire' => $entreprise?->domiciliataire ? [
                    'nom' => $entreprise->domiciliataire->nom,
                    'prenom' => $entreprise->domiciliataire->prenom,
                    'email' => $entreprise->domiciliataire->email,
                    'telephone' => $entreprise->domiciliataire->telephone,
                ] : null,
                'contrat' => $contrat ? [
                    'id' => $contrat->id,
                    'statut' => $contrat->statut,
                    'date_debut' => $contrat->date_debut?->format('d/m/Y'),
                    'date_fin' => $contrat->date_fin?->format('d/m/Y'),
                    'prix_total' => $contrat->prix_total,
                    'pdf_path' => $contrat->pdf_path,
                    'pdf_url' => $contrat->pdf_path ? asset('storage/' . $contrat->pdf_path) : null,
                    'has_open_renewal' => $contrat?->hasOpenRenewal() ?? false,
                ] : null,
                'role' => 'client',
            ],
        ]);
    }

    private function visibleContrats(): Builder
    {
        return $this->applyVisibleContratScope(Contrat::query());
    }

    private function applyVisibleContratScope($query)
    {
        return $this->contratsHaveArchivedAt()
            ? $query->whereNull('archived_at')
            : $query;
    }

    private function contratsHaveArchivedAt(): bool
    {
        try {
            return Schema::hasColumn('contrats', 'archived_at');
        } catch (\Throwable) {
            return false;
        }
    }

    private function safeMetric(string $key, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric failed', [
                'metric' => $key,
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}
