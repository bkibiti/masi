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
    Loan #{{ $loan->id }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('account.loans.index') }}">My Loans</a></li>
    <li class="breadcrumb-item active">#{{ $loan->id }}</li>
@endsection

@section("content")
<div class="col-12 d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge {{ $statusBadge }} fs-6">{{ $statusLabel }}</span>
        <span class="ms-2">{{ $loan->loanType->name }} — {{ $loan->interest_method === 'fixed' ? 'Fixed' : 'Monthly reducing' }} @ {{ formatAmount($loan->interest_rate) }}%</span>
    </div>
    @if (in_array($loan->status, ['pending_guarantors', 'pending_approval']))
        <form method="POST" action="{{ route('account.loans.cancel', $loan) }}" onsubmit="return confirm('Cancel this loan application?');">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Cancel application</button>
        </form>
    @endif
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
                        <thead><tr><th>Guarantor</th><th class="text-end">Amount</th><th>Status</th><th>Responded</th></tr></thead>
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
                                    <td>{{ $guarantor->responded_at?->format('j M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted p-3">No guarantors yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
                            <thead><tr><th>Paid on</th><th class="text-end">Amount</th><th>Method</th><th>Reference</th></tr></thead>
                            <tbody>
                                @forelse ($loan->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                        <td class="text-end">{{ formatAmount($payment->amount) }}</td>
                                        <td>{{ $payment->paymentMethod?->name }}</td>
                                        <td>{{ $payment->reference ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted p-3">No repayments recorded yet.</td></tr>
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
                                @if ($event->note) — {{ $event->note }} @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
