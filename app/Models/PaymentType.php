<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a payment is for, and how the contribution ledger should treat it:
 *   - monthly_fee: replayed into the ledger, contribution settings apply.
 *   - penalty: pays outstanding penalties only, oldest first.
 *   - none: recorded only, never touches the ledger (e.g. loan repayment).
 *
 * The seeded "Monthly Fee" and "Fines / Penalties" types are system types: their name
 * can change, but they cannot be deleted and their ledger_role cannot change. A type
 * used on any payment cannot be deleted, only deactivated.
 */
class PaymentType extends Model
{
    public const ROLE_MONTHLY_FEE = 'monthly_fee';

    public const ROLE_PENALTY = 'penalty';

    public const ROLE_NONE = 'none';

    /** @var array<int, string> */
    public const LEDGER_ROLES = [self::ROLE_MONTHLY_FEE, self::ROLE_PENALTY, self::ROLE_NONE];

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

    public function isLedgerRelevant(): bool
    {
        return $this->ledger_role !== self::ROLE_NONE;
    }
}
