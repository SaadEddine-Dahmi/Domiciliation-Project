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

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer info
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable browser features not needed
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // HSTS — only add in production over HTTPS
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content Security Policy
        // Adjust CSP as your frontend assets require (fonts, CDN etc.)
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",   // unsafe-inline needed for Nuxt inline scripts
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
