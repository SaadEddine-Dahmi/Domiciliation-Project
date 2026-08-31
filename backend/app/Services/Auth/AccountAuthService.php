<?php
// app/Services/Auth/AccountAuthService.php
// Handles registration, login eligibility, and password updates.

namespace App\Services\Auth;

use App\Models\Entreprise;
use App\Models\User;
use App\Services\ActivationService;
use Database\Seeders\DefaultArticlesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountAuthService
{
    public function __construct(private readonly ActivationService $activationService)
    {
    }

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $role = $data['role'] ?? 'domiciliataire';

            $user = User::create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'] ?? null,
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'telephone' => $data['telephone'] ?? null,
                'role' => $role,
                'status' => $role === 'domiciliataire' ? 'pending' : 'active',
            ]);

            if ($role === 'domiciliataire') {
                (new DefaultArticlesSeeder())->run($user->id);
            }

            return [
                'user' => $user,
                'token' => $user->status === 'active' ? $user->createToken('api-token')->plainTextToken : null,
            ];
        });
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', strtolower(trim($email)))->first();
        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Identifiants invalides.']]);
        }

        $this->activationService->activateIfReady($user);
        $user->refresh();

        if (!$user->isActive()) {
            return ['user' => $user, 'token' => null, 'message' => $this->inactiveMessage($user)];
        }

        if ($user->role === 'client' && !$this->clientCanLogin($user)) {
            return [
                'user' => $user,
                'token' => null,
                'message' => 'Votre accès a été suspendu par votre domiciliataire. Veuillez le contacter.',
            ];
        }

        return [
            'user' => $user,
            'token' => $user->createToken('api-token')->plainTextToken,
            'message' => null,
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }

        DB::transaction(fn() => $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ]));
    }

    private function clientCanLogin(User $user): bool
    {
        $entreprise = Entreprise::where('client_user_id', $user->id)->first(['id', 'statut']);

        return !$entreprise || $entreprise->isActiveForClient();
    }

    private function inactiveMessage(User $user): string
    {
        return match ($user->status) {
            'pending' => 'Votre compte est en attente de validation.',
            'approved' => 'Votre compte sera activé le ' . $user->activation_date->format('d/m/Y') . '.',
            'rejected' => 'Votre compte a été rejeté. Raison : ' . $user->rejection_reason,
            default => 'Accès refusé.',
        };
    }
}
