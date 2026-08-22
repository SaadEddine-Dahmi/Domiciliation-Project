<?php
// app/Http/Controllers/Api/ClientController.php
//
// Manages the client (Entreprise + linked User) resource for the
// domiciliataire.
//
// Password policy for client portal accounts:
//   - The domiciliataire NEVER types a password for a client. store()
//     always generates one server-side via generatePassword().
//   - The plaintext password is returned ONCE, in the store()/
//     resetPassword() JSON response, so the domiciliataire can copy it
//     and hand it to the client. It is never logged or stored anywhere
//     except as a bcrypt hash on the users row — it cannot be recovered
//     afterwards, only regenerated.
//   - must_change_password is set to true whenever a password is
//     system-generated. AuthController::login() returns this flag on the
//     user object so the frontend can prompt the client to set their own
//     password after login. It's cleared by AuthController::changePassword().
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
     * transaction. A password is always generated — the domiciliataire
     * cannot set one manually. The plaintext value is included only in
     * this response's `generated_password` key; the frontend must show
     * it once and never persist it client-side beyond that.
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

            // Client portal account fields — no password accepted here.
            'client_nom' => ['required', 'string', 'max:20'],
            'client_prenom' => ['nullable', 'string', 'max:20'],
            'client_email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'client_telephone' => ['nullable', 'string', 'max:13'],
        ]);

        $plainPassword = $this->generatePassword();

        $entreprise = DB::transaction(function () use ($data, $plainPassword) {
            $clientUser = User::create([
                'nom' => $data['client_nom'],
                'prenom' => $data['client_prenom'] ?? null,
                'email' => strtolower(trim($data['client_email'])),
                'password' => Hash::make($plainPassword),
                'telephone' => $data['client_telephone'] ?? null,
                'role' => 'client',
                'status' => 'active',
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
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $entreprise->fresh([
                'representant',
                'clientUser:id,nom,prenom,email,telephone,role,must_change_password',
            ]),
            'generated_password' => $plainPassword,
        ], 201);
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
     *
     * For a client who forgot their password. Generates a brand-new
     * random password the same way store() does, overwrites the hash,
     * and flips must_change_password back to true so the client is
     * prompted to pick their own after logging in with it.
     *
     * The old password can never be recovered — it only ever existed as
     * a bcrypt hash — so "reset" means "issue a new one", not "restore
     * the original plaintext".
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