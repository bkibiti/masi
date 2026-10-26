<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of dependant: Mzazi (group "Wazazi"), Mkwe, Mtoto, Mume, Mke, ...
 *
 * gender        - every dependant in this category has this gender (Mume -> Male); null = any
 * member_gender - only members of this gender can add it (Mume -> female members); null = any
 */
class DependantCategory extends Model
{
    protected $table = 'dependant_categories';

    protected $fillable = ['name', 'group_name', 'gender', 'member_gender', 'sort_order'];

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function allowsMember(Member $member): bool
    {
        return $this->member_gender === null || $this->member_gender === $member->gender;
    }
}
