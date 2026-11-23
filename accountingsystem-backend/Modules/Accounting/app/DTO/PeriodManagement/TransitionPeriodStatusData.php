<?php

namespace Modules\Accounting\DTO\PeriodManagement;

use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;

final readonly class TransitionPeriodStatusData
{
    public function __construct(
        public string       $periodId,
        public PeriodStatus $targetStatus,
    )
    {
    }

    public static function fromArray(array $payload, string $id): self
    {
        return new self(
            periodId: $id,
            targetStatus: PeriodStatus::from((string)$payload['status']),
        );
    }

}
