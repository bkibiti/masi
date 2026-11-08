@extends("layouts.master")

@section('page_css')
<style>
    .dashboard-card { background-color: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; box-shadow: none; }
    .dashboard-card > .card-header { background-color: transparent; border-bottom: 1px solid rgba(0,0,0,.06); }
    .dashboard-label, .gs-label { color: var(--bs-secondary-color); font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .gs-card { background: #fff; border: 1px solid #E2E5EC; border-radius: 12px; padding: 18px 20px; }
    .gs-hero { background: #0E1430; border-color: #0E1430; color: #fff; }
    .gs-hero .gs-label, .gs-hero .gs-note { color: #AEB6D3; }
    .gs-big { font-size: 1.6rem; font-weight: 700; margin: 6px 0 4px; line-height: 1.2; }
    .gs-note { font-size: .8rem; color: var(--bs-secondary-color); }
    .gs-split { display: flex; height: 24px; border-radius: 6px; overflow: hidden; gap: 2px; background: #E6E9F0; }
    .gs-dot { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
    .gs-tile { border-radius: 8px; padding: 10px 12px; }
    .gs-bars { display: flex; align-items: flex-end; gap: 6px; height: 90px; border-bottom: 1px solid #E6E9F0; }
    .gs-bar-col { flex: 1; height: 100%; display: flex; align-items: flex-end; }
    .gs-bar { width: 100%; border-radius: 3px 3px 0 0; min-height: 0; }
    .gs-bar-labels { gap: 6px; font-size: 11px; color: #8A93A5; }
    .gs-bar-labels span { flex: 1; text-align: center; }
    .gs-pill { display: inline-flex; align-items: center; gap: 6px; background: #F4F5F8; border-radius: 999px; padding: 4px 6px 4px 12px; font-size: .85rem; }
    .gs-count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; }
    .gs-table th { font-weight: 600; color: var(--bs-secondary-color); background: transparent !important; }
</style>
@endsection

@section('content-title')
    Dashboard
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section("content")
<div class="col-12 d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div class="text-body-secondary small">{{ groupName() }} &middot; as at {{ now('Africa/Dar_es_Salaam')->format('l, j M Y') }}</div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label for="dashboard-year" class="form-label mb-0 small text-body-secondary">Year</label>
        <select id="dashboard-year" name="year" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
</div>


@php
    $m = $status['money'];
    $p = $status['profit'];
    $c = $status['contributions'];
    $l = $status['loans'];
    $f = $status['fines'];
    $e = $status['expenses'];
    $countColors = ['Pending' => '#E08A00', 'Approved' => '#0B7BC1', 'Active' => '#1F3A68', 'Overdue' => '#D32F2F', 'Completed' => '#2E7D32'];
@endphp

{{-- Headline figures (as at today) --}}
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="gs-card gs-hero h-100">
        <div class="gs-label">Total group fund</div>
        <div class="gs-big">TZS {{ formatAmount($m['fund']) }}</div>
        <div class="gs-note">Cash available + money on loan</div>
    </div>
</div>
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="gs-card h-100" style="border-left: 5px solid #1D6B3A">
        <div class="gs-label">Cash available</div>
        <div class="gs-big">TZS {{ formatAmount($m['cash']) }}</div>
        <div class="gs-note">Payments + other income &minus; loans released &minus; expenses</div>
    </div>
</div>
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="gs-card h-100" style="border-left: 5px solid #C4512B">
        <div class="gs-label">Cash outside (on loan)</div>
        <div class="gs-big">TZS {{ formatAmount($m['outside']) }}</div>
        <div class="gs-note">Loan principal not yet repaid &middot; {{ $m['outside_loans'] }} {{ \Illuminate\Support\Str::plural('loan', $m['outside_loans']) }}</div>
    </div>
</div>

{{-- Where the fund is --}}
<div class="col-12 mb-3">
    <div class="gs-card">
        <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-2">
            <h6 class="mb-0 fw-bold">Where the group fund is</h6>
            <span class="small text-body-secondary">TZS {{ formatAmount($m['fund']) }}</span>
        </div>
        <div class="gs-split mb-2">
            <div style="flex: {{ max(0, $m['cash']) }}; background: #1D6B3A"></div>
            <div style="flex: {{ max(0, $m['outside']) }}; background: #C4512B"></div>
        </div>
        <div class="d-flex flex-wrap gap-4 small mb-3">
            <span class="d-flex align-items-center gap-2"><span class="gs-dot" style="background:#1D6B3A"></span><span class="text-body-secondary">Cash available</span><b>{{ formatAmount($m['cash']) }}</b><span class="text-body-secondary">{{ $m['cash_percent'] }}%</span></span>
            <span class="d-flex align-items-center gap-2"><span class="gs-dot" style="background:#C4512B"></span><span class="text-body-secondary">With members (loans)</span><b>{{ formatAmount($m['outside']) }}</b><span class="text-body-secondary">{{ 100 - $m['cash_percent'] }}%</span></span>
        </div>
        <div class="row g-2 small border-top pt-2">
            <div class="col-6 col-md"><div class="text-body-secondary">Monthly fees</div><b>+ {{ formatAmount($m['fees']) }}</b></div>
            <div class="col-6 col-md"><div class="text-body-secondary">Fines collected</div><b>+ {{ formatAmount($m['fines']) }}</b></div>
            <div class="col-6 col-md"><div class="text-body-secondary">Loan interest</div><b>+ {{ formatAmount($m['interest']) }}</b></div>
            <div class="col-6 col-md"><div class="text-body-secondary">Loan penalties</div><b>+ {{ formatAmount($m['loan_penalties']) }}</b></div>
            @if ($m['other_income'] > 0)
                <div class="col-6 col-md"><div class="text-body-secondary">Other income</div><b>+ {{ formatAmount($m['other_income']) }}</b></div>
            @endif
            @if (abs($m['other']) >= 1)
                <div class="col-6 col-md"><div class="text-body-secondary">Other / overpaid</div><b>{{ $m['other'] >= 0 ? '+' : '−' }} {{ formatAmount(abs($m['other'])) }}</b></div>
            @endif
            <div class="col-6 col-md"><div class="text-body-secondary">Expenses</div><b class="text-danger">&minus; {{ formatAmount($m['expenses']) }}</b></div>
            <div class="col-6 col-md"><div class="text-body-secondary">= Group fund</div><b>{{ formatAmount($m['fund']) }}</b></div>
        </div>
    </div>
</div>

{{-- Contributions --}}
<div class="col-lg-6 mb-3">
    <div class="gs-card h-100">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="mb-0 fw-bold">Contributions &middot; {{ $year }}</h6>
            @can('View Contributions')<a href="{{ route('contributions.register') }}" class="small">Details &rarr;</a>@endcan
        </div>
        <div class="d-flex justify-content-between small text-body-secondary mb-1">
            <span>Collected <b class="text-body">{{ formatAmount($c['collected']) }}</b> of {{ formatAmount($c['expected']) }} expected</span>
            <b class="text-body">{{ $c['percent'] }}%</b>
        </div>
        <div class="progress mb-3" style="height: 10px"><div class="progress-bar" style="width: {{ $c['percent'] }}%; background: #1A2358"></div></div>
        <div class="row g-2 mb-3">
            <div class="col-4"><div class="gs-tile" style="background:#EEF6F0"><div class="fs-4 fw-bold" style="color:#1D6B3A">{{ $c['up_to_date'] }}</div><div class="small text-body-secondary">Up to date</div></div></div>
            <div class="col-4"><div class="gs-tile" style="background:#FBEDEA"><div class="fs-4 fw-bold" style="color:#A12A1C">{{ $c['in_arrears'] }}</div><div class="small text-body-secondary">In arrears</div></div></div>
            <div class="col-4"><div class="gs-tile" style="background:#F4F5F8"><div class="fs-6 fw-bold pt-1">{{ formatAmount($c['arrears_total']) }}</div><div class="small text-body-secondary">Arrears now</div></div></div>
        </div>
        <div class="gs-label mb-1">Collected per month</div>
        <div class="gs-bars">
            @foreach ($c['months'] as $month)
                <div class="gs-bar-col" title="{{ $month['label'] }}: {{ formatAmount($month['collected']) }} of {{ formatAmount($month['expected']) }}">
                    <div class="gs-bar" style="height: {{ $month['height'] }}%; background: {{ $month['is_current'] ? '#8C95C8' : '#1A2358' }}"></div>
                </div>
            @endforeach
        </div>
        <div class="d-flex gs-bar-labels">
            @foreach ($c['months'] as $month)<span>{{ substr($month['label'], 0, 1) }}</span>@endforeach
        </div>
        @if ($c['current_label'])
            <div class="small text-body-secondary mt-2">{{ $c['current_label'] }} so far: {{ formatAmount($c['current_collected']) }} of {{ formatAmount($c['current_expected']) }} (month not ended)</div>
        @endif
    </div>
</div>

{{-- Loans --}}
<div class="col-lg-6 mb-3">
    <div class="gs-card h-100">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="mb-0 fw-bold">Loans</h6>
            @can('View Loans')<a href="{{ route('loans.index') }}" class="small">All loans &rarr;</a>@endcan
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach ($l['counts'] as $label => $count)
                <span class="gs-pill">{{ $label }} <span class="gs-count" style="background: {{ $count > 0 ? $countColors[$label] : '#E4E7EE' }}; color: {{ $count > 0 ? '#fff' : '#6B7488' }}">{{ $count }}</span></span>
            @endforeach
        </div>
        <div class="small d-flex flex-column gap-1 border-top pt-2 mb-3">
            <div class="d-flex justify-content-between"><span class="text-body-secondary">Released in {{ $year }}</span><b>{{ formatAmount($l['released_in_year']) }}</b></div>
            <div class="d-flex justify-content-between"><span class="text-body-secondary">Repaid so far (all loans)</span><b>{{ formatAmount($l['repaid']) }}</b></div>
            <div class="d-flex justify-content-between"><span class="text-body-secondary">Outstanding (total)</span><b>{{ formatAmount($l['outstanding']) }}</b></div>
            <div class="d-flex justify-content-between ps-3"><span class="text-body-secondary">Principal</span><span>{{ formatAmount($l['principal']) }}</span></div>
            <div class="d-flex justify-content-between ps-3"><span class="text-body-secondary">Interest due</span><span>{{ formatAmount($l['interest_due']) }}</span></div>
            <div class="d-flex justify-content-between ps-3"><span class="text-body-secondary">Unpaid penalties</span><span>{{ formatAmount($l['penalties_unpaid']) }}</span></div>
        </div>
        @if ($l['overdue']->isNotEmpty())
            <div class="gs-tile small mb-2" style="background:#FBEDEA">
                <div class="fw-bold mb-1" style="color:#A12A1C">Overdue &middot; {{ formatAmount($l['overdue_total']) }}</div>
                @foreach ($l['overdue'] as $loan)
                    <div class="d-flex justify-content-between gap-2">
                        @can('View Loans')<a href="{{ route('loans.show', $loan['id']) }}" class="text-body">{{ $loan['member'] }}</a>@else<span>{{ $loan['member'] }}</span>@endcan
                        <span>{{ formatAmount($loan['outstanding']) }} @if ($loan['since']) &middot; since {{ $loan['since']->format('j M') }}@endif</span>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="small text-body-secondary">Next 30 days: {{ $l['soon_count'] }} {{ \Illuminate\Support\Str::plural('installment', $l['soon_count']) }} due &middot; {{ formatAmount($l['soon_total']) }}</div>
    </div>
</div>

{{-- Loan profit --}}
<div class="col-lg-4 mb-3">
    <div class="gs-card h-100">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="mb-0 fw-bold">Loan profit</h6>
            <span class="small text-body-secondary">Interest + loan penalties</span>
        </div>
        <div class="d-flex justify-content-between small text-body-secondary mb-1">
            <span>Earned <b class="text-body">{{ formatAmount($p['earned']) }}</b></span>
            <span>Expected <b class="text-body">{{ formatAmount($p['expected']) }}</b></span>
        </div>
        <div class="gs-split mb-3" style="height: 14px; border-radius: 999px; gap: 0">
            <div style="width: {{ $p['interest_pct'] }}%; background: #2A3A8C"></div>
            <div style="width: {{ $p['penalties_pct'] }}%; background: #6B78C2"></div>
            <div style="width: {{ $p['due_pct'] }}%; background: repeating-linear-gradient(45deg, #D7DBEA 0 6px, #EEF0F6 6px 12px)"></div>
        </div>
        <div class="small d-flex flex-column gap-1">
            <div class="d-flex justify-content-between"><span class="d-flex align-items-center gap-2"><span class="gs-dot" style="background:#2A3A8C"></span>Interest received</span><b>{{ formatAmount($p['interest']) }}</b></div>
            <div class="d-flex justify-content-between"><span class="d-flex align-items-center gap-2"><span class="gs-dot" style="background:#6B78C2"></span>Loan penalties received</span><b>{{ formatAmount($p['penalties']) }}</b></div>
            <div class="d-flex justify-content-between"><span class="d-flex align-items-center gap-2"><span class="gs-dot" style="background:#D7DBEA"></span>Interest still to come</span><b>{{ formatAmount($p['interest_due']) }}</b></div>
        </div>
        <div class="small d-flex justify-content-between border-top pt-2 mt-2">
            <span class="text-body-secondary">Unpaid loan penalties (not counted)</span>
            <span class="fw-semibold {{ $p['penalties_unpaid'] > 0 ? 'text-danger' : '' }}">{{ formatAmount($p['penalties_unpaid']) }}</span>
        </div>
    </div>
</div>

{{-- Fines --}}
<div class="col-lg-4 mb-3">
    <div class="gs-card h-100">
        <h6 class="mb-2 fw-bold">Fines &amp; penalties &middot; {{ $year }}</h6>
        @if ($f['rows']->isEmpty())
            <div class="small text-body-secondary">No fines or penalties charged in {{ $year }}.</div>
        @else
            <table class="table table-sm small mb-2 gs-table">
                <thead><tr><th>Type</th><th class="text-end">Charged</th><th class="text-end">Paid</th><th class="text-end">Unpaid</th></tr></thead>
                <tbody>
                    @foreach ($f['rows'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ formatAmount($row['charged']) }}</td><td class="text-end">{{ formatAmount($row['paid']) }}</td><td class="text-end {{ $row['unpaid'] > 0 ? 'text-danger' : '' }}">{{ formatAmount($row['unpaid']) }}</td></tr>
                    @endforeach
                    <tr class="fw-bold"><td>Total</td><td class="text-end">{{ formatAmount($f['charged']) }}</td><td class="text-end">{{ formatAmount($f['paid']) }}</td><td class="text-end {{ $f['unpaid'] > 0 ? 'text-danger' : '' }}">{{ formatAmount($f['unpaid']) }}</td></tr>
                </tbody>
            </table>
        @endif
        <div class="small text-body-secondary">Loan penalties are counted under Loan profit.</div>
    </div>
</div>

{{-- Expenses --}}
<div class="col-lg-4 mb-3">
    <div class="gs-card h-100">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="mb-0 fw-bold">Expenses &middot; {{ $year }}</h6>
            <span class="fs-5 fw-bold">{{ formatAmount($e['total']) }}</span>
        </div>
        @forelse ($e['categories'] as $cat)
            <div class="small mb-2">
                <div class="d-flex justify-content-between"><span>{{ $cat['name'] }}</span><b>{{ formatAmount($cat['total']) }}</b></div>
                <div class="progress" style="height: 7px"><div class="progress-bar" style="width: {{ $cat['percent'] }}%; background: #C4512B"></div></div>
            </div>
        @empty
            <div class="small text-body-secondary">No expenses recorded in {{ $year }}.</div>
        @endforelse
        @if ($e['latest']->isNotEmpty())
            <div class="border-top pt-2 mt-2 small">
                <div class="gs-label mb-1">Latest</div>
                @foreach ($e['latest'] as $item)
                    <div class="d-flex justify-content-between gap-2"><span class="text-truncate">{{ $item['date']->format('j M') }} &middot; {{ $item['description'] }}</span><span>{{ formatAmount($item['amount']) }}</span></div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="col-12 mb-3">
    <div class="gs-card small" style="background:#FFF8F0; border-color:#F1DCC6">
        <b>How the numbers are worked out.</b>
        Group fund = monthly fees + fines collected + loan interest + loan penalties + other income &minus; expenses.
        Cash outside = loan principal not yet repaid.
        Cash available = group fund &minus; cash outside.
        Loan profit = interest and loan penalties actually received; expected adds the interest still scheduled on active loans.
        The year selector changes contributions, loans released, fines and expenses; the other figures are as at today.
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
