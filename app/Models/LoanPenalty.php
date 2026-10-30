<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly penalty charged on a loan's outstanding balance after its period has ended.
 * Charged by LoanLedger; never charged while the loan is still within its period.
 */
class LoanPenalty extends Model
{
    protected $table = 'loan_penalties';

    protected $fillable = ['loan_id', 'charged_on', 'amount', 'amount_paid'];

    protected function casts(): array
    {
        return [
            'charged_on' => 'date',
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
