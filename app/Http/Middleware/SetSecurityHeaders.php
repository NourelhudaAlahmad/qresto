<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening on every response.
 *
 * - No other site may frame QResto (clickjacking a waiter into a void or a
 *   refund). Stripe's 3-D Secure frames sit inside our page, not around it.
 * - Responses are never sniffed into another content type.
 * - Other sites receive only our origin as the referrer, never a table's
 *   QR token or an order URL.
 * - The camera is for our own QR scanner only; no microphone or location.
 * - Over HTTPS in production, browsers are told to keep using HTTPS.
 *
 * A web server that sets any of these already wins: nothing is replaced.
 */
class SetSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
        ];

        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
