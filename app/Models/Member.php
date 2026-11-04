<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members';

    protected $fillable = ['name', 'phone', 'email', 'created_by', 'updated_by'];

    /**
     * The login linked to this member, if the admin has created or linked one. Set via the
     * member login-management actions, never through the member create/edit form.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accepts 0712345678, 712345678, 255712345678 or +255712345678 and returns the
     * canonical 255712345678 form stored in `phone`, or null if it isn't a 9-digit TZ number.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return null;
    }

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
