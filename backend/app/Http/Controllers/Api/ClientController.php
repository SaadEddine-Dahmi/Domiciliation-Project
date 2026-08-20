<?php
// app/Http/Controllers/Api/ClientController.php
//
// Manages the client (Entreprise-based) resource for the domiciliataire.
//
// The representative is attached separately via RepresentantController,
// so store() only needs the entreprise fields. 'statut' is deliberately
// excluded from store() and update() — it has its own dedicated endpoint
// (toggleStatus) so it can never be overwritten by an unrelated profile edit.
//
// Routes (auth:sanctum):
//   GET    /api/clients                  → index
//   POST   /api/clients                  → store
//   GET    /api/clients/{id}             → show
//   PUT    /api/clients/{id}             → update
//   PUT    /api/clients/{id}/password    → updatePassword
//   PATCH  /api/clients/{id}/status      → toggleStatus
//   GET    /api/clients/{id}/history     → history

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
     * Returns all entreprises (clients) belonging to the authenticated
     * domiciliataire, with representant, linked user account, and
     * documents eager-loaded.
     */
    public function index()
    {
        $tenantId = auth()->id();

        $rows = Entreprise::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'representant',
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
     * POST /api/clients
     * Creates a new entreprise (client) for the authenticated domiciliataire.
     * The representant is created afterwards by a separate call to
     * RepresentantController::store(). Tenant ID is always forced from the
     * authenticated user, never trusted from the request body.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
            'capital' => ['nullable', 'numeric'],
            'date_creation' => ['nullable', 'date'],
        ]);

        $entreprise = Entreprise::create([
            ...$data,
            'domiciliataire_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $entreprise->fresh([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role',
            ]),
        ], 201);
    }

    /**
     * GET /api/clients/{id}
     * Returns a single entreprise by ID, tenant-scoped.
     */
    public function show(int $id)
    {
        $row = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->with([
                'representant',
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
     * Updates entreprise fields and optionally the linked client user account.
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
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role',
                'documents.documentType:id,name,is_required,has_expiration',
            ]),
        ]);
    }

    /**
     * PATCH /api/clients/{id}/status
     * Toggles the client between 'actif' and 'inactif'. An 'inactif'
     * client's linked user account is refused login by AuthController::login().
     * This is the only place statut is written.
     */
    public function toggleStatus(Request $request, int $id)
    {
        $entreprise = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->findOrFail($id);

        $data = $request->validate([
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $entreprise->update(['statut' => $data['statut']]);

        return response()->json([
            'success' => true,
            'data' => $entreprise->fresh(),
        ]);
    }

    /**
     * PUT /api/clients/{id}/password
     * Resets the password for the linked client user account.
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

    /**
     * GET /api/clients/{id}/history
     * Returns the full audit trail for this client, newest first.
     */
    public function history(int $id)
    {
        $entreprise = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entreprise->history()->with('changedBy:id,nom,prenom')->get(),
        ]);
    }
}