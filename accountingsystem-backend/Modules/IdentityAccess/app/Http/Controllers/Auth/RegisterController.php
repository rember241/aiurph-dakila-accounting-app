<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\IdentityAccess\Actions\Auth\RegisterUserAction;
use Modules\IdentityAccess\Http\Requests\Auth\RegisterRequest;

final class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        RegisterUserAction $action,
    ): Response {
        $action->execute($request->validated());

        return response()->noContent();
    }
}
