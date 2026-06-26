<?php
// app/Http/Controllers/Api/ClientController.php
//
// Manages the client (Entreprise-based) resource for the domiciliataire.
//
// FIX: Added 'representant' to eager loads in index() and show().
// Without this, the contract wizard received Entreprise objects with
// representant === undefined, so gerantNom/CIN/tel/email/adressePerso
// could never be auto-filled from the database.
//
// Routes (auth:sanctum):
//   GET    /api/clients          → index
//   GET    /api/clients/{id}     → show
//   PUT    /api/clients/{id}     → update
//   PUT    /api/clients/{id}/password → updatePassword

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    /**
     * GET /api/clients
     *
     * Returns all entreprises (clients) belonging to the authenticated
     * domiciliataire, with their representant, linked user account,
     * and documents eager-loaded.
     *
     * ✅ FIX: 'representant' added to with() so the contract wizard
     * can auto-fill gerant fields from the database.
     */
    public function index()
    {
        $tenantId = auth()->id();

        $rows = Entreprise::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'representant',                                        // ✅ ADDED — hasOne, needed by contract wizard
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
     * GET /api/clients/{id}
     *
     * Returns a single entreprise by ID, tenant-scoped.
     *
     * ✅ FIX: 'representant' added to with() for consistency with index().
     */
    public function show(int $id)
    {
        $row = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->with([
                'representant',                                        // ✅ ADDED
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
     * PUT /api/clients/{id}
     *
     * Update entreprise fields and optionally the linked client user account.
     * The representant is managed separately via RepresentantController.
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

            // Optional nested client user update
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

        // Update linked client user account if present
        if ($entreprise->client_user_id && isset($data['client_user'])) {
            $user = User::find($entreprise->client_user_id);

            if ($user) {
                $newEmail = $data['client_user']['email'] ?? $user->email;

                // Guard against email collision
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
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role',
                'documents.documentType:id,name,is_required,has_expiration',
            ]),
        ]);
    }

    /**
     * PUT /api/clients/{id}/password
     *
     * Reset the password for the linked client user account.
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