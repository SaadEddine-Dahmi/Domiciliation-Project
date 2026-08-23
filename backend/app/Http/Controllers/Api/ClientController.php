<?php
// app/Http/Controllers/Api/ClientController.php
//
// Manages the client (Entreprise + linked User) resource for the
// domiciliataire.
//
// Password policy for client portal accounts:
//   - store() accepts an OPTIONAL client_password field.
//       - If provided (min 8 chars), it's used as-is.
//       - If omitted or blank, generatePassword() creates a random one.
//   - must_change_password is ALWAYS set to true on creation, regardless
//     of whether the password was typed by the domiciliataire or
//     generated — a manually-typed password is often written down or
//     reused, so the client is still nudged to set their own on login.
//   - The response only includes `generated_password` when the backend
//     actually generated the value (client_password was blank). If the
//     domiciliataire typed their own, they already know it — nothing is
//     echoed back.
//   - resetPassword() always generates (there's no "forgotten password,
//     but let me type a replacement" flow — that would defeat the point
//     of a reset triggered because the client is locked out).
//
// Account status:
//   - New clients are always created with statut = 'actif'. Leaving this
//     unset let it fall back to whatever the database default was,
//     which caused freshly-created clients to be treated as suspended
//     the very first time they tried to log in — statut was never
//     'actif' because nothing ever set it.
//   - toggleStatus() is the only place statut changes after creation,
//     and it's now wired to a button on the client detail page in the
//     frontend.
//
// Routes (auth:sanctum):
//   GET    /api/clients                     → index
//   POST   /api/clients                     → store
//   GET    /api/clients/{id}                → show
//   PUT    /api/clients/{id}                → update
//   PATCH  /api/clients/{id}/status         → toggleStatus
//   PATCH  /api/clients/{id}/reset-password → resetPassword
//   GET    /api/clients/{id}/history        → history

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    /**
     * Generates a random password for a new/reset client account.
     * Excludes visually ambiguous characters (0/O, 1/l/I) so it can be
     * read off a screen and typed correctly by the client.
     */
    private function generatePassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $password;
    }

    public function index()
    {
        $tenantId = auth()->id();

        $rows = Entreprise::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role,must_change_password',
                'documents.documentType:id,name,is_required,has_expiration',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/clients
     *
     * Creates the entreprise AND its linked client portal account in one
     * transaction.
     *
     * client_password is optional:
     *   - If the domiciliataire typed one (or used the "Générer" button
     *     client-side to pre-fill a suggestion), it's used directly.
     *   - If left blank, a random password is generated server-side.
     * The plaintext is only ever included in this response's
     * `generated_password` key, and only for the auto-generated case —
     * it is never logged or stored anywhere except as a bcrypt hash.
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

            // Client portal account fields.
            'client_nom' => ['required', 'string', 'max:20'],
            'client_prenom' => ['nullable', 'string', 'max:20'],
            'client_email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'client_telephone' => ['nullable', 'string', 'max:13'],

            // Optional — if omitted or empty, a password is generated.
            'client_password' => ['nullable', 'string', 'min:8'],
        ]);

        $typedPassword = trim($data['client_password'] ?? '');
        $wasGenerated = $typedPassword === '';
        $plainPassword = $wasGenerated ? $this->generatePassword() : $typedPassword;

        $entreprise = DB::transaction(function () use ($data, $plainPassword) {
            $clientUser = User::create([
                'nom' => $data['client_nom'],
                'prenom' => $data['client_prenom'] ?? null,
                'email' => strtolower(trim($data['client_email'])),
                'password' => Hash::make($plainPassword),
                'telephone' => $data['client_telephone'] ?? null,
                'role' => 'client',
                'status' => 'active',
                // Always true on creation — even a domiciliataire-typed
                // password is still nudged for a client-owned change.
                'must_change_password' => true,
            ]);

            return Entreprise::create([
                'raison_sociale' => $data['raison_sociale'],
                'forme_juridique' => $data['forme_juridique'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'ville' => $data['ville'] ?? null,
                'pays' => $data['pays'] ?? null,
                'date_creation' => $data['date_creation'] ?? null,
                'capital' => $data['capital'] ?? null,
                'domiciliataire_id' => auth()->id(),
                'client_user_id' => $clientUser->id,
                // FIX: explicitly set to 'actif' on creation. This used
                // to be omitted entirely, so a new client's status fell
                // back to the column's DB default (null / not 'actif'),
                // which made isActiveForClient() return false and
                // blocked the client's very first login with a
                // "suspended by your domiciliataire" message nobody
                // actually triggered.
                'statut' => 'actif',
            ]);
        });

        $response = [
            'success' => true,
            'data' => $entreprise->fresh([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role,must_change_password',
            ]),
        ];

        // Only echo the password back when we generated it ourselves —
        // if the domiciliataire typed it, they already have it.
        if ($wasGenerated) {
            $response['generated_password'] = $plainPassword;
        }

        return response()->json($response, 201);
    }

    public function show(int $id)
    {
        $row = Entreprise::query()
            ->where('domiciliataire_id', auth()->id())
            ->with([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role,must_change_password',
                'documents.documentType:id,name,is_required,has_expiration',
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $row]);
    }

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
                'clientUser:id,nom,prenom,email,telephone,role,must_change_password',
                'documents.documentType:id,name,is_required,has_expiration',
            ]),
        ]);
    }

    /**
     * PATCH /api/clients/{id}/status
     * Toggles (or explicitly sets) a client's status. Now surfaced by a
     * button on the client detail page — previously the endpoint
     * existed but nothing in the UI ever called it.
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

        return response()->json(['success' => true, 'data' => $entreprise->fresh()]);
    }

    /**
     * PATCH /api/clients/{id}/reset-password
     * Always generates a new random password (no manual override here —
     * this is the "client is locked out" recovery path, not an edit
     * flow). Flips must_change_password back to true.
     */
    public function resetPassword(int $id)
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

        $user = User::findOrFail($entreprise->client_user_id);
        $plainPassword = $this->generatePassword();

        $user->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé.',
            'generated_password' => $plainPassword,
        ]);
    }

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