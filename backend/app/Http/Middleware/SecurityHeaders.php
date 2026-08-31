<?php
// app/Http/Middleware/SecurityHeaders.php
// Applies browser security headers to API responses.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if ($request->routeIs('factures.pdf')) {
            $response->headers->set('X-Frame-Options', "ALLOW-FROM $frontendUrl");
            $frameAncestors = "'self' $frontendUrl";
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
            $frameAncestors = "'none'";
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors $frameAncestors",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
