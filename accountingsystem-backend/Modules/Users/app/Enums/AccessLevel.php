<?php

namespace Modules\Users\Enums;

/**
 * High-level access hint used for UI and coarse access gating.
 *
 * Note: Fine-grained access is expressed via `module_permissions` and resolved
 * into Sanctum abilities like `accounting.read` or `users.write`.
 */
enum AccessLevel: string
{
    case READ = 'read';
    case WRITE = 'write';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}

