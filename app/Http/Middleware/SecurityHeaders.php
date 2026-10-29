<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $this->aplicarContentSecurityPolicy($request, $response);

        return $response;
    }

    private function aplicarContentSecurityPolicy(
        Request $request,
        Response $response
    ): void {
        if (! config('security.csp.enabled', false)) {
            return;
        }

        $diretivas = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            'connect-src '.$this->origensPermitidas(),
            "media-src 'self' blob:",
        ];

        if ($request->isSecure()) {
            $diretivas[] = 'upgrade-insecure-requests';
        }

        $cabecalho = config('security.csp.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($cabecalho, implode('; ', $diretivas));
    }

    private function origensPermitidas(): string
    {
        $urls = [
            (string) config('app.url'),
            (string) config('app.frontend_url'),
        ];

        $origens = array_map(
            fn (string $url): ?string => $this->origem($url),
            $urls
        );

        $origens = array_values(array_unique(array_filter($origens)));

        return implode(' ', ["'self'", ...$origens]);
    }

    private function origem(string $url): ?string
    {
        $partes = parse_url($url);

        if (! isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $origem = $partes['scheme'].'://'.$partes['host'];

        if (isset($partes['port'])) {
            $origem .= ':'.$partes['port'];
        }

        return $origem;
    }
}
