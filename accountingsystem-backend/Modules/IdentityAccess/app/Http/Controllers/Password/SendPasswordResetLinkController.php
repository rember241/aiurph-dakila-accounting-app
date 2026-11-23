<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Password;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\IdentityAccess\Actions\Password\SendPasswordResetLinkAction;
use Modules\IdentityAccess\Http\Requests\Password\SendPasswordResetLinkRequest;

final class SendPasswordResetLinkController extends Controller
{
    public function __invoke(
        SendPasswordResetLinkRequest $request,
        SendPasswordResetLinkAction $action,
    ): JsonResponse {
        return response()->json([
            'status' => $action->execute(
                (string) $request->validated('email'),
            ),
        ]);
    }
}
