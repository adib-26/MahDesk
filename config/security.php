<?php

/*
|--------------------------------------------------------------------------
| HTTP security headers
|--------------------------------------------------------------------------
|
| Applied to every response by App\Http\Middleware\SecurityHeaders.
|
*/

return [

    /*
    | Strict-Transport-Security: browsers only use HTTPS for this site for
    | max_age seconds. Sent only on HTTPS responses, and only when enabled
    | (by default in production). Behind a load balancer or proxy that ends
    | TLS, set TRUSTED_PROXIES so Laravel can tell the request was HTTPS.
    |
    | Only enable "preload" after submitting the domain to hstspreload.org;
    | removing a domain from browser preload lists takes months.
    */
    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS', env('APP_ENV') === 'production'),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', true),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    /*
    | Content-Security-Policy for HTML pages. Scripts also get a per-request
    | nonce, and the Vite dev server is allowed while `npm run dev` is running.
    | API responses always get "default-src 'none'".
    |
    | report_uri: where browsers POST violation reports (optional).
    | report_only: send the policy as Content-Security-Policy-Report-Only to
    | test a stricter policy without blocking anything.
    */
    'csp' => [
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            // Radix UI and the chart component set inline styles.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'],
            // fonts.bunny.net serves the stylesheet; the font files come from jsDelivr.
            'font-src' => ["'self'", 'https://fonts.bunny.net', 'https://cdn.jsdelivr.net', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ],
    ],

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        // Other sites can't embed this site's responses (images, JSON, files).
        'Cross-Origin-Resource-Policy' => 'same-origin',
        // Block legacy Flash/PDF cross-domain policy files.
        'X-Permitted-Cross-Domain-Policies' => 'none',
        // Turn off the legacy XSS auditor, which could itself be abused; CSP replaces it.
        'X-XSS-Protection' => '0',
    ],

    /*
    | Signed-in pages and API responses contain private data, so tell
    | browsers and shared proxies not to store them (e.g. the back button
    | after logout, or a shared computer). Responses that already set
    | caching on purpose (file downloads) are left alone.
    */
    'no_store_when_authenticated' => true,

];
