<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\EmailVerification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\IdentityAccess\Actions\EmailVerification\SendEmailVerificationNotificationAction;
use Modules\IdentityAccess\Support\Auth\AuthenticatedUser;

final class SendEmailVerificationNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        SendEmailVerificationNotificationAction $action,
    ): JsonResponse|RedirectResponse {
        $user = AuthenticatedUser::from($request);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $action->execute($user);

        return response()->json([
            'status' => 'verification-link-sent',
        ]);
    }
}
