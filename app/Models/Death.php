<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One death record. Rows are never deleted: a wrong record is reversed instead,
 * which keeps the full history.
 */
class Death extends Model
{
    protected $table = 'deaths';

    protected $fillable = [
        'member_id', 'dependant_id', 'deceased_name', 'relationship', 'date_of_death', 'place_of_death',
        'burial_date', 'burial_place', 'reported_by', 'notes', 'recorded_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_of_death' => 'date',
            'burial_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function dependant(): BelongsTo
    {
        return $this->belongsTo(Dependant::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reversed_at');
    }

    public function isMemberDeath(): bool
    {
        return $this->dependant_id === null;
    }

    /**
     * Date for display. Old records imported from the 2019-2023 register have no date.
     */
    public function dateLabel(): string
    {
        return $this->date_of_death?->format('j M Y') ?? 'Date unknown';
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
