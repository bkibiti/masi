@extends("layouts.master")

@php
    $today = now()->toDateString();
    $overdue = $loan->isOverdue($today);
    $statusLabel = match (true) {
        $overdue => 'Overdue',
        $loan->status === 'pending_guarantors' => 'Pending guarantors',
        $loan->status === 'pending_approval' => 'Pending approval',
        $loan->status === 'disbursed' => 'Active',
        default => ucfirst($loan->status),
    };
    $statusBadge = match (true) {
        $overdue => 'text-bg-danger',
        in_array($loan->status, ['pending_guarantors', 'pending_approval']) => 'text-bg-warning',
        $loan->status === 'approved' => 'text-bg-info',
        $loan->status === 'disbursed' => 'text-bg-success',
        $loan->status === 'completed' => 'text-bg-secondary',
        default => 'text-bg-danger',
    };
@endphp

@section('content-title')
    Loan #{{ $loan->id }} — {{ $loan->member->name }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">#{{ $loan->id }}</li>
@endsection

@section("content")
<div class="col-12 d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge {{ $statusBadge }} fs-6">{{ $statusLabel }}</span>
        <span class="ms-2">{{ $loan->loanType->name }} — {{ $loan->interest_method === 'fixed' ? 'Fixed' : 'Monthly reducing' }} @ {{ formatAmount($loan->interest_rate) }}%</span>
    </div>
    <div class="d-flex gap-2">
        @can('Manage Loans')
            @if (in_array($loan->status, ['pending_guarantors', 'pending_approval']))
                <form method="POST" action="{{ route('loans.cancel', $loan) }}" onsubmit="return confirm('Cancel this loan application?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Cancel application</button>
                </form>
            @endif
        @endcan
        @can('Approve Loans')
            @if ($loan->status === 'pending_approval')
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approve-loan">Approve</button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reject-loan">Reject</button>
            @endif
        @endcan
        @can('Disburse Loans')
            @if ($loan->status === 'approved')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#disburse-loan">Release funds</button>
            @endif
        @endcan
        @can('Record Loan Repayments')
            @if ($loan->status === 'disbursed')
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#record-payment">
                    <i class="bi bi-cash-coin"></i> Record repayment
                </button>
            @endif
        @endcan
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Requested</div>
        <div class="fs-5 fw-bold">TZS {{ formatAmount($loan->requested_amount) }} / {{ $loan->requested_duration_months }} mo</div>
    </div></div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Approved</div>
        <div class="fs-5 fw-bold">{{ $loan->approved_amount !== null ? 'TZS '.formatAmount($loan->approved_amount).' / '.$loan->approved_duration_months.' mo' : '—' }}</div>
    </div></div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Disbursed on</div>
        <div class="fs-5 fw-bold">{{ $loan->disbursed_on?->format('j M Y') ?? '—' }}</div>
        @can('Disburse Loans')
            @if (in_array($loan->status, ['disbursed', 'completed']))
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#change-release-date">Correct date</button>
            @endif
        @endcan
    </div></div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Outstanding</div>
        <div class="fs-5 fw-bold {{ $loan->outstandingBalance() > 0 ? ($overdue ? 'text-danger' : '') : 'text-success' }}">TZS {{ formatAmount($loan->outstandingBalance()) }}</div>
    </div></div>
</div>

@if ($loan->purpose)
    <div class="col-12 mb-3"><div class="card"><div class="card-body"><strong>Purpose:</strong> {{ $loan->purpose }}</div></div></div>
@endif

