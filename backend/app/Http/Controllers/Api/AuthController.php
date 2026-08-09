<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ActivationService $activationService)
    {
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'telephone' => ['nullable', 'string', 'max:13'],
            'role' => ['nullable', 'in:domiciliataire,client,admin'],
        ]);

        $role = $data['role'] ?? 'domiciliataire';

        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'telephone' => $data['telephone'] ?? null,
            'role' => $role,
            'status' => $role === 'domiciliataire' ? 'pending' : 'active',
        ]);

        if ($user->status === 'pending') {
            return response()->json([
                'success' => true,
                'message' => 'Votre compte est en attente de validation par un administrateur.',
                'data' => ['user' => $user],
            ], 201);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => compact('user', 'token'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $this->activationService->activateIfReady($user);
        $user->refresh();

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => match ($user->status) {
                    'pending' => 'Votre compte est en attente de validation.',
                    'approved' => 'Votre compte sera activé le ' . $user->activation_date->format('d/m/Y') . '.',
                    'rejected' => 'Votre compte a été rejeté. Raison : ' . $user->rejection_reason,
                    default => 'Accès refusé.',
                },
            ], 403);
        }

        // Client-role users are additionally gated by their linked entreprise's
        // status. A domiciliataire can suspend a client's portal access by
        // marking the entreprise 'inactif' — see ClientController::toggleStatus().
        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();

            if ($entreprise && !$entreprise->isActiveForClient()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre accès a été suspendu par votre domiciliataire. Veuillez le contacter.',
                ], 403);
            }
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => compact('user', 'token'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Déconnecté.']);
    }
}