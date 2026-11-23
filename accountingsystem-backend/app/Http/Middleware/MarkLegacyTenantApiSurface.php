<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks legacy tenant-named API entry points so integrators can migrate toward
 * the company-named surface without requiring a risky big-bang cutover.
 */
final class MarkLegacyTenantApiSurface
{
    private const SUNSET_AT = 'Thu, 31 Dec 2026 23:59:59 GMT';

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $replacementPath = ''): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', self::SUNSET_AT);
        $response->headers->set(
            'Warning',
            '299 - "Legacy tenant API surface is deprecated; migrate to company-named routes and X-Company-ID."'
        );
        $response->headers->set('X-Legacy-Contract', 'tenant');

        if (trim($replacementPath) !== '') {
            $response->headers->set('Link', sprintf('<%s>; rel="successor-version"', trim($replacementPath)));
            $response->headers->set('X-Replacement-Path', trim($replacementPath));
        }

        return $response;
    }
}
