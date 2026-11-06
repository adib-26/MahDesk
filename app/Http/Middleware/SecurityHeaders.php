<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the HTTP security headers configured in config/security.php to every
 * response.
 *
 * Pages get a Content-Security-Policy where scripts only run if they come from
 * this site or carry the per-request nonce. Vite, the React refresh preamble
 * and Ziggy's @routes script get that nonce automatically. An attacker-injected
 * <script> or inline event handler (onerror=...) does not, so it is blocked.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $isApi = $request->is('api/*');

        if (! $isApi) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $headers = $response->headers;

        // Don't advertise the PHP version.
        header_remove('X-Powered-By');
        $headers->remove('X-Powered-By');

        foreach (config('security.headers', []) as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if (config('security.hsts.enabled') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', $this->hsts());
        }

        if (! $headers->has('Content-Security-Policy') && ! $headers->has('Content-Security-Policy-Report-Only')) {
            $name = config('security.csp.report_only') && ! $isApi
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $headers->set($name, $isApi ? "default-src 'none'; frame-ancestors 'none'" : $this->pagePolicy($request));
        }

        if (config('security.no_store_when_authenticated') && $request->user() && ! $this->setsOwnCaching($response)) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function hsts(): string
    {
        $value = 'max-age='.config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }

    private function pagePolicy(Request $request): string
    {
        $directives = config('security.csp.directives', []);
        [$devHttp, $devWs] = $this->viteDevServer();

        $directives['script-src'] = [...($directives['script-src'] ?? []), "'nonce-".Vite::cspNonce()."'", ...$devHttp];
        $directives['style-src'] = [...($directives['style-src'] ?? []), ...$devHttp];
        $directives['connect-src'] = [...($directives['connect-src'] ?? []), ...$devHttp, ...$devWs];

        $policy = collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_unique($sources)))
            ->values();

        if ($request->isSecure()) {
            $policy->push('upgrade-insecure-requests');
        }

        if ($uri = config('security.csp.report_uri')) {
            $policy->push("report-uri {$uri}");
        }

        return $policy->implode('; ');
    }

    /** File downloads and other responses that chose their own caching. */
    private function setsOwnCaching(Response $response): bool
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        return str_contains($cacheControl, 'no-store') || $response->headers->has('Content-Disposition');
    }

    /**
     * While `npm run dev` is running, scripts, styles and the HMR websocket
     * come from the Vite dev server, so it is allowed in local development.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function viteDevServer(): array
    {
        if (app()->isProduction() || ! Vite::isRunningHot()) {
            return [[], []];
        }

        $url = rtrim((string) @file_get_contents(public_path('hot')), "/ \n");
        $parts = parse_url($url);

        if (! isset($parts['host'], $parts['port'])) {
            return [[], []];
        }

        // CSP host sources can't express IPv6 literals such as [::1], which Vite
        // often uses for "localhost", so localhost and 127.0.0.1 are allowed as well.
        $hosts = array_unique(array_filter([
            str_contains($parts['host'], ':') ? null : $parts['host'],
            'localhost',
            '127.0.0.1',
        ]));
        $port = $parts['port'];
        $scheme = $parts['scheme'] ?? 'http';
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';

        return [
            array_map(fn ($host) => "{$scheme}://{$host}:{$port}", $hosts),
            array_map(fn ($host) => "{$wsScheme}://{$host}:{$port}", $hosts),
        ];
    }
}
