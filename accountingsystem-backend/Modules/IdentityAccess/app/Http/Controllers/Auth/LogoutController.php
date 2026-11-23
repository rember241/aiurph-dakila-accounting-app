<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\IdentityAccess\Actions\Auth\LogoutUserAction;

final class LogoutController extends Controller
{
    public function __invoke(
        Request $request,
        LogoutUserAction $action,
    ): Response {
        $action->execute($request);

        return response()->noContent();
    }
}
