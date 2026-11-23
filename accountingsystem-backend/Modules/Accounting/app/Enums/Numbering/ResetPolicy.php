<?php

namespace Modules\Accounting\Enums\Numbering;

enum ResetPolicy: string
{
    case NEVER = 'never';
    case FISCAL_YEAR = 'fiscal_year';
    case PERIOD = 'period';
}
