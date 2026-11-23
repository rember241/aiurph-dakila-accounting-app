<?php

namespace Modules\Accounting\Enums\PeriodManagement;

enum FiscalYearStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
