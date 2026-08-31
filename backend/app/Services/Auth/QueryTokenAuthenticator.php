<?php
// app/Services/Auth/QueryTokenAuthenticator.php
// Resolves Sanctum users from query-string tokens for browser-opened URLs.

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class QueryTokenAuthenticator
{
    public function userFromRequest(Request $request): ?User
    {
        $tokenValue = $request->query('token');
        if (!$tokenValue) {
            return null;
        }

        $token = PersonalAccessToken::findToken($tokenValue);
        if (!$token || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        return $token->tokenable instanceof User ? $token->tokenable : null;
    }
}
