<?php

namespace Modules\Accounting\DTO\PeriodManagement;

use Carbon\Carbon;

/**
 * Data transfer object for creating a fiscal year.
 *
 * @property-read string $code The code of the fiscal year.
 * @property-read string $name The name of the fiscal year.
 * @property-read Carbon $start The start date of the fiscal year.
 * @property-read Carbon $end The end date of the fiscal year.
 * @property-read bool $isCurrent Whether the fiscal year is the current one.
 */
final class CreateFiscalYearData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly bool   $isCurrent,
    )
    {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            code: (string)$payload['code'],
            name: (string)$payload['name'],
            start: Carbon::parse($payload['start_date']),
            end: Carbon::parse($payload['end_date']),
            isCurrent: (bool)($payload['is_current'] ?? false),
        );
    }
}
