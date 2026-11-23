<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Password;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\IdentityAccess\Actions\Password\ResetPasswordAction;
use Modules\IdentityAccess\Http\Requests\Password\ResetPasswordRequest;

final class ResetPasswordController extends Controller
{
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $action,
    ): JsonResponse {
        return response()->json([
            'status' => $action->execute($request->validated()),
        ]);
    }
}
