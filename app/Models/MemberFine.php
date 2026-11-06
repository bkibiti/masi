<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A lump-sum fine an admin charged a member directly (e.g. missing a meeting), as opposed to
 * the automatic 5% contribution penalty. Never created or deleted by ContributionLedger::
 * rebuildForMember() - only its amount_paid and payment allocations are recalculated on rebuild.
 */
class MemberFine extends Model
{
    use SoftDeletes;

    protected $table = 'member_fines';

    protected $fillable = ['member_id', 'fine_type_id', 'fined_on', 'amount', 'amount_paid', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'fined_on' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fineType(): BelongsTo
    {
        return $this->belongsTo(FineType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
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
