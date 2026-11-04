@extends("layouts.master")

@section('content-title')
    {{ $member->name }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('members.index') }}">Members</a></li>
    <li class="breadcrumb-item active">{{ $member->name }}</li>
@endsection

@section("content")
@if (session('temp_password'))
    <div class="col-12">
        <div class="alert alert-warning">
            <strong>Temporary password for {{ session('temp_password_username') }}:</strong>
            <code class="fs-5">{{ session('temp_password') }}</code>
            &mdash; share it with {{ $member->name }} now. It will not be shown again; they must change it at first login.
        </div>
    </div>
@endif

{{-- Actions --}}
<div class="col-12 d-flex justify-content-end gap-2 mb-3">
    @can('Manage Members')
        @include('members._login_actions', ['member' => $member, 'linkableUsers' => $linkableUsers])
    @endcan
    @can('Record Payments')
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#record-payment"
            data-member-id="{{ $member->id }}" data-member-name="{{ $member->name }}">
            <i class="bi bi-cash-coin"></i> Record payment
        </button>
    @endcan
</div>

{{-- Summary cards --}}
@php
    $tzs = fn ($v) => formatAmount($v);
    $label = 'small fw-semibold text-uppercase text-body-secondary';
@endphp
<div class="col-12 mb-3">
    <div class="row g-3">
        @can('View Contributions')
            {{-- Total amount paid --}}
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 mb-0"><div class="card-body d-flex flex-column gap-2">
                    <div class="{{ $label }}" style="letter-spacing: .05em">Total amount paid</div>
                    <div class="fs-4 fw-bold">TZS {{ $tzs($summary['paid']['total']) }}</div>
                    <div class="border-top pt-2 small d-flex flex-column gap-1">
                        <div class="d-flex justify-content-between"><span class="text-body-secondary">Monthly fees</span><span class="fw-semibold">{{ $tzs($summary['paid']['monthly_fees']) }}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-body-secondary">Fines / penalties</span><span class="fw-semibold">{{ $tzs($summary['paid']['fines']) }}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-body-secondary">Loan repayments</span><span class="fw-semibold">{{ $tzs($summary['paid']['loans']) }}</span></div>
                        @if ($summary['paid']['other'] > 0)
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">Other</span><span class="fw-semibold">{{ $tzs($summary['paid']['other']) }}</span></div>
                        @endif
                    </div>
                    <div class="small text-body-secondary mt-auto">All years{{ $summary['paid']['last_paid_on'] ? ' · last payment '.\Carbon\Carbon::parse($summary['paid']['last_paid_on'])->format('j M Y') : '' }}</div>
                </div></div>
            </div>
        @endcan

        @can('View Loans')
            {{-- Current loan --}}
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 mb-0"><div class="card-body d-flex flex-column gap-2">
                    @php $l = $summary['loan']; @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="{{ $label }}" style="letter-spacing: .05em">Current loan</div>
                        @if ($l)
                            @if ($l['overdue'])
                                <span class="badge text-bg-danger">Overdue</span>
                            @elseif ($l['released'])
                                <span class="badge text-bg-primary">Active</span>
                            @else
                                <span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $l['model']->status)) }}</span>
                            @endif
                        @endif
                    </div>
                    @if (! $l)
                        <div class="fs-4 fw-bold text-body-secondary">No active loan</div>
                    @elseif (! $l['released'])
                        <div class="fs-4 fw-bold">TZS {{ $tzs($l['amount']) }}</div>
                        <div class="small text-body-secondary">{{ $l['model']->loanType->name }} · applied, not yet released</div>
                    @else
                        <div class="fs-4 fw-bold">TZS {{ $tzs($l['outstanding']) }} <span class="fs-6 fw-normal text-body-secondary">outstanding</span></div>
                        <div class="progress" style="height: 8px" role="progressbar" aria-label="Loan repaid" aria-valuenow="{{ $l['progress'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $l['progress'] }}%"></div>
                        </div>
                        <div class="small d-flex flex-column gap-1">
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">{{ $l['model']->loanType->name }} · {{ $tzs($l['amount']) }}</span><span class="fw-semibold">{{ $l['paid_count'] }} of {{ $l['installment_count'] }} paid</span></div>
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">Loan amount due now</span><span class="fw-semibold {{ $l['due_now'] > 0 ? 'text-danger' : '' }}">{{ $tzs($l['due_now']) }}</span></div>
                            @if ($l['next'])
                                <div class="d-flex justify-content-between"><span class="text-body-secondary">Next installment</span><span class="fw-semibold">{{ $tzs($l['next']['amount']) }} · {{ $l['next']['due_date']->format('j M') }}</span></div>
                            @endif
                        </div>
                    @endif
                    @if ($l)
                        <a href="{{ route('loans.show', $l['model']) }}" class="small fw-semibold text-decoration-none mt-auto">View loan &rarr;</a>
                    @endif
                </div></div>
            </div>
        @endcan

        @can('View Contributions')
            {{-- Arrears (monthly fees + penalties past due) --}}
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 mb-0"><div class="card-body d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="{{ $label }}" style="letter-spacing: .05em">Arrears</div>
                        @if ($summary['arrears']['total'] > 0)
                            <span class="badge text-bg-danger">Overdue</span>
                        @else
                            <span class="badge text-bg-success">Up to date</span>
                        @endif
                    </div>
                    <div class="fs-4 fw-bold {{ $summary['arrears']['total'] > 0 ? 'text-danger' : '' }}">TZS {{ $tzs($summary['arrears']['total']) }}</div>
                    <div class="border-top pt-2 small d-flex flex-column gap-1">
                        <div class="d-flex justify-content-between"><span class="text-body-secondary">Unpaid monthly fees</span><span class="fw-semibold">{{ $tzs($summary['arrears']['fees']) }}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-body-secondary">Unpaid penalties</span><span class="fw-semibold">{{ $tzs($summary['arrears']['penalties']) }}</span></div>
                    </div>
                    <div class="small text-body-secondary mt-auto">Past due only · credit TZS {{ $tzs($summary['arrears']['credit']) }}</div>
                </div></div>
            </div>

            {{-- Amount due by the end of this month --}}
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 mb-0 border-0" style="background: var(--abg-black); color: #FFFFFF"><div class="card-body d-flex flex-column gap-2">
                    <div class="small fw-semibold text-uppercase" style="letter-spacing: .05em; color: #C3C9DE">Amount due by {{ $summary['due']['month_end']->format('j M') }}</div>
                    <div class="fs-4 fw-bold" style="color: var(--masi-orange-on-dark)">TZS {{ $tzs($summary['due']['total']) }}</div>
                    <div class="pt-2 small d-flex flex-column gap-1" style="border-top: 1px solid #232B52">
                        <div class="d-flex justify-content-between"><span style="color: #C3C9DE">Arrears (overdue now)</span><span class="fw-semibold">{{ $tzs($summary['due']['arrears']) }}</span></div>
                        <div class="d-flex justify-content-between"><span style="color: #C3C9DE">{{ $summary['due']['month_end']->format('F') }} fee</span><span class="fw-semibold">{{ $tzs($summary['due']['current_fee']) }}</span></div>
                        @can('View Loans')
                            <div class="d-flex justify-content-between"><span style="color: #C3C9DE">Loan due by {{ $summary['due']['month_end']->format('j M') }}</span><span class="fw-semibold">{{ $tzs($summary['due']['loan']) }}</span></div>
                        @endcan
                    </div>
                    @if ($summary['due']['next_after'])
                        <div class="small mt-auto" style="color: #C3C9DE">Next after that: loan {{ $tzs($summary['due']['next_after']['amount']) }} on {{ $summary['due']['next_after']['due_date']->format('j M') }}</div>
                    @endif
                </div></div>
            </div>
        @endcan
    </div>
