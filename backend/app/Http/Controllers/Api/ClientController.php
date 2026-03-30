<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    /**
     * Clients list (Entreprise-based), tenant-safe.
     */
    public function index()
    {
        $tenantId = auth()->id();

        $rows = Entreprise::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'clientUser:id,nom,prenom,email,telephone,role',
                'documents.documentType:id,name,is_required,has_expiration',
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Show one client (entreprise).
     */
    public function show(int $id)
    {
        $row = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->with([
                'clientUser:id,nom,prenom,email,telephone,role',
                'documents.documentType:id,name,is_required,has_expiration',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    /**
     * Update entreprise info + optional linked client user data.
     */
    public function update(Request $request, int $id)
    {
        $entreprise = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->findOrFail($id);

        $data = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
            'capital' => ['nullable', 'numeric'],
            'date_creation' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'max:50'],

            'client_user.nom' => ['nullable', 'string', 'max:20'],
            'client_user.prenom' => ['nullable', 'string', 'max:20'],
            'client_user.email' => ['nullable', 'email', 'max:50'],
            'client_user.telephone' => ['nullable', 'string', 'max:13'],
        ]);

        $entreprise->update([
            'raison_sociale' => $data['raison_sociale'],
            'forme_juridique' => $data['forme_juridique'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'ville' => $data['ville'] ?? null,
            'pays' => $data['pays'] ?? null,
            'capital' => $data['capital'] ?? null,
            'date_creation' => $data['date_creation'] ?? null,
            'statut' => $data['statut'] ?? null,
        ]);

        if ($entreprise->client_user_id && isset($data['client_user'])) {
            $user = User::find($entreprise->client_user_id);

            if ($user) {
                $newEmail = $data['client_user']['email'] ?? $user->email;

                if (
                    $newEmail !== $user->email &&
                    User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email déjà utilisé.',
                    ], 422);
                }

                $user->update([
                    'nom' => $data['client_user']['nom'] ?? $user->nom,
                    'prenom' => $data['client_user']['prenom'] ?? $user->prenom,
                    'email' => $newEmail,
                    'telephone' => $data['client_user']['telephone'] ?? $user->telephone,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $entreprise->fresh([
                'clientUser:id,nom,prenom,email,telephone,role',
                'documents.documentType:id,name,is_required,has_expiration',
            ]),
        ]);
    }

    /**
     * Update linked client account password.
     */
    public function updatePassword(Request $request, int $id)
    {
        $entreprise = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->findOrFail($id);

        if (!$entreprise->client_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur client lié.',
            ], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($entreprise->client_user_id);
        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
        ]);
    }
}