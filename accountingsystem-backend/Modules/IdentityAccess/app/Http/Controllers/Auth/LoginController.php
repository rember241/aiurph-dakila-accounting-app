<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\IdentityAccess\Actions\Auth\LoginUserAction;
use Modules\IdentityAccess\Http\Requests\Auth\LoginRequest;
use Modules\IdentityAccess\Http\Resources\Auth\LoginResource;

final class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        LoginUserAction $action,
    ): JsonResponse {
        return response()->json(
            (new LoginResource($action->execute($request)))->resolve($request),
        );
    }
}
