<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail entry for a loan's workflow: who moved it from one status to another, and when.
 */
class LoanStatusHistory extends Model
{
    protected $table = 'loan_status_histories';

    protected $fillable = ['loan_id', 'from_status', 'to_status', 'changed_by', 'note'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
