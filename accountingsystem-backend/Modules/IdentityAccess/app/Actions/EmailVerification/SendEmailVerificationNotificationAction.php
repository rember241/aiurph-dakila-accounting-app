<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Actions\EmailVerification;

use App\Models\User;

final class SendEmailVerificationNotificationAction
{
    public function execute(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
