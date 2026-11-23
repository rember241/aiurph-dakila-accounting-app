<?php

namespace App\Support;

final class ModuleAvailability
{
    public static function isSupported(string $module): bool
    {
        return in_array(self::normalize($module), config('module_availability.supported', []), true);
    }

    public static function isEnabled(string $module): bool
    {
        return self::isSupported($module)
            && in_array(self::normalize($module), config('module_availability.enabled', []), true);
    }

    /**
     * @return array<int, string>
     */
    public static function disabledModules(): array
    {
        return array_values(array_diff(
            config('module_availability.supported', []),
            config('module_availability.enabled', [])
        ));
    }

    private static function normalize(string $module): string
    {
        return strtolower(trim($module));
    }
}
