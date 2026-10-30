<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members';

    protected $fillable = ['name', 'phone', 'email', 'created_by', 'updated_by'];

    public function dues(): HasMany
    {
        return $this->hasMany(MemberDue::class)->orderBy('period');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(MemberPenalty::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }
}
