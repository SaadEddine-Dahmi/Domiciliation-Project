<?php
// app/Http/Controllers/Concerns/AuthorizesApiRoles.php
// Provides small role guards for API controllers.

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait AuthorizesApiRoles
{
    protected function denyUnlessRole(?User $user, string|array $roles, string $message = 'Non autorisé.'): ?JsonResponse
    {
        $allowed = is_array($roles) ? $roles : [$roles];

        return $user && in_array($user->role, $allowed, true)
            ? null
            : response()->json(['success' => false, 'message' => $message], 403);
    }
}
