<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's guarantee of part of another member's loan. Accepted/declined via
 * LoanService::recordGuarantorResponse(), either by the guarantor themselves (My Account)
 * or by staff on their behalf.
 */
class LoanGuarantor extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    protected $table = 'loan_guarantors';

    protected $fillable = [
        'loan_id', 'guarantor_member_id', 'guaranteed_amount', 'status',
        'responded_by', 'responded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'guaranteed_amount' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function guarantorMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
