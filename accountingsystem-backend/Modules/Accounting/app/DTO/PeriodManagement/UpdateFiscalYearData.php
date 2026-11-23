<?php

namespace Modules\Accounting\DTO\PeriodManagement;

use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;

final readonly class UpdateFiscalYearData
{
    public function __construct(
        public string           $fiscalYearId,
        public string           $code,
        public string           $name,
        public FiscalYearStatus $targetStatus,
    )
    {
    }

    public static function fromArray(array $payload, string $id): self
    {
        return new self(
            fiscalYearId: $id,
            code: (string)$payload['code'],
            name: (string)$payload['name'],
            targetStatus: FiscalYearStatus::from((string)$payload['status']),
        );
    }
}
