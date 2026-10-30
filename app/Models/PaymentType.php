<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a payment is for, and how it should be treated:
 *   - monthly_fee: replayed into the contribution ledger, contribution settings apply.
 *   - penalty: pays outstanding contribution penalties only, oldest first.
 *   - loan: replayed into LoanLedger against the member's loan, never the contribution ledger.
 *   - none: recorded only, touches no ledger.
 *
 * The seeded "Monthly Fee" and "Fines / Penalties" types are system types: their name
 * can change, but they cannot be deleted and their ledger_role cannot change. A type
 * used on any payment cannot be deleted, only deactivated.
 */
class PaymentType extends Model
{
    public const ROLE_MONTHLY_FEE = 'monthly_fee';

    public const ROLE_PENALTY = 'penalty';

    public const ROLE_LOAN = 'loan';

    public const ROLE_NONE = 'none';

    /** @var array<int, string> */
    public const LEDGER_ROLES = [self::ROLE_MONTHLY_FEE, self::ROLE_PENALTY, self::ROLE_LOAN, self::ROLE_NONE];

    protected $table = 'payment_types';

    protected $fillable = ['name', 'description', 'ledger_role', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isInUse(): bool
    {
        return $this->payments()->withTrashed()->exists();
    }

    public function isMonthlyFee(): bool
    {
        return $this->ledger_role === self::ROLE_MONTHLY_FEE;
    }

    public function isPenalty(): bool
    {
        return $this->ledger_role === self::ROLE_PENALTY;
    }

    public function isLoan(): bool
    {
        return $this->ledger_role === self::ROLE_LOAN;
    }

    /**
     * Whether this type feeds the contribution ledger. Deliberately a whitelist (not
     * "!= none") so a new role, such as "loan", never silently flows into it.
     */
    public function isLedgerRelevant(): bool
    {
        return in_array($this->ledger_role, [self::ROLE_MONTHLY_FEE, self::ROLE_PENALTY], true);
    }
}
