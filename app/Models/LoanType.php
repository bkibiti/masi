<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loan product: its interest method/rate, amount and duration limits, and the penalty %
 * charged per month once a loan's period has ended. Changing a loan type never changes an
 * existing loan: the rate, method and penalty % are copied onto the loan when it is created.
 * A loan type used by any loan cannot be deleted, only deactivated.
 */
class LoanType extends Model
{
    public const METHOD_MONTHLY_REDUCING = 'monthly_reducing';

    public const METHOD_FIXED = 'fixed';

    /** @var array<int, string> */
    public const INTEREST_METHODS = [self::METHOD_MONTHLY_REDUCING, self::METHOD_FIXED];

    /**
     * Rounding choices for the schedule. Installment: rounded UP to a multiple of this.
     * Interest: rounded to the NEAREST multiple. MASI's Excel sheet uses 1,000 and 100.
     *
     * @var array<int, string>
     */
    public const INSTALLMENT_ROUNDING_OPTIONS = [1 => 'Exact (whole TZS)', 100 => 'Up to the next 100', 1000 => 'Up to the next 1,000'];

    /** @var array<int, string> */
    public const INTEREST_ROUNDING_OPTIONS = [1 => 'Exact (whole TZS)', 10 => 'Nearest 10', 100 => 'Nearest 100', 1000 => 'Nearest 1,000'];

    protected $table = 'loan_types';

    protected $fillable = [
        'name', 'description', 'interest_method', 'interest_rate',
        'min_amount', 'max_amount', 'min_duration_months', 'max_duration_months',
        'penalty_percent', 'installment_rounding', 'interest_rounding', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'min_duration_months' => 'integer',
            'max_duration_months' => 'integer',
            'penalty_percent' => 'decimal:2',
            'installment_rounding' => 'integer',
            'interest_rounding' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function isInUse(): bool
    {
        return $this->loans()->exists();
    }
}
