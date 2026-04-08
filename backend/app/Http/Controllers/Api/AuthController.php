<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ActivationService $activationService) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'nom'       => ['required', 'string', 'max:20'],
            'prenom'    => ['nullable', 'string', 'max:20'],
            'email'     => ['required', 'email', 'max:50', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'telephone' => ['nullable', 'string', 'max:13'],
            'role'      => ['nullable', 'in:domiciliataire,client,admin'],
        ]);

        $role = $data['role'] ?? 'domiciliataire';

        $user = User::create([
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'] ?? null,
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'telephone' => $data['telephone'] ?? null,
            'role'      => $role,
            // New domiciliataires wait for admin approval
            // Clients and admins are active immediately
            'status'    => $role === 'domiciliataire' ? 'pending' : 'active',
        ]);

        // Pending users don't get a token yet
        if ($user->status === 'pending') {
            return response()->json([
                'success' => true,
                'message' => 'Votre compte est en attente de validation par un administrateur.',
                'data'    => ['user' => $user],
            ], 201);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => compact('user', 'token'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Case-insensitive email lookup
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // Auto-flip approved → active if activation_date has passed
        $this->activationService->activateIfReady($user);
        $user->refresh();

        // Block login with a clear message per status
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => match ($user->status) {
                    'pending'  => 'Votre compte est en attente de validation.',
                    'approved' => 'Votre compte sera activé le ' . $user->activation_date->format('d/m/Y') . '.',
                    'rejected' => 'Votre compte a été rejeté. Raison : ' . $user->rejection_reason,
                    default    => 'Accès refusé.',
                },
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => compact('user', 'token'),
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
