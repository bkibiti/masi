<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPhone extends Model
{
    protected $table = 'member_phones';

    protected $fillable = ['member_id', 'phone', 'is_primary'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
