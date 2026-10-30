<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One member's contribution obligation for one calendar month.
 */
class MemberDue extends Model
{
    protected $table = 'member_dues';

    protected $fillable = ['member_id', 'contribution_setting_id', 'period', 'due_date', 'amount', 'amount_paid'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ContributionSetting::class, 'contribution_setting_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(MemberPenalty::class)->orderBy('charged_on');
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

    /**
     * Register/statement status for this due: Paid on time, Paid late, Partly paid,
     * Unpaid with penalties, Current month, or Not yet due.
     */
    public function status(string $todayDate, string $currentMonthPeriod): string
    {
        $periodStr = $this->period->toDateString();
        $fullyPaid = $this->isFullyPaid();
        $penaltyCount = $this->penalties->count();

        if ($periodStr === $currentMonthPeriod && ! $fullyPaid) {
            return 'Current month';
        }

        if ($fullyPaid) {
            return $penaltyCount > 0 ? 'Paid late' : 'Paid on time';
        }

        if ((float) $this->amount_paid <= 0) {
            return 'Unpaid with penalties ('.$penaltyCount.')';
        }

        return 'Partly paid';
    }
}
