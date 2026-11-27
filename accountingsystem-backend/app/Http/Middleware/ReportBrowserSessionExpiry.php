<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ReportBrowserSessionExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && $request->hasSession() && Auth::guard('web')->hasUser()) {
            $lifetime = (int) config('session.lifetime') * 60;

            $response->headers->set('X-Session-Expires-At', (string) (now()->getTimestamp() + $lifetime));
            $response->headers->set('X-Session-Warning-Seconds', (string) min(120, max(1, intdiv($lifetime, 5))));
        }

        return $response;
    }
}
