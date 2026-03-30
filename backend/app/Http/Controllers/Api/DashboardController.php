<?php
// ============================================================
// app/Http/Controllers/Api/DashboardController.php
// Stats adaptées selon le rôle :
//   admin          → stats globales (tous domiciliataires)
//   domiciliataire → ses propres stats
//   client         → son contrat et ses infos
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;

class DashboardController extends Controller
{
    public function stats()
    {
        $user = auth()->user();
        $role = $user->role;

        // ── Admin : stats globales ────────────────────────
        if ($role === 'admin') {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_domiciliataires' => User::where('role', 'domiciliataire')->count(),
                    'total_clients' => Entreprise::count(),
                    'total_contrats' => Contrat::count(),
                    'contrats_actifs' => Contrat::where('statut', 'active')->count(),
                    'contrats_draft' => Contrat::where('statut', 'draft')->count(),
                    'total_documents' => 0,
                    'ca_mensuel' => '0.00',
                    'role' => 'admin',
                ],
            ]);
        }

        // ── Domiciliataire : ses propres stats ────────────
        if ($role === 'domiciliataire') {
            $tenantId = $user->id;

            $caMensuel = Contrat::where('domiciliataire_id', $tenantId)
                ->where('statut', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('prix_total');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clients' => Entreprise::where('domiciliataire_id', $tenantId)->count(),
                    'total_contrats' => Contrat::where('domiciliataire_id', $tenantId)->count(),
                    'contrats_actifs' => Contrat::where('domiciliataire_id', $tenantId)->where('statut', 'active')->count(),
                    'contrats_draft' => Contrat::where('domiciliataire_id', $tenantId)->where('statut', 'draft')->count(),
                    'total_documents' => 0,
                    'ca_mensuel' => number_format((float) $caMensuel, 2, '.', ''),
                    'role' => 'domiciliataire',
                ],
            ]);
        }

        // ── Client : son contrat et son domiciliataire ────
        // Trouver l'entreprise liée au client connecté
        $entreprise = Entreprise::where('client_user_id', $user->id)
            ->with(['domiciliataire:id,nom,prenom,email,telephone', 'contrats' => fn($q) => $q->latest()->limit(1)])
            ->first();

        $contrat = $entreprise?->contrats->first();

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
                ] : null,
                'role' => 'client',
            ],
        ]);
    }
}