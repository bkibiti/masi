<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled repayment for a loan, created at disbursement by LoanCalculator.
 */
class LoanInstallment extends Model
{
    protected $table = 'loan_installments';

    protected $fillable = ['loan_id', 'number', 'due_date', 'principal', 'interest', 'amount', 'amount_paid'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal' => 'decimal:2',
            'interest' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->amount_paid >= (float) $this->amount;
    }

    public function balance(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->amount_paid);
    }
}
