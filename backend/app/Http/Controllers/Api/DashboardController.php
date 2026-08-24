<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
                    'total_domiciliataires' => User::where('role', 'domiciliataire')->count(),
                    'total_clients' => Entreprise::count(),
                    'total_contrats' => $this->visibleContrats()->count(),
                    'contrats_actifs' => $this->visibleContrats()->where('statut', 'active')->count(),
                    'contrats_draft' => $this->visibleContrats()->where('statut', 'draft')->count(),
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

            $caMensuel = $tenantContrats()
                ->where('statut', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('prix_total');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clients' => Entreprise::where('domiciliataire_id', $tenantId)->count(),
                    'total_contrats' => $tenantContrats()->count(),
                    'contrats_actifs' => $tenantContrats()->where('statut', 'active')->count(),
                    'contrats_draft' => $tenantContrats()->where('statut', 'draft')->count(),
                    'total_documents' => 0,
                    'ca_mensuel' => number_format((float) $caMensuel, 2, '.', ''),
                    'role' => 'domiciliataire',
                ],
            ]);
        }

        $entreprise = Entreprise::where('client_user_id', $user->id)
            ->with([
                'domiciliataire:id,nom,prenom,email,telephone',
                'contrats' => fn($q) => $this->applyVisibleContratScope($q)->latest()->limit(1),
            ])
            ->first();

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
}