@if ($loan->requiresGuarantors() || $loan->guarantors->isNotEmpty())
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Guarantors</h3>
                <div class="text-muted small">
                    Required: TZS {{ formatAmount($loan->required_guarantee_amount) }}.
                    Accepted so far: TZS {{ formatAmount($loan->acceptedGuaranteeAmount()) }}.
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Guarantor</th><th class="text-end">Amount</th><th>Status</th><th>Responded</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($loan->guarantors as $guarantor)
                                <tr>
                                    <td>{{ $guarantor->guarantorMember->name }}</td>
                                    <td class="text-end">{{ formatAmount($guarantor->guaranteed_amount) }}</td>
                                    <td>
                                        <span class="badge {{ match($guarantor->status) { 'accepted' => 'text-bg-success', 'declined' => 'text-bg-danger', default => 'text-bg-warning' } }}">
                                            {{ ucfirst($guarantor->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $guarantor->responded_at?->format('j M Y') }} {{ $guarantor->respondedBy?->name ? '('.$guarantor->respondedBy->name.')' : '' }}</td>
                                    <td class="text-end" style="white-space: nowrap">
                                        @can('Manage Loans')
                                            @if ($guarantor->status === 'pending')
                                                <form method="POST" action="{{ route('loans.guarantors.respond', [$loan, $guarantor]) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="response" value="accepted">
                                                    <button type="submit" class="btn btn-outline-success btn-sm">Accept</button>
                                                </form>
                                                <form method="POST" action="{{ route('loans.guarantors.respond', [$loan, $guarantor]) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="response" value="declined">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Decline</button>
                                                </form>
                                            @endif
                                            @if ($loan->status === 'pending_guarantors')
                                                <form method="POST" action="{{ route('loans.guarantors.destroy', [$loan, $guarantor]) }}" class="d-inline" onsubmit="return confirm('Remove this guarantor?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash"></i></button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted p-3">No guarantors yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @can('Manage Loans')
                    @if ($loan->status === 'pending_guarantors')
                        <form method="POST" action="{{ route('loans.guarantors.store', $loan) }}" class="row g-2 p-3">
                            @csrf
                            <div class="col-md-5">
                                <select name="guarantor_member_id" class="form-select" required>
                                    <option value="">Select guarantor</option>
                                    @foreach ($members as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->phone }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="guaranteed_amount" class="form-control" placeholder="Guaranteed amount" min="1" step="1" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary w-100">Add guarantor</button>
                            </div>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
@endif

<div class="col-12">
    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-schedule" type="button">Schedule</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-penalties" type="button">Penalties</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button">Payments</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-timeline" type="button">Timeline</button></li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active p-0" id="tab-schedule">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>#</th><th>Due date</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($loan->installments as $installment)
                                    <tr>
                                        <td>{{ $installment->number }}</td>
                                        <td>{{ $installment->due_date->format('j M Y') }}</td>
                                        <td class="text-end">{{ formatAmount($installment->principal) }}</td>
                                        <td class="text-end">{{ formatAmount($installment->interest) }}</td>
                                        <td class="text-end">{{ formatAmount($installment->amount) }}</td>
                                        <td class="text-end">{{ formatAmount($installment->amount_paid) }}</td>
                                        <td class="text-end">{{ formatAmount($installment->balance()) }}</td>
                                        <td>{{ $installment->isFullyPaid() ? 'Paid' : ($installment->amount_paid > 0 ? 'Partly paid' : 'Unpaid') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted p-3">No schedule yet — the loan has not been disbursed.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade p-0" id="tab-penalties">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Charged on</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                            <tbody>
                                @forelse ($loan->penalties as $penalty)
                                    <tr>
                                        <td>{{ $penalty->charged_on->format('j M Y') }}</td>
                                        <td class="text-end">{{ formatAmount($penalty->amount) }}</td>
                                        <td class="text-end">{{ formatAmount($penalty->amount_paid) }}</td>
                                        <td class="text-end">{{ formatAmount($penalty->balance()) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted p-3">No penalties charged.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade p-0" id="tab-payments">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Paid on</th><th class="text-end">Amount</th><th>Method</th><th>Reference</th><th>Recorded by</th></tr></thead>
                            <tbody>
                                @forelse ($loan->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                        <td class="text-end">{{ formatAmount($payment->amount) }}</td>
                                        <td>{{ $payment->paymentMethod?->name }}</td>
                                        <td>{{ $payment->reference ?: '—' }}</td>
                                        <td>{{ $payment->recorder?->name }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted p-3">No repayments recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade p-3" id="tab-timeline">
                    <ul class="list-unstyled mb-0">
                        @foreach ($loan->statusHistory as $event)
                            <li class="mb-2">
                                <strong>{{ $event->created_at->format('j M Y H:i') }}</strong> —
                                {{ $event->from_status ? ucfirst(str_replace('_', ' ', $event->from_status)).' → ' : '' }}{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}
                                @if ($event->changedBy) by {{ $event->changedBy->name }} @endif
                                @if ($event->note) — {{ $event->note }} @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@can('Approve Loans')
    @include('loans._approve_form', ['loan' => $loan])
    @include('loans._reject_form', ['loan' => $loan])
@endcan
@can('Disburse Loans')
    @include('loans._disburse_form', ['loan' => $loan, 'paymentMethods' => $paymentMethods])
    @if (in_array($loan->status, ['disbursed', 'completed']))
        <div class="modal fade" id="change-release-date" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('loans.disbursement-date', $loan) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Correct release date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="corrected-disbursed-on">Released on <span class="text-danger">*</span></label>
                        <input type="date" id="corrected-disbursed-on" name="disbursed_on" class="form-control" max="{{ now()->toDateString() }}" value="{{ $loan->disbursed_on?->toDateString() }}" required>
                        <div class="form-text">Installment amounts stay the same. Due dates move to run monthly from this date, and penalties and status are recalculated. The application and approval dates move back if they are later.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save date</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan
@can('Record Loan Repayments')
    @if ($loan->status === 'disbursed')
        @include('contributions.payments._form', ['mode' => 'create', 'members' => [$loan->member]])
    @endif
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
