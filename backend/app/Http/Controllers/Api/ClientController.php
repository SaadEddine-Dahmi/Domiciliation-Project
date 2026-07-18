<?php
// app/Http/Controllers/Api/ClientController.php
//
// Manages the Entreprise (client) resource for the domiciliataire.
//
// Routes (auth:sanctum):
//   GET  /api/clients          → index
//   POST /api/clients          → store   ← NEW: inline client creation from wizard
//   GET  /api/clients/{id}     → show
//   PUT  /api/clients/{id}     → update
//   PUT  /api/clients/{id}/password → updatePassword
//
// WHY store() is needed:
//   The contract wizard step 2 "nouveau client" flow previously called
//   clientsStore.create() which was missing from the Pinia store and had no
//   matching backend endpoint. The wizard was trying to call a method that
//   did not exist, silently failing, and proceeding without a client ID.
//   This left selectedClientId null, saveDraft() errored, and the PDF was
//   never generated with client data.
//
// Representant creation:
//   store() creates the Entreprise and optionally its User account.
//   The representant (gérant: CIN, date_naissance, adresse, etc.) is stored
//   in a separate table. The wizard calls POST /api/entreprises/{id}/representant
//   immediately after store() returns, passing the gérant fields.
//   This two-step approach keeps the representant endpoint reusable for edits.

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
     * Returns all entreprises for the authenticated domiciliataire,
     * with representant, clientUser, and documents eager-loaded.
     *
     * representant must be eager-loaded here so the contract wizard step 2
     * can auto-fill gérant fields (CIN, telephone, email, adresse, date_naissance)
     * when the user selects an existing client.
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

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/clients
     *
     * Create a new entreprise inline from the contract wizard step 2.
     *
     * This endpoint handles the "nouveau client" flow:
     *   1. Create the Entreprise record (domiciliataire-scoped).
     *   2. If client_email and client_password are provided, create a User
     *      account with role = 'client' and link it via client_user_id.
     *
     * The gérant (représentant) is NOT created here. After this endpoint
     * returns, the wizard calls POST /api/entreprises/{id}/representant
     * to store the CIN, date_naissance, adresse, telephone, email.
     * This keeps representant management in one place (RepresentantController).
     *
     * Returns the full entreprise with representant eager-loaded so the wizard
     * can immediately call fillFromClient() with the fresh data.
     */
    public function store(Request $request)
    {
        $tenantId = auth()->id();

        $data = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
            'capital' => ['nullable', 'numeric'],
            'date_creation' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'max:50'],
            // Portal user account — required for client login
            'client_nom' => ['nullable', 'string', 'max:20'],
            'client_prenom' => ['nullable', 'string', 'max:20'],
            'client_email' => ['nullable', 'email', 'max:50', 'unique:users,email'],
            'client_password' => ['nullable', 'string', 'min:8'],
            'client_telephone' => ['nullable', 'string', 'max:13'],
        ]);

        // Create the portal user account if credentials were provided.
        $clientUserId = null;
        if (!empty($data['client_email']) && !empty($data['client_password'])) {
            $clientUser = User::create([
                'nom' => $data['client_nom'] ?? ($data['raison_sociale'] ?? 'Client'),
                'prenom' => $data['client_prenom'] ?? null,
                'email' => $data['client_email'],
                'password' => Hash::make($data['client_password']),
                'telephone' => $data['client_telephone'] ?? null,
                'role' => 'client',
                'status' => 'active',  // client accounts are immediately active
            ]);
            $clientUserId = $clientUser->id;
        }

        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenantId,
            'client_user_id' => $clientUserId,
            'raison_sociale' => $data['raison_sociale'],
            'forme_juridique' => $data['forme_juridique'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'ville' => $data['ville'] ?? null,
            'pays' => $data['pays'] ?? 'Maroc',
            'capital' => $data['capital'] ?? null,
            'date_creation' => $data['date_creation'] ?? null,
            'statut' => $data['statut'] ?? 'actif',
        ]);

        return response()->json([
            'success' => true,
            'data' => $entreprise->load([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role',
            ]),
        ], 201);
    }

    /**
     * GET /api/clients/{id}
     *
     * Single entreprise by ID, tenant-scoped. Same eager-loads as index().
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

        return response()->json(['success' => true, 'data' => $row]);
    }

    /**
     * PUT /api/clients/{id}
     *
     * Update entreprise fields and optionally the linked portal user account.
     * Representant is managed separately via RepresentantController.
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
            $newEmail = $data['client_user']['email'] ?? $user?->email;

            if ($user) {
                if (
                    $newEmail !== $user->email &&
                    User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email déjà utilisé par un autre compte.',
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
     * Reset the password for the linked portal user account.
     */
    public function updatePassword(Request $request, int $id)
    {
        $entreprise = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->findOrFail($id);

        if (!$entreprise->client_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte portal lié à ce client.',
            ], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($entreprise->client_user_id);
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($data['password'])]);

        return response()->json(['success' => true, 'message' => 'Mot de passe mis à jour.']);
    }
}