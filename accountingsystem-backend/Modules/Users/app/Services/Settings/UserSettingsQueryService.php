<?php

namespace Modules\Users\Services\Settings;

use App\Models\User;

/**
 * Resolves current-user settings reads.
 */
final class UserSettingsQueryService
{
    public function current(User $user): User
    {
        return $user->fresh(['company', 'primaryOrgUnit']) ?? $user->loadMissing(['company', 'primaryOrgUnit']);
    }
}