</div>

{{-- Statement + payment history --}}
@can('View Contributions')
    <div class="col-lg-12">
        <div class="card mb-3">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-statement" type="button" role="tab">Statement</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button" role="tab">Payment history</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-penalties" type="button" role="tab">Penalties</button>
                    </li>
                    @can('View Loans')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-loans" type="button" role="tab">Loans</button>
                        </li>
                    @endcan
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane fade show active p-3" id="tab-statement" role="tabpanel">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-auto">
                                <select name="year" class="form-select" onchange="this.form.submit()">
                                    @foreach ($years as $y)
                                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                        @include('contributions._statement_table', ['statement' => $statement])
                    </div>
                    <div class="tab-pane fade p-0" id="tab-payments" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Paid on</th>
                                        <th class="text-end">Amount</th>
                                        <th>Type</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                              
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                            <td class="text-end">{{ formatAmount($payment->amount) }}</td>
                                            <td>{{ $payment->paymentType?->name ?? '—' }}</td>
                                            <td>{{ $payment->paymentMethod?->name ?? '—' }}</td>
                                            <td>{{ $payment->reference ?: '—' }}</td>
                                  
                                            <td class="text-end" style="white-space: nowrap">
                                                @can('Manage Payments')
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#edit-payment"
                                                        data-action="{{ route('contributions.payments.update', $payment) }}"
                                                        data-member-id="{{ $payment->member_id }}" data-member-name="{{ $member->name }}"
                                                        data-payment-type-id="{{ $payment->payment_type_id }}" data-payment-method-id="{{ $payment->payment_method_id }}"
                                                        data-loan-id="{{ $payment->loan_id }}"
                                                        data-amount="{{ $payment->amount }}" data-paid-on="{{ $payment->paid_on->toDateString() }}"
                                                        data-reference="{{ $payment->reference }}" data-notes="{{ $payment->notes }}">Edit</button>
                                                    <form method="POST" action="{{ route('contributions.payments.destroy', $payment) }}" class="d-inline"
                                                        onsubmit="return confirm('Delete this payment of TZS {{ formatAmount($payment->amount) }}? The ledger will be rebuilt.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-muted p-3">No payments recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade p-0" id="tab-penalties" role="tabpanel">
                        @php
                            $penCharged = (float) $penaltyRecords->sum('amount');
                            $penPaid = (float) $penaltyRecords->sum('amount_paid');
                        @endphp
                        <div class="d-flex flex-wrap gap-4 px-3 pt-3 pb-2 small">
                            <div><span class="text-body-secondary">Charged</span> <span class="fw-bold">TZS {{ formatAmount($penCharged) }}</span></div>
                            <div><span class="text-body-secondary">Paid</span> <span class="fw-bold">TZS {{ formatAmount($penPaid) }}</span></div>
                            <div><span class="text-body-secondary">Outstanding</span> <span class="fw-bold {{ $penCharged - $penPaid > 0 ? 'text-danger' : '' }}">TZS {{ formatAmount($penCharged - $penPaid) }}</span></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Charged on</th>
                                        <th>For month</th>
                                        <th class="text-end">Unpaid fee (base)</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Penalty</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($penaltyRecords as $penalty)
                                        @php
                                            $rate = (float) ($penalty->due?->setting?->penalty_percent ?? 0);
                                            $base = $rate > 0 ? (float) $penalty->amount * 100 / $rate : null;
                                        @endphp
                                        <tr>
                                            <td>{{ $penalty->charged_on->format('j M Y') }}</td>
                                            <td>{{ $penalty->due?->period?->format('M Y') ?? '—' }}</td>
                                            <td class="text-end">{{ $base !== null ? formatAmount($base) : '—' }}</td>
                                            <td class="text-end">{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</td>
                                            <td class="text-end fw-semibold">{{ formatAmount($penalty->amount) }}</td>
                                            <td class="text-end">{{ formatAmount($penalty->amount_paid) }}</td>
                                            <td class="text-end {{ $penalty->balance() > 0 ? 'text-danger fw-semibold' : '' }}">{{ formatAmount($penalty->balance()) }}</td>
                                            <td>
                                                @if ($penalty->isFullyPaid())
                                                    <span class="badge text-bg-success">Paid</span>
                                                @elseif ((float) $penalty->amount_paid > 0)
                                                    <span class="badge text-bg-warning">Partly paid</span>
                                                @else
                                                    <span class="badge text-bg-danger">Unpaid</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-muted p-3">No penalties charged.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($penaltyRecords->isNotEmpty())
                                    <tfoot>
                                        <tr class="fw-bold">
                                            <td colspan="4">Total ({{ $penaltyRecords->count() }})</td>
                                            <td class="text-end">{{ formatAmount($penCharged) }}</td>
                                            <td class="text-end">{{ formatAmount($penPaid) }}</td>
                                            <td class="text-end">{{ formatAmount($penCharged - $penPaid) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                        <div class="px-3 py-2 small text-body-secondary">One row per unpaid month on each charge date. The base is the part of that month's fee still unpaid when the penalty was charged.</div>

                        @can('View Loans')
                            @if ($loanPenaltyRecords->isNotEmpty())
                                <div class="px-3 pt-3 pb-2 fw-semibold">Loan penalties</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead><tr><th>Charged on</th><th>Loan</th><th class="text-end">Penalty</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                                        <tbody>
                                            @foreach ($loanPenaltyRecords as $lp)
                                                <tr>
                                                    <td>{{ $lp->charged_on->format('j M Y') }}</td>
                                                    <td><a href="{{ route('loans.show', $lp->loan) }}">{{ $lp->loan->loanType->name }} · {{ formatAmount($lp->loan->effectiveAmount()) }}</a></td>
                                                    <td class="text-end">{{ formatAmount($lp->amount) }}</td>
                                                    <td class="text-end">{{ formatAmount($lp->amount_paid) }}</td>
                                                    <td class="text-end">{{ formatAmount($lp->balance()) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endcan
                    </div>

                    @can('View Loans')
                        <div class="tab-pane fade p-0" id="tab-loans" role="tabpanel">
                            <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Loan type</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Outstanding</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr>
                                    <td>{{ $loan->loanType->name }}</td>
                                    <td class="text-end">{{ formatAmount($loan->effectiveAmount()) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</td>
                                    <td class="text-end">{{ formatAmount($loan->outstandingBalance()) }}</td>
                                    <td class="text-end"><a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted p-3">No loans.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
</div>
                            <div class="px-3 pt-3 pb-2 fw-semibold">Guarantees given</div>
                            <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Borrower</th><th class="text-end">Guaranteed</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($guarantees as $guarantee)
                                <tr>
                                    <td>{{ $guarantee->loan->member->name }}</td>
                                    <td class="text-end">{{ formatAmount($guarantee->guaranteed_amount) }}</td>
                                    <td>{{ ucfirst($guarantee->status) }}</td>
                                    <td class="text-end"><a href="{{ route('loans.show', $guarantee->loan) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted p-3">No guarantees given.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
</div>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endcan

{{-- ===================== Modals ===================== --}}

@can('Record Payments')
    @include('contributions.payments._form', ['mode' => 'create', 'members' => [$member]])
@endcan
@can('Manage Payments')
    @include('contributions.payments._form', ['mode' => 'edit', 'members' => [$member]])
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | " + @json($member->name));

        @if ($errors->any() && old('_modal'))
            // Reopen the form that failed validation
            const failed = document.getElementById(@json(old('_modal')));
            if (failed) {
                bootstrap.Modal.getOrCreateInstance(failed).show();
            }
        @endif
    </script>
@endpush
