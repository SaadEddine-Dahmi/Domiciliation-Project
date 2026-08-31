<?php
// app/Http/Controllers/Api/AuthController.php
// Handles account registration, authentication, sessions, and password changes.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountAuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AccountAuthService $accounts)
    {
    }

    public function register(Request $request)
    {
        $result = $this->accounts->register($request->validate([
            'nom' => ['required', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'telephone' => ['nullable', 'string', 'max:13'],
            'role' => ['nullable', 'in:domiciliataire,client'],
        ]));

        if (!$result['token']) {
            return response()->json([
                'success' => true,
                'message' => 'Votre compte est en attente de validation par un administrateur.',
                'data' => ['user' => $result['user']],
            ], 201);
        }

        return response()->json([
            'success' => true,
            'data' => ['user' => $result['user'], 'token' => $result['token']],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $result = $this->accounts->login($data['email'], $data['password']);
        if (!$result['token']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => ['user' => $result['user'], 'token' => $result['token']],
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

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->accounts->changePassword($request->user(), $data['current_password'], $data['password']);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
        ]);
    }
}
