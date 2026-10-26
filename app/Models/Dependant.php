<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dependant extends Model
{
    protected $table = 'dependants';

    protected $fillable = [
        'member_id', 'dependant_category_id', 'name', 'gender', 'date_of_birth', 'phone', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DependantCategory::class, 'dependant_category_id');
    }

    /**
     * The (non-reversed) death record of this dependant.
     */
    public function death(): HasOne
    {
        return $this->hasOne(Death::class)->whereNull('reversed_at');
    }

    /**
     * Category name, e.g. "Mzazi". Also stored on the death record when a death is recorded.
     */
    public function relationshipName(): string
    {
        return $this->category?->name ?? '';
    }

    public function isDeceased(): bool
    {
        return $this->status === 'Deceased';
    }
}
