<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    public const STATUS_PENDING_GUARANTORS = 'pending_guarantors';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<int, string> A member can have only one loan in these statuses at a time. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_GUARANTORS,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_DISBURSED,
    ];

    protected $table = 'loans';

    protected $fillable = [
        'member_id', 'loan_type_id', 'requested_amount', 'requested_duration_months', 'purpose',
        'approved_amount', 'approved_duration_months',
        'interest_method', 'interest_rate', 'penalty_percent', 'installment_rounding', 'interest_rounding',
        'status', 'applicant_contributions_total', 'required_guarantee_amount',
        'applied_by', 'applied_at',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'cancelled_by', 'cancelled_at',
        'disbursed_by', 'disbursed_at', 'disbursed_on',
        'disbursement_payment_method_id', 'disbursement_reference', 'disbursement_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'requested_duration_months' => 'integer',
            'approved_amount' => 'decimal:2',
            'approved_duration_months' => 'integer',
            'interest_rate' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
            'installment_rounding' => 'integer',
            'interest_rounding' => 'integer',
            'applicant_contributions_total' => 'decimal:2',
            'required_guarantee_amount' => 'decimal:2',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'disbursed_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LoanPenalty::class)->orderBy('charged_on');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function disbursementPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'disbursement_payment_method_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function requiresGuarantors(): bool
    {
        return (float) $this->required_guarantee_amount > 0;
    }

    public function acceptedGuaranteeAmount(): float
    {
        return (float) $this->guarantors()->where('status', LoanGuarantor::STATUS_ACCEPTED)->sum('guaranteed_amount');
    }

    public function guaranteesCoverRequirement(): bool
    {
        return $this->acceptedGuaranteeAmount() >= (float) $this->required_guarantee_amount;
    }

    public function effectiveAmount(): float
    {
        return (float) ($this->approved_amount ?? $this->requested_amount);
    }

    public function effectiveDurationMonths(): int
    {
        return (int) ($this->approved_duration_months ?? $this->requested_duration_months);
    }

    /**
     * The loan period ends on the due date of the last installment. Null until disbursed.
     */
    public function periodEndDate(): ?CarbonImmutable
    {
        $lastDueDate = $this->installments()->max('due_date');

        return $lastDueDate ? CarbonImmutable::parse($lastDueDate) : null;
    }

    public function outstandingBalance(): float
    {
        $installmentBalance = (float) $this->installments->sum(fn (LoanInstallment $i) => $i->balance());
        $penaltyBalance = (float) $this->penalties->sum(fn (LoanPenalty $p) => $p->balance());

        return $installmentBalance + $penaltyBalance;
    }

    public function isOverdue(string $today): bool
    {
        $periodEnd = $this->periodEndDate();

        return $this->status === self::STATUS_DISBURSED
            && $periodEnd !== null
            && $periodEnd->toDateString() < $today
            && $this->outstandingBalance() > 0;
    }
}
