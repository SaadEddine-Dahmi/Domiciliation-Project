<?php
// ============================================================
// app/Http/Controllers/Api/AdminController.php
// Endpoints réservés au rôle admin
// Lecture seule — pas de données sensibles (CIN, mdp, docs)
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Liste tous les domiciliataires avec leurs stats
     * Sans données sensibles
     */
    public function domiciliataires()
    {
        $user = auth()->user();

        // Seulement accessible par l'admin
        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $rows = User::where('role', 'domiciliataire')
            ->withCount(['entreprises', 'contrats'])
            ->with([
                // Noms entreprises uniquement — pas d'infos sensibles
                'entreprises:id,domiciliataire_id,raison_sociale,statut,ville',
            ])
            ->get()
            ->map(fn($u) => [
                'id'                => $u->id,
                'nom'               => $u->nom,
                'prenom'            => $u->prenom,
                'email'             => $u->email,
                'telephone'         => $u->telephone,
                'entreprises_count' => $u->entreprises_count,
                'contrats_count'    => $u->contrats_count,
                'entreprises'       => $u->entreprises->map(fn($e) => [
                    'id'             => $e->id,
                    'raison_sociale' => $e->raison_sociale,
                    'statut'         => $e->statut,
                    'ville'          => $e->ville,
                ]),
                // PAS de : CIN, mot de passe, documents, données personnelles
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
