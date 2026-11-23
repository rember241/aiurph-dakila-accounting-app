<?php

namespace Modules\Accounting\Enums\PeriodManagement;

enum PeriodStatus: string
{
    case OPEN = 'open';
    case SOFT_CLOSED = 'soft_closed';
    case CLOSED = 'closed';

    /**
     * Determines if the current period status can transition to the given target status.
     *
     * This method is used to enforce the state machine transition rules for period statuses.
     *
     * @param self $target The target period status to transition to.
     * @return bool True if the transition is allowed, false otherwise.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions()[$this->value] ?? [], true);
    }


    /**
     * Returns the list of allowed transitions from the current status.
     *
     * @return PeriodStatus[] The list of allowed transitions.
     */
    public static function allowedTransitions(): array
    {
        return [
            // The OPEN status can transition to SOFT_CLOSED or CLOSED.
            self::OPEN->value => [self::SOFT_CLOSED, self::CLOSED],
            // The SOFT_CLOSED status can transition to OPEN or CLOSED.
            self::SOFT_CLOSED->value => [self::OPEN, self::CLOSED],
            // The CLOSED status cannot transition to any other status.
            self::CLOSED->value => [],
        ];
    }

}
