<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $path = $this->redirectPath(Auth::guard($guard)->user()?->role);

                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Déjà authentifié.',
                        'redirect_to' => $path,
                    ], 409);
                }

                return redirect($path);
            }
        }

        return $next($request);
    }

    private function redirectPath(?string $role): string
    {
        return $role === 'client' ? '/client/dashboard' : '/admin/dashboard';
    }
}
