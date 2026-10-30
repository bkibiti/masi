<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One contribution period: a date range with its own monthly amount and penalty %.
 * Locked (dates/amount/penalty_percent read-only) once any due under it has a payment allocated.
 */
class ContributionSetting extends Model
{
    protected $table = 'contribution_settings';

    protected $fillable = ['name', 'start_date', 'end_date', 'monthly_amount', 'penalty_percent', 'notes'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_amount' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
        ];
    }

    public function dues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }

    public function status(): string
    {
        $today = now()->toDateString();

        if ($this->start_date->toDateString() > $today) {
            return 'Upcoming';
        }

        if ($this->end_date->toDateString() < $today) {
            return 'Closed';
        }

        return 'Active';
    }

    /**
     * Locked once a payment has been allocated to a due or penalty under this setting.
     */
    public function isLocked(): bool
    {
        return $this->dues()->where('amount_paid', '>', 0)->exists()
            || $this->dues()->whereHas('penalties', fn ($q) => $q->where('amount_paid', '>', 0))->exists();
    }

    public function hasDues(): bool
    {
        return $this->dues()->exists();
    }

    /**
     * Number of whole months this period covers.
     */
    public function monthCount(): int
    {
        return $this->start_date->diffInMonths($this->end_date->copy()->addDay());
    }

    /**
     * Penalty for one fully unpaid month under this setting, rounded to whole TZS.
     */
    public function penaltyForFullMonth(): int
    {
        return (int) round((float) $this->monthly_amount * (float) $this->penalty_percent / 100);
    }
}
