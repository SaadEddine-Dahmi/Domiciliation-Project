<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\User;
use App\Services\ActivationService;
use Database\Seeders\DefaultArticlesSeeder;
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

        if ($role === 'domiciliataire') {
            (new DefaultArticlesSeeder())->run($user->id);
        }

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

        // $user->must_change_password is serialised automatically since
        // it isn't in $hidden — the frontend reads it to decide whether
        // to show the "change your password?" prompt after login.
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

    /**
     * PUT /api/account/password
     *
     * Lets the currently authenticated user set their own password.
     * Used by the "change password" prompt shown to anyone whose
     * account still carries a system-generated password
     * (must_change_password === true), and reusable from account
     * settings at any other time too.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update([
            'password'             => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
        ]);
    }
}