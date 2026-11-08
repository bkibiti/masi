@extends("layouts.master")

@section('page_css')
<style>
    .ms-card { background: #fff; border: 1px solid #E2E5EC; border-radius: 12px; padding: 16px 18px; }
    .ms-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); }
    .ms-big { font-size: 1.4rem; font-weight: 700; line-height: 1.25; }
    .ms-avatar { width: 64px; height: 64px; border-radius: 50%; background: #0E1430; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; font-weight: 700; flex: none; }
    .ms-pill { font-size: .75rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-block; }
    .ms-pill-success { background: #E6F3EA; color: #1D6B3A; }
    .ms-pill-warning { background: #FDF0DC; color: #8A4B00; }
    .ms-pill-danger { background: #FBEDEA; color: #A12A1C; }
    .ms-pill-info { background: #E7F1FA; color: #0B5E94; }
    .ms-pill-secondary { background: #EEF0F4; color: #556072; }
    .ms-note { background: #FBF5EC; border: 1px solid #F1DCC6; border-radius: 10px; padding: 12px 14px; font-size: .875rem; line-height: 1.5; }
    .ms-tiles { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; }
    .ms-tile { border-radius: 6px; padding: 8px 0; text-align: center; font-size: .8rem; }
    @media (max-width: 575.98px) { .ms-tiles { grid-template-columns: repeat(6, 1fr); } }
    .ms-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #E6E9F0; font-size: .875rem; }
    .ms-row:last-child { border-bottom: 0; }
    .ms-dark { background: #0E1430; color: #fff; border: 0; }
    .ms-dark .ms-label, .ms-dark .ms-muted { color: #C3C9DE; }
</style>
@endsection

@section('content-title')
    My Statement
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">My Statement</li>
@endsection

@section("content")
@php
    $tzs = fn ($v) => formatAmount($v);
    $loan = $summary['loan'];
    $initials = collect(preg_split('/\s+/', trim($member->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<div class="col-12 d-flex justify-content-end mb-3">
    <a href="{{ route('account.statement.pdf', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download"></i> Download PDF
    </a>
</div>

{{-- Profile + status --}}
<div class="col-12 mb-3">
    <div class="ms-card d-flex flex-wrap align-items-center gap-3">
        <div class="ms-avatar">{{ mb_strtoupper($initials) }}</div>
        <div class="flex-grow-1" style="min-width: 240px">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fs-5 fw-bold">{{ $member->name }}</span>
                <span class="ms-pill ms-pill-{{ $status['tone'] }}">● {{ $status['label'] }}</span>
            </div>
            <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                @if ($member->phone)<span><i class="bi bi-telephone"></i> {{ $member->phone }}</span>@endif
                @if ($member->email)<span><i class="bi bi-envelope"></i> {{ $member->email }}</span>@endif
                @if ($member->created_at)<span>Member since {{ $member->created_at->format('M Y') }}</span>@endif
            </div>
        </div>
        <div class="ms-note flex-grow-1" style="min-width: 280px; max-width: 640px">
            <b>Your status:</b> {!! $status['message'] !!}
        </div>
    </div>
</div>

{{-- Four cards --}}
<div class="col-12 mb-3">
    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="ms-card h-100 d-flex flex-column gap-2">
                <div class="ms-label">Total contributed</div>
                <div class="ms-big">TZS {{ $tzs($summary['paid']['contributed']) }}</div>
                <div class="border-top pt-2 small d-flex flex-column gap-1">
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Monthly fees</span><span class="fw-semibold">{{ $tzs($summary['paid']['monthly_fees']) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Fines / penalties</span><span class="fw-semibold">{{ $tzs($summary['paid']['fines']) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Paid in {{ now()->year }}</span><span class="fw-semibold">{{ $tzs($contributedThisYear) }}</span></div>
                </div>
                @if ($summary['paid']['loans'] > 0)
                    <div class="small text-body-secondary mt-auto">Loan repayments ({{ $tzs($summary['paid']['loans']) }}) are not included.</div>
                @endif
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="ms-card h-100 d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="ms-label">Arrears</span>
                    <span class="ms-pill {{ $summary['arrears']['total'] > 0 ? 'ms-pill-danger' : 'ms-pill-success' }}">{{ $summary['arrears']['total'] > 0 ? 'In arrears' : 'Up to date' }}</span>
                </div>
                <div class="ms-big {{ $summary['arrears']['total'] > 0 ? 'text-danger' : '' }}">TZS {{ $tzs($summary['arrears']['total']) }}</div>
                <div class="border-top pt-2 small d-flex flex-column gap-1">
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Unpaid monthly fees</span><span class="fw-semibold">{{ $tzs($summary['arrears']['fees']) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Unpaid penalties</span><span class="fw-semibold">{{ $tzs($summary['arrears']['penalties']) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-body-secondary">Unpaid fines</span><span class="fw-semibold">{{ $tzs($summary['arrears']['fines']) }}</span></div>
                </div>
                @if ($summary['arrears']['credit'] > 0)
                    <div class="small text-body-secondary mt-auto">Credit (paid ahead): {{ $tzs($summary['arrears']['credit']) }}</div>
                @endif
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="ms-card h-100 d-flex flex-column gap-2">
                @if ($loan)
                    @php
                        $m = $loan['model'];
                        [$loanLabel, $loanTone] = match (true) {
                            $loan['released'] && ($loan['overdue'] || ($summary['due']['loan_overdue'] ?? 0) > 0) => ['Overdue', 'warning'],
                            $loan['released'] => ['Active', 'info'],
                            default => ['In progress', 'secondary'],
                        };
                    @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="ms-label">My loan</span>
                        <span class="ms-pill ms-pill-{{ $loanTone }}">{{ $loanLabel }}</span>
                    </div>
                    @if ($loan['released'])
                        <div class="ms-big">TZS {{ $tzs($loan['outstanding']) }} <span class="fs-6 fw-normal text-body-secondary">left</span></div>
                        <div class="progress" style="height: 8px"><div class="progress-bar" style="width: {{ $loan['progress'] }}%; background: #2A3A8C"></div></div>
                        <div class="small d-flex flex-column gap-1">
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">{{ $m->loanType?->name }} · {{ $tzs($loan['amount']) }}</span><span class="fw-semibold">{{ $loan['paid_count'] }} of {{ $loan['installment_count'] }} paid</span></div>
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">Repaid so far</span><span class="fw-semibold">{{ $tzs($m->installments->sum('amount_paid') + $m->penalties->sum('amount_paid')) }}</span></div>
                            @if ($m->periodEndDate())
                                <div class="d-flex justify-content-between"><span class="text-body-secondary">Loan ends</span><span class="fw-semibold">{{ $m->periodEndDate()->format('j M Y') }}</span></div>
                            @endif
                        </div>
                    @else
                        <div class="ms-big">TZS {{ $tzs($loan['amount']) }}</div>
                        <div class="small text-body-secondary">{{ $m->loanType?->name }} · {{ ucfirst(str_replace('_', ' ', $m->status)) }}</div>
                    @endif
                    <a href="{{ route('account.loans.show', $m) }}" class="small mt-auto">View loan &rarr;</a>
                @else
                    <div class="ms-label">My loan</div>
                    <div class="ms-big">No active loan</div>
                    <a href="{{ route('account.loans.create') }}" class="small mt-auto">Apply for a loan &rarr;</a>
                @endif
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="ms-card ms-dark h-100 d-flex flex-column gap-2">
                <div class="ms-label">To pay by {{ $summary['due']['month_end']->format('j M') }}</div>
                <div class="ms-big" style="color: var(--masi-orange-on-dark, #F08A5D)">TZS {{ $tzs($summary['due']['total']) }}</div>
                <div class="pt-2 small d-flex flex-column gap-1" style="border-top: 1px solid #232B52">
                    @if ($summary['due']['arrears'] > 0)
                        <div class="d-flex justify-content-between"><span class="ms-muted">Arrears (now)</span><span class="fw-semibold">{{ $tzs($summary['due']['arrears']) }}</span></div>
                    @endif
                    @if (($summary['due']['loan_overdue'] ?? 0) > 0)
                        <div class="d-flex justify-content-between"><span class="ms-muted">Loan overdue (now)</span><span class="fw-semibold">{{ $tzs($summary['due']['loan_overdue']) }}</span></div>
                    @endif
                    @foreach ($summary['due']['loan_upcoming'] ?? [] as $installment)
                        <div class="d-flex justify-content-between"><span class="ms-muted">Loan installment · {{ $installment['due_date']->format('j M') }}</span><span class="fw-semibold">{{ $tzs($installment['amount']) }}</span></div>
                    @endforeach
                    <div class="d-flex justify-content-between"><span class="ms-muted">{{ $summary['due']['month_end']->format('F') }} fee · {{ $summary['due']['month_end']->format('j M') }}</span><span class="fw-semibold">{{ $tzs($summary['due']['current_fee']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Year at a glance --}}
@php
    $tileStyle = fn ($status) => match (true) {
        str_starts_with($status, 'Paid on time') => 'background:#2E7D32;color:#fff',
        str_starts_with($status, 'Paid late') => 'background:#E08A00;color:#fff',
        str_starts_with($status, 'Partly paid'), str_starts_with($status, 'Unpaid') => 'background:#D32F2F;color:#fff',
        str_starts_with($status, 'Current month') => 'background:#fff;color:#172036;border:2px dashed #8C95C8;padding:6px 0',
        default => 'background:#E4E7EE;color:#6B7488',
    };
@endphp
<div class="col-12 mb-3">
    <div class="ms-card">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
            <h6 class="fw-bold mb-0">{{ $year }} at a glance</h6>
            <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                <span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background:#2E7D32"></span>Paid on time</span>
                <span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background:#E08A00"></span>Paid late</span>
                <span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background:#D32F2F"></span>Unpaid</span>
                <span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background:#E4E7EE"></span>Not yet due</span>
            </div>
        </div>
        <div class="ms-tiles">
            @foreach ($statement['months'] as $row)
                <div class="ms-tile" style="{{ $tileStyle($row['status']) }}" title="{{ $row['label'] }}: {{ $row['status'] }}">{{ \Illuminate\Support\Str::limit($row['label'], 3, '') }}</div>
            @endforeach
        </div>
    </div>
</div>

{{-- Coming up + guarantees --}}
<div class="col-lg-6 mb-3">
    <div class="ms-card h-100">
        <h6 class="fw-bold mb-2">Coming up</h6>
        @forelse ($upcoming as $item)
            <div class="ms-row">
                <span><b class="{{ $item['overdue'] ? 'text-danger' : '' }}">{{ $item['when'] }}</b> · {{ $item['label'] }}</span>
                <b>{{ $tzs($item['amount']) }}</b>
            </div>
        @empty
            <div class="small text-body-secondary">Nothing due in the next two months.</div>
        @endforelse
        <div class="small text-body-secondary mt-2">
            @if ($rules['fee_percent'] !== null)
                Unpaid monthly fees get a {{ rtrim(rtrim(number_format($rules['fee_percent'], 2), '0'), '.') }}% penalty from the 1st of the next month.
            @endif
            @if ($rules['loan_percent'] !== null)
                Loan penalties ({{ rtrim(rtrim(number_format($rules['loan_percent'], 2), '0'), '.') }}% a month) start after the loan period ends.
            @endif
        </div>
    </div>
</div>
<div class="col-lg-6 mb-3">
    <div class="ms-card h-100">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="fw-bold mb-0">Loans I guarantee</h6>
            <a href="{{ route('account.guarantees.index') }}" class="small">Guarantee requests &rarr;</a>
        </div>
        @forelse ($guarantees as $g)
            <div class="ms-row">
                <span>{{ $g['borrower'] }} · {{ $g['loan'] }}</span>
                <span class="text-nowrap"><b>{{ $tzs($g['amount']) }}</b> <span class="ms-pill ms-pill-{{ $g['tone'] }} ms-1">{{ $g['label'] }}</span></span>
            </div>
        @empty
            <div class="small text-body-secondary">You are not guaranteeing any loans.</div>
        @endforelse
        @if ($guarantees->isNotEmpty())
            <div class="small text-body-secondary mt-2">If a borrower you guarantee falls behind, you will see it here.</div>
        @endif
    </div>
</div>

{{-- Tabs --}}
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
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-fines" type="button" role="tab">Penalties &amp; fines</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-loans" type="button" role="tab">My loans</button>
                </li>
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
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted p-3">No payments recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade p-0" id="tab-fines" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($feeChargeRecords as $charge)
                                    <tr>
                                        <td>{{ $charge->date->format('j M Y') }}</td>
                                        <td>{{ $charge->type_label }}</td>
                                        <td>{{ $charge->reason ?: '—' }}</td>
                                        <td class="text-end">{{ formatAmount($charge->amount) }}</td>
                                        <td class="text-end">{{ formatAmount($charge->amount_paid) }}</td>
                                        <td class="text-end {{ $charge->balance > 0 ? 'text-danger fw-semibold' : '' }}">{{ formatAmount($charge->balance) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted p-3">No fines charged.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade p-0" id="tab-loans" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Loan type</th>
                                    <th class="text-end">Amount</th>
                                    <th>Released</th>
                                    <th>Status</th>
                                    <th class="text-end">Outstanding</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($loans as $l)
                                    <tr>
                                        <td>{{ $l->loanType?->name ?? '—' }}</td>
                                        <td class="text-end">{{ formatAmount($l->effectiveAmount()) }}</td>
                                        <td>{{ $l->disbursed_on ? $l->disbursed_on->format('j M Y') : '—' }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $l->status === 'disbursed' ? 'active' : $l->status)) }}</td>
                                        <td class="text-end">{{ in_array($l->status, ['disbursed', 'completed'], true) ? formatAmount($l->outstandingBalance()) : '—' }}</td>
                                        <td class="text-end"><a href="{{ route('account.loans.show', $l) }}" class="btn btn-outline-secondary btn-sm">View</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted p-3">No loans yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
