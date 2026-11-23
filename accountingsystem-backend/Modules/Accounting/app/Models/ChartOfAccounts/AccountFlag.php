<?php

namespace Modules\Accounting\Models\ChartOfAccounts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountFlag extends Model
{
    use HasUuids;

    protected $fillable = ['account_id', 'flag', 'value', 'scope'];
}
