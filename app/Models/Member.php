<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    public const MAX_PHONES = 3;

    public const GENDERS = ['Male', 'Female'];

    public const STATUSES = ['Active', 'Inactive', 'Deceased'];

    protected $table = 'members';

    protected $fillable = [
        'member_no', 'name', 'gender', 'date_of_birth', 'location', 'joined_on', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_on' => 'date',
        ];
    }

    public function phones(): HasMany
    {
        return $this->hasMany(MemberPhone::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class)->orderBy('name');
    }

    /**
     * Every death recorded under this member's family (the member and the dependants),
     * including reversed records, newest first.
     */
    public function deaths(): HasMany
    {
        return $this->hasMany(Death::class)->latest('date_of_death')->latest('id');
    }

    /**
     * The (non-reversed) death record of the member themself.
     */
    public function ownDeath(): HasOne
    {
        return $this->hasOne(Death::class)->whereNull('dependant_id')->whereNull('reversed_at');
    }

    public function scopeAlive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'Deceased');
    }

    public function isDeceased(): bool
    {
        return $this->status === 'Deceased';
    }

    public function primaryPhone(): ?string
    {
        return $this->phones->first()?->phone;
    }
}
