<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How a payment physically arrived: M-Koba, bank deposit, etc. A method used on any
 * payment cannot be deleted, only deactivated (inactive methods stay off the payment
 * form but remain visible on the payments they were already used for).
 */
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isInUse(): bool
    {
        return $this->payments()->withTrashed()->exists();
    }
}
