<?php

namespace Modules\Users\Support;

use App\Support\ModuleAvailability;
use Modules\Users\Enums\AccessLevel;

/**
 * Central permission matrix for role module_permissions.
 *
 * The UI and API now support exact module verbs such as read, create, write,
 * and delete. This matrix keeps the backend contract strict so read-level
 * roles cannot be over-granted through crafted payloads.
 */
final class RolePermissionMatrix
{
    /**
     * @return array<int, string>
     */
    public static function allowedModules(): array
    {
        return [
            'dashboard_reports',
            'accounting',
            'journal',
            'period',
            'sales',
            'customers',
            'inventory',
            'expenses',
            'users',
            'roles',
            'org_units',
            'settings',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedVerbsForAccessLevel(string $accessLevel): array
    {
        $normalizedAccessLevel = strtolower(trim($accessLevel));

        if ($normalizedAccessLevel === AccessLevel::READ->value) {
            return ['read'];
        }

        return [
            'read',
            'create',
            'write',
            'delete',
        ];
    }

    public static function isVerbAllowedForAccessLevel(string $verb, string $accessLevel): bool
    {
        $normalizedVerb = strtolower(trim($verb));

        return in_array($normalizedVerb, self::allowedVerbsForAccessLevel($accessLevel), true);
    }

    public static function isModuleSupported(string $module): bool
    {
        $normalizedModule = strtolower(trim($module));

        return in_array($normalizedModule, self::allowedModules(), true)
            && ModuleAvailability::isSupported($normalizedModule);
    }

    public static function isModuleEnabled(string $module): bool
    {
        return self::isModuleSupported($module)
            && ModuleAvailability::isEnabled($module);
    }

    /**
     * Preserve disabled permissions in storage, but hide them from effective
     * role-management responses while the product profile is active.
     *
     * @param  array<string, mixed>|null  $modulePermissions
     * @return array<string, mixed>
     */
    public static function filterDisabledModules(?array $modulePermissions): array
    {
        if (! is_array($modulePermissions)) {
            return [];
        }

        return array_filter(
            $modulePermissions,
            static fn ($verbs, $module): bool => is_string($module) && self::isModuleEnabled($module),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param  array<string, mixed>|null  $modulePermissions
     * @return array<string, array<int, string>>
     */
    public static function sanitizeModulePermissionsForAccessLevel(?array $modulePermissions, string $accessLevel): array
    {
        if (empty($modulePermissions)) {
            return [];
        }

        $allowedVerbs = array_fill_keys(self::allowedVerbsForAccessLevel($accessLevel), true);
        $normalized = [];

        foreach ($modulePermissions as $module => $verbs) {
            if (! is_string($module)) {
                continue;
            }

            $moduleKey = strtolower(trim($module));
            if ($moduleKey === '' || ! is_array($verbs) || ! self::isModuleSupported($moduleKey)) {
                continue;
            }

            $cleanVerbs = [];

            foreach ($verbs as $verb) {
                if (! is_string($verb)) {
                    continue;
                }

                $normalizedVerb = strtolower(trim($verb));
                if ($normalizedVerb === '' || ! isset($allowedVerbs[$normalizedVerb])) {
                    continue;
                }

                $cleanVerbs[$normalizedVerb] = $normalizedVerb;
            }

            if ($cleanVerbs !== []) {
                $normalized[$moduleKey] = array_values($cleanVerbs);
            }
        }

        ksort($normalized);

        return $normalized;
    }
}
