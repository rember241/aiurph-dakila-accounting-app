<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use LogicException;

final class AuthenticatedUser
{
    public static function from(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('Authenticated user has an unexpected type.');
        }

        return $user;
    }
}
