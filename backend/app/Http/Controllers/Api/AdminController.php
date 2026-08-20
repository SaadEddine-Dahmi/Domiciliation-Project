<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // Lists all domiciliataire accounts with client/contract counts.
    // Admin-only; deliberately excludes sensitive fields (CIN, password,
    // documents) even though it returns cross-tenant data.
    public function domiciliataires()
    {
        $user = auth()->user();

        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $rows = User::where('role', 'domiciliataire')
            ->withCount(['entreprises', 'contrats'])
            ->with([
                'entreprises:id,domiciliataire_id,raison_sociale,statut,ville',
            ])
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'email' => $u->email,
                'telephone' => $u->telephone,
                // Free from the User model's $appends — lets the admin
                // list show a real photo or the same initials fallback
                // used everywhere else in the app.
                'photo_url' => $u->photo_url,
                'initials' => $u->initials,
                'entreprises_count' => $u->entreprises_count,
                'contrats_count' => $u->contrats_count,
                'entreprises' => $u->entreprises->map(fn($e) => [
                    'id' => $e->id,
                    'raison_sociale' => $e->raison_sociale,
                    'statut' => $e->statut,
                    'ville' => $e->ville,
                ]),
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }
}