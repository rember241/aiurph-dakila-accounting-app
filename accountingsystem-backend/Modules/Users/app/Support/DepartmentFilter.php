<?php

namespace Modules\Users\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared department filter helper for nullable department fields.
 */
final class DepartmentFilter
{
    public const UNASSIGNED = '__unassigned__';

    /**
     * Apply a department filter to a nullable/shared department column.
     */
    public static function apply(Builder $query, string $column, string $filter): Builder
    {
        $normalizedFilter = trim($filter);

        if ($normalizedFilter === '') {
            return $query;
        }

        if ($normalizedFilter === self::UNASSIGNED) {
            return $query->where(function (Builder $where) use ($column): void {
                $where
                    ->whereNull($column)
                    ->orWhere($column, '');
            });
        }

        return $query->where($column, $normalizedFilter);
    }
}
