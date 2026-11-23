<?php

namespace Modules\Users\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Users\Support\RolePermissionMatrix;

/**
 * Ensures the submitted module permissions do not exceed the declared
 * high-level access_level contract.
 */
class CompatibleAccessLevelModulePermissions implements ValidationRule
{
    public function __construct(
        private readonly string $accessLevel
    )
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || !is_array($value)) {
            return;
        }

        foreach ($value as $module => $verbs) {
            if (!is_string($module) || !is_array($verbs)) {
                continue;
            }

            foreach ($verbs as $verb) {
                if (!is_string($verb)) {
                    continue;
                }

                $normalizedVerb = strtolower(trim($verb));
                if ($normalizedVerb === '') {
                    continue;
                }

                if (!RolePermissionMatrix::isVerbAllowedForAccessLevel($normalizedVerb, $this->accessLevel)) {
                    $normalizedAccessLevel = strtolower(trim($this->accessLevel));

                    $fail("Module `{$module}` contains permission verb `{$verb}` that is not allowed for {$normalizedAccessLevel} access.");

                    return;
                }
            }
        }
    }
}
