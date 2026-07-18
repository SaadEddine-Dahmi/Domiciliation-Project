<?php
// app/Http/Middleware/SecurityHeaders.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get the frontend URL from .env (e.g., http://localhost:3000)
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        // Prevent MIME sniffing[cite: 10]
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer info[cite: 10]
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable browser features not needed[cite: 10]
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // HSTS — only add in production over HTTPS[cite: 10]
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // --- Logic for PDF Preview Route ---
        if ($request->routeIs('factures.pdf')) {
            // Allow framing from your specific frontend URL[cite: 6, 7]
            $response->headers->set('X-Frame-Options', "ALLOW-FROM $frontendUrl");
            $frameAncestors = "'self' $frontendUrl";
        } else {
            // Default strict policy[cite: 10]
            $response->headers->set('X-Frame-Options', 'DENY');
            $frameAncestors = "'none'";
        }

        // Dynamic Content Security Policy[cite: 10]
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors $frameAncestors", // Injected based on route[cite: 6]
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // Remove server fingerprinting headers[cite: 10]
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
