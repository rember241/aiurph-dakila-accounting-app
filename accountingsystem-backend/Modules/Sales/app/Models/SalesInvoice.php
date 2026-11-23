<?php

namespace Modules\Sales\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Customers\Models\Customer;
use Modules\Sales\Enums\SalesInvoiceDisplayStatus;
use Modules\Users\Models\Company;

/**
 * Company-scoped sales invoice master record.
 *
 * Workflow status is intentionally narrower than the business-facing display
 * status exposed by the API. Display status is derived from workflow state,
 * due dates, and applied payments.
 *
 * @property string $id
 * @property string $company_id
 * @property string $customer_id
 * @property string $invoice_number
 * @property string $status
 * @property \Illuminate\Support\Carbon $invoice_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancelled_reason
 * @property string|null $journal_entry_id
 * @property string|null $reversal_journal_entry_id
 * @property string|null $notes
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total_amount
 */
final class SalesInvoice extends Model
{
    use HasUuids;

    protected $table = 'sales_invoices';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'status',
        'invoice_date',
        'due_date',
        'issued_at',
        'cancelled_at',
        'cancelled_reason',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'accounting_posted_at',
        'notes',
        'subtotal',
        'tax_amount',
        'total_amount',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'invoice_date'        => 'date',
        'due_date'            => 'date',
        'issued_at'           => 'datetime',
        'cancelled_at'        => 'datetime',
        'accounting_posted_at' => 'datetime',
        'subtotal'            => 'float',
        'tax_amount'          => 'float',
        'total_amount'        => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Durable invoice lines with item and tax snapshots.
     *
     * Reports and print layouts should use these snapshots instead of joining
     * back to mutable product master data for historical values.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'sales_invoice_id')->orderBy('line_number');
    }

    /**
     * Payments recorded against this invoice, including voided rows.
     *
     * Use `activePayments()` for balance calculations so voided receipts do not
     * reduce the outstanding amount.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalesInvoicePayment::class, 'sales_invoice_id')->orderBy('payment_date');
    }

    /**
     * Non-voided payments only. Used to compute amount_paid / balance_due so
     * voided receipts stop contributing to balance but stay visible in the
     * `payments` collection for audit and history views.
     */
    public function activePayments(): HasMany
    {
        return $this->hasMany(SalesInvoicePayment::class, 'sales_invoice_id')
            ->whereNull('voided_at')
            ->orderBy('payment_date');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function totalAmount(): float
    {
        if (array_key_exists('total_amount', $this->attributes) && $this->attributes['total_amount'] !== null) {
            return round((float) $this->attributes['total_amount'], 2);
        }

        return round((float) $this->subtotal + $this->taxAmount(), 2);
    }

    public function taxAmount(): float
    {
        if (array_key_exists('tax_amount', $this->attributes) && $this->attributes['tax_amount'] !== null) {
            return round((float) $this->attributes['tax_amount'], 2);
        }

        if ($this->relationLoaded('lines')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, SalesInvoiceLine> $lines */
            $lines = $this->lines;

            return round((float) $lines->sum('tax_amount'), 2);
        }

        return round((float) $this->lines()->sum('tax_amount'), 2);
    }

    public function amountPaid(): float
    {
        if (array_key_exists('amount_paid_total', $this->attributes) && $this->attributes['amount_paid_total'] !== null) {
            return round((float) $this->attributes['amount_paid_total'], 2);
        }

        if ($this->relationLoaded('activePayments')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, SalesInvoicePayment> $payments */
            $payments = $this->activePayments;

            return round((float) $payments->sum('amount'), 2);
        }

        if ($this->relationLoaded('payments')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, SalesInvoicePayment> $payments */
            $payments = $this->payments->filter(
                static fn (SalesInvoicePayment $payment): bool => $payment->voided_at === null
            );

            return round((float) $payments->sum('amount'), 2);
        }

        return round((float) $this->payments()->whereNull('voided_at')->sum('amount'), 2);
    }

    public function balanceDue(): float
    {
        return round($this->totalAmount() - $this->amountPaid(), 2);
    }

    public function isOverdue(?CarbonInterface $today = null): bool
    {
        $comparisonDate = ($today ?? now())->copy()->startOfDay();

        return (string) $this->status === 'issued'
            && $this->balanceDue() > 0
            && $this->due_date !== null
            && $this->due_date->lt($comparisonDate);
    }

    /**
     * Resolves the business-facing status from workflow, due date, and payments.
     *
     * The stored status is deliberately small (`draft`, `issued`, `cancelled`);
     * paid, partially paid, and overdue are calculated from the financial state.
     */
    public function displayStatus(): string
    {
        $status = (string) $this->status;

        if ($status === 'cancelled') {
            return 'cancelled';
        }

        if ($status === 'draft') {
            return 'draft';
        }

        $balanceDue = $this->balanceDue();
        $amountPaid = $this->amountPaid();

        if ($balanceDue <= 0) {
            return 'paid';
        }

        if ($this->isOverdue()) {
            return 'overdue';
        }

        if ($amountPaid > 0 && $balanceDue > 0) {
            return 'partially_paid';
        }

        return 'issued';
    }

    public function displayStatusLabel(): string
    {
        return SalesInvoiceDisplayStatus::label($this->displayStatus());
    }

    public function canEdit(): bool
    {
        return (string) $this->status === 'draft';
    }

    public function canDelete(): bool
    {
        return (string) $this->status === 'draft';
    }

    public function canIssue(): bool
    {
        return (string) $this->status === 'draft';
    }

    public function canCancel(): bool
    {
        $status = (string) $this->status;

        if ($status === 'draft') {
            return true;
        }

        return $status === 'issued' && $this->amountPaid() <= 0;
    }
}
