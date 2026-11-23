<?php

namespace Modules\Users\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Users\Support\RolePermissionMatrix;

/**
 * Validates the `module_permissions` payload for roles.
 *
 * Expected shape:
 *  {
 *    "accounting": ["read", "write"],
 *    "users": ["read"]
 *  }
 *
 * Notes:
 * - Keys represent module identifiers.
 * - Values are arrays of permission verbs (lowercase strings).
 * - The backend resolves these into Sanctum abilities: `${module}.${verb}`.
 */
class ValidModulePermissions implements ValidationRule
{
    /**
     * @var string[]
     */
    private array $allowedVerbs = ['read', 'create', 'write', 'delete'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The '.$attribute.' field must be an object of module permissions.');

            return;
        }

        foreach ($value as $module => $verbs) {
            if (! is_string($module) || trim($module) === '') {
                $fail('Each module_permissions key must be a non-empty string.');

                return;
            }

            if (! preg_match('/^[a-z][a-z0-9_-]*$/', $module)) {
                $fail("Invalid module key `{$module}`. Use lowercase letters, numbers, _ or -.");

                return;
            }

            if (! RolePermissionMatrix::isModuleSupported($module)) {
                $fail("Module `{$module}` is not supported by the current access-control matrix.");

                return;
            }

            if (! RolePermissionMatrix::isModuleEnabled($module)) {
                $fail("Module `{$module}` is currently disabled for this product profile.");

                return;
            }

            if (! is_array($verbs)) {
                $fail("Module `{$module}` must be an array of permission verbs.");

                return;
            }

            foreach ($verbs as $verb) {
                if (! is_string($verb) || trim($verb) === '') {
                    $fail("Module `{$module}` contains an invalid permission verb.");

                    return;
                }

                $normalized = strtolower(trim($verb));
                if (! in_array($normalized, $this->allowedVerbs, true)) {
                    $fail("Module `{$module}` contains unsupported permission verb `{$verb}`.");

                    return;
                }
            }
        }
    }
}
