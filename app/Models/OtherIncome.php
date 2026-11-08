<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money the group received that does not belong to any member (opening balance not
 * attributable to a member, bank interest, donations...). Adds to cash available and the
 * group fund; never touches member contributions, statements or arrears.
 */
class OtherIncome extends Model
{
    use SoftDeletes;

    protected $table = 'other_incomes';

    protected $fillable = ['received_on', 'description', 'amount', 'payment_method_id', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
