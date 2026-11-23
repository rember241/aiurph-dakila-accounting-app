<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\IdentityAccess\Actions\Auth\ResolveCurrentUserAction;
use Modules\IdentityAccess\Http\Resources\Auth\CurrentUserResource;
use Modules\IdentityAccess\Support\Auth\AuthenticatedUser;

final class CurrentUserController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveCurrentUserAction $action,
    ): JsonResponse {
        $context = $action->execute(AuthenticatedUser::from($request));

        return response()->json(
            (new CurrentUserResource($context))->resolve($request),
        );
    }
}
