<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Actions\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Laravel\Sanctum\TransientToken;

final class LogoutUserAction
{
    public function execute(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && ! $token instanceof TransientToken) {
            $token->delete();
        }

        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $sessionPath = (string) config('session.path', '/');
        $sessionDomain = config('session.domain');

        Cookie::queue(Cookie::forget(
            (string) config('session.cookie'),
            $sessionPath,
            $sessionDomain,
        ));
        Cookie::queue(Cookie::forget(
            'XSRF-TOKEN',
            $sessionPath,
            $sessionDomain,
        ));
    }
}
