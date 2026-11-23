<?php

namespace Modules\Accounting\DTO\PeriodManagement;

final class SetCurrentFiscalYearData
{
    public function __construct(
        public readonly string $fiscalYearId,

    )
    {
    }

    public static function fromArray(array $payload, string $id): self
    {
        return new self(fiscalYearId: $id);
    }
}
