<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = ['member_id', 'payment_method_id', 'payment_type_id', 'loan_id', 'amount', 'paid_on', 'reference', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Payments whose type actually feeds the contribution ledger (monthly_fee/penalty only;
     * excludes "none" and "loan" payments, e.g. loan repayments).
     */
    public function scopeLedgerRelevant(Builder $query): Builder
    {
        return $query->whereHas('paymentType', fn ($q) => $q->whereIn('ledger_role', [
            PaymentType::ROLE_MONTHLY_FEE,
            PaymentType::ROLE_PENALTY,
        ]));
    }

    /**
     * Payments whose type feeds LoanLedger (ledger_role=loan).
     */
    public function scopeLoanRelevant(Builder $query): Builder
    {
        return $query->whereHas('paymentType', fn ($q) => $q->where('ledger_role', PaymentType::ROLE_LOAN));
    }
}
