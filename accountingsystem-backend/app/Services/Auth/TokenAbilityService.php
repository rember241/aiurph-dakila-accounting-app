<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\ModuleAvailability;

/**
 * TokenAbilityService
 *
 * Resolves Sanctum token abilities from the user's globally assigned roles.
 *
 * Mental model:
 * - Roles store `module_permissions` as JSON:
 *   { "accounting": ["read","write"], "users": ["read"] }
 * - We flatten that to Sanctum abilities:
 *   ["accounting.read", "accounting.write", "users.read"]
 *
 * Tenant-scoped business APIs must not rely on token-wide abilities because
 * they are resolved against the active tenant membership at request time.
 */
class TokenAbilityService
{
    /**
     * Resolve abilities for a given user.
     *
     * Step-by-step:
     * 1) Load active roles.
     * 2) Merge their module permissions.
     * 3) Normalize into `module.verb` ability strings.
     *
     * @return string[]
     */
    public function resolve(User $user): array
    {
        $abilities = [];

        $roles = $user->roles()
            ->where('roles.status', 'active')
            ->get(['roles.id', 'roles.module_permissions']);

        foreach ($roles as $role) {
            $modulePermissions = $role->module_permissions ?? [];
            if (! is_array($modulePermissions)) {
                continue;
            }

            foreach ($modulePermissions as $module => $verbs) {
                $moduleKey = strtolower(trim((string) $module));
                if ($moduleKey === '' || ! is_array($verbs) || ! ModuleAvailability::isEnabled($moduleKey)) {
                    continue;
                }

                foreach ($verbs as $verb) {
                    $v = strtolower(trim((string) $verb));
                    if ($v === '') {
                        continue;
                    }

                    // Basic hardening: only allow safe ability tokens.
                    $ability = $moduleKey.'.'.$v;
                    if (preg_match('/^[a-z][a-z0-9_-]*\\.[a-z][a-z0-9_-]*$/', $ability) !== 1) {
                        continue;
                    }

                    $abilities[] = $ability;
                }
            }
        }

        $abilities = array_values(array_unique($abilities));
        sort($abilities);

        return $abilities;
    }
}
