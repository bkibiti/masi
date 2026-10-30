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

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->latest('id');
    }

    /**
     * Loans this member has agreed (or been asked) to guarantee for someone else.
     */
    public function guarantees(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class, 'guarantor_member_id')->latest('id');
    }

    public function activeLoan(): ?Loan
    {
        return $this->loans()->whereIn('status', Loan::ACTIVE_STATUSES)->first();
    }

    /**
     * Sum of all monthly dues this member has actually paid, across every year. Used by
     * LoanEligibility for the guarantor threshold.
     */
    public function totalContributionsPaid(): float
    {
        return (float) $this->dues()->sum('amount_paid');
    }
}
