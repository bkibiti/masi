<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lump-sum fine category an admin can charge a member for (e.g. missing a meeting).
 * A type used by any fine cannot be deleted, only deactivated.
 */
class FineType extends Model
{
    protected $table = 'fine_types';

    protected $fillable = ['name', 'default_amount', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function fines(): HasMany
    {
        return $this->hasMany(MemberFine::class);
    }

    public function isInUse(): bool
    {
        return $this->fines()->withTrashed()->exists();
    }
}
