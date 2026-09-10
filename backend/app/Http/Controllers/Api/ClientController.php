<?php
// app/Http/Controllers/Api/ClientController.php
// Manages tenant-scoped client companies and their linked portal users.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    use UsesApiPagination;

    private const CLIENT_USER_COLUMNS = 'id,nom,prenom,email,telephone,role,photo_path,must_change_password';

    private function tenantClients(): Builder
    {
        return Entreprise::query()->where('domiciliataire_id', auth()->id());
    }

    private function listRelations(): array
    {
        return [
            'representant:id,representable_id,representable_type,nom,prenom,cin,nationalite,date_naissance,adresse,telephone,email',
            'clientUser:' . self::CLIENT_USER_COLUMNS,
        ];
    }

    private function detailRelations(): array
    {
        return [
            ...$this->listRelations(),
            'documents:id,entreprise_id,document_type_id,file_path,date_expiration,created_at',
            'documents.documentType:id,name,is_required,has_expiration',
        ];
    }

    // Avoids visually ambiguous characters in one-time client passwords.
    private function generatePassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }

    public function index(Request $request)
    {
        $rows = $this->tenantClients()
            ->with($this->listRelations())
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($this->paginatedResponse($rows));
    }

    // Creates the company and linked portal account atomically.
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'domiciliataire') {
            return response()->json(['message' => 'Non autorise.'], 403);
        }

        $data = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
            'capital' => ['nullable', 'numeric'],
            'date_creation' => ['nullable', 'date'],
            'client_nom' => ['required', 'string', 'max:20'],
            'client_prenom' => ['nullable', 'string', 'max:20'],
            'client_email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'client_telephone' => ['nullable', 'string', 'max:30'],
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
                'statut' => 'actif',
            ]);
        });

        $response = [
            'success' => true,
            'data' => $entreprise->fresh($this->listRelations()),
        ];

        if ($wasGenerated) {
            $response['generated_password'] = $plainPassword;
        }

        return response()->json($response, 201);
    }

    public function show(int $id)
    {
        $row = $this->tenantClients()
            ->with($this->detailRelations())
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $row]);
    }

    // Updates company and linked portal identity in one transaction.
    public function update(Request $request, int $id)
    {
        $entreprise = $this->tenantClients()->findOrFail($id);

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
            'client_user.email' => [
                'nullable',
                'email',
                'max:50',
                Rule::unique('users', 'email')->ignore($entreprise->client_user_id),
            ],
            'client_user.telephone' => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($entreprise, $data) {
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
                User::whereKey($entreprise->client_user_id)->update([
                    'nom' => $data['client_user']['nom'] ?? DB::raw('nom'),
                    'prenom' => $data['client_user']['prenom'] ?? DB::raw('prenom'),
                    'email' => isset($data['client_user']['email'])
                        ? strtolower(trim($data['client_user']['email']))
                        : DB::raw('email'),
                    'telephone' => $data['client_user']['telephone'] ?? DB::raw('telephone'),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $entreprise->fresh($this->detailRelations()),
        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $entreprise = $this->tenantClients()->findOrFail($id);

        $data = $request->validate([
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $entreprise->update(['statut' => $data['statut']]);

        return response()->json(['success' => true, 'data' => $entreprise->fresh()]);
    }

    // Generates a one-time password for client account recovery.
    public function resetPassword(int $id)
    {
        if (auth()->user()?->role !== 'domiciliataire') {
            return response()->json([
                'success' => false,
                'message' => 'Acces interdit.',
            ], 403);
        }

        $entreprise = $this->tenantClients()->findOrFail($id);

        if (!$entreprise->client_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur client lie.',
            ], 422);
        }

        $plainPassword = $this->generatePassword();

        User::whereKey($entreprise->client_user_id)->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe reinitialise.',
            'generated_password' => $plainPassword,
        ]);
    }

    // Allows the tenant owner to set a known client password manually.
    public function updatePassword(Request $request, int $id)
    {
        if (auth()->user()?->role !== 'domiciliataire') {
            return response()->json([
                'success' => false,
                'message' => 'Acces interdit.',
            ], 403);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $entreprise = $this->tenantClients()->findOrFail($id);

        if (!$entreprise->client_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur client lie.',
            ], 422);
        }

        User::whereKey($entreprise->client_user_id)->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis a jour.',
        ]);
    }

    public function history(int $id)
    {
        $entreprise = $this->tenantClients()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entreprise->history()
                ->with('changedBy:id,nom,prenom')
                ->limit(100)
                ->get(),
        ]);
    }
}
