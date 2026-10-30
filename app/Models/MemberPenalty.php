<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One penalty charge against a member's due, for one month it stayed outstanding.
 * Never charged on another penalty, only on an unpaid contribution.
 */
class MemberPenalty extends Model
{
    protected $table = 'member_penalties';

    protected $fillable = ['member_id', 'member_due_id', 'charged_on', 'amount', 'amount_paid'];

    protected function casts(): array
    {
        return [
            'charged_on' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function due(): BelongsTo
    {
        return $this->belongsTo(MemberDue::class, 'member_due_id');
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
