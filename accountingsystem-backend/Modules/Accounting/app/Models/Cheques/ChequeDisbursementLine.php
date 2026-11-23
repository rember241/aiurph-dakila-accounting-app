<?php

namespace Modules\Accounting\Models\Cheques;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccounts\Account;

final class ChequeDisbursementLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'cheque_disbursement_id',
        'account_id',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function chequeDisbursement(): BelongsTo
    {
        return $this->belongsTo(ChequeDisbursement::class, 'cheque_disbursement_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
