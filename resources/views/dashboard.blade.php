@extends("layouts.master")

@section('page_css')
<style>
    .dashboard-card {
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        box-shadow: none;
    }

    .dashboard-card > .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .dashboard-label {
        color: var(--bs-secondary-color);
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .dashboard-figure {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .dash-chart-scroll {
        overflow-x: auto;
    }

    .dash-chart {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        min-width: 620px;
        padding-top: 1.75rem;
    }

    .dash-bar-col {
        flex: 1 0 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .dash-bar-value {
        font-size: 0.72rem;
        font-weight: 600;
        margin-bottom: 4px;
        white-space: nowrap;
    }

    .dash-bar-outline {
        width: 28px;
        height: 140px;
        border: 1px solid var(--bs-border-color);
        border-radius: 4px;
        display: flex;
        align-items: flex-end;
        overflow: hidden;
        background-color: rgba(0, 0, 0, 0.02);
    }

    .dash-bar-outline.dash-bar-empty {
        border-style: dashed;
        opacity: 0.5;
    }

    .dash-bar-fill {
        width: 100%;
        background-color: #1A2358;
        border-radius: 2px 2px 0 0;
    }

    .dash-bar-outline.dash-bar-current .dash-bar-fill {
        background-color: #E0633A;
    }

    .dash-bar-label {
        font-size: 0.7rem;
        color: var(--bs-secondary-color);
        margin-top: 4px;
    }

    .dash-legend-swatch {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: middle;
    }

    .dash-legend-collected {
        background-color: #1A2358;
    }

    .dash-legend-expected {
        border: 1px solid var(--bs-border-color);
    }

    .dash-ledger-badge-monthly_fee {
        background-color: #1A2358;
        color: #fff;
    }

    .dash-ledger-badge-penalty {
        background-color: #C4512B;
        color: #fff;
    }

    .dash-ledger-badge-none {
        background-color: #2F6B8A;
        color: #fff;
    }
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
    <div class="text-body-secondary small">{{ groupName() }} &middot; {{ now('Africa/Dar_es_Salaam')->format('l, j M Y') }}</div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label for="dashboard-year" class="form-label mb-0 small text-body-secondary">Year</label>
        <select id="dashboard-year" name="year" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- Summary cards --}}
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="card dashboard-card">
        <div class="card-body">
            <div class="dashboard-label">Total received {{ $year }}</div>
            <div class="dashboard-figure">TZS {{ formatAmount($totals['total']) }}</div>
            <div class="text-body-secondary small">All payment types &middot; TZS</div>
        </div>
    </div>
</div>
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="card dashboard-card">
        <div class="card-body">
            <div class="dashboard-label">Monthly fees collected</div>
            <div class="dashboard-figure">TZS {{ formatAmount($monthlyFees['collected']) }}</div>
            <div class="text-body-secondary small">
                @if ($monthlyFees['rate'] === null)
                    No closed months yet
                @else
                    {{ $monthlyFees['rate'] }}% collection rate, {{ $monthlyFees['range_label'] }}
                @endif
            </div>
        </div>
    </div>
</div>
<div class="col-sm-6 col-xl-4 mb-3">
    <div class="card dashboard-card">
        <div class="card-body">
            <div class="dashboard-label">Outstanding arrears</div>
            <div class="dashboard-figure text-danger">TZS {{ formatAmount($arrears['total']) }}</div>
            <div class="text-body-secondary small">TZS {{ formatAmount($arrears['fees']) }} fees + TZS {{ formatAmount($arrears['penalties']) }} penalties</div>
        </div>
    </div>
</div>

{{-- Chart + current month --}}
<div class="col-lg-8 mb-3">
    <div class="card dashboard-card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0">Monthly fees: collected vs expected, {{ $year }}</h6>
            <div class="small text-body-secondary">
                <span class="dash-legend-swatch dash-legend-collected"></span>Collected
                <span class="dash-legend-swatch dash-legend-expected ms-3"></span>Expected
            </div>
        </div>
        <div class="card-body">
            <div class="dash-chart-scroll">
                <div class="dash-chart">
                    @foreach ($chart['months'] as $m)
                        <div class="dash-bar-col" title="{{ $m['label'] }}: TZS {{ formatAmount($m['collected']) }} of TZS {{ formatAmount($m['expected']) }}">
                            @if ($m['has_dues'])
                                <div class="dash-bar-value">{{ formatCompactAmount($m['collected']) }}</div>
                                <div class="dash-bar-outline {{ $m['is_current'] ? 'dash-bar-current' : '' }}">
                                    <div class="dash-bar-fill" style="height: {{ $m['percent'] }}%"></div>
                                </div>
                            @else
                                <div class="dash-bar-value text-body-secondary">&ndash;</div>
                                <div class="dash-bar-outline dash-bar-empty"></div>
                            @endif
                            <div class="dash-bar-label">{{ $m['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
          
        </div>
    </div>
</div>

<div class="col-lg-4 mb-3">
    <div class="card dashboard-card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ $currentMonth['month_label'] }}</h6>
            <span class="badge text-bg-warning">{{ $currentMonth['due_badge'] }}</span>
        </div>
        <div class="card-body">
            <div class="mb-1">{{ $currentMonth['paid_members'] }} of {{ $currentMonth['total_members'] }} members paid</div>
            <div class="progress mb-1" style="height: 8px">
                <div class="progress-bar" style="width: {{ $currentMonth['percent'] }}%"></div>
            </div>
            <div class="small text-body-secondary mb-3">{{ $currentMonth['percent'] }}%</div>

            <div class="d-flex justify-content-between small mb-3">
                <span>Collected <strong>TZS {{ formatAmount($currentMonth['collected']) }}</strong></span>
                <span>Expected <strong>TZS {{ formatAmount($currentMonth['expected']) }}</strong></span>
            </div>

            @if ($currentMonth['setting'])
                <dl class="row small mb-3">
                    <dt class="col-6">Monthly fee</dt>
                    <dd class="col-6">TZS {{ formatAmount($currentMonth['setting']->monthly_amount) }}</dd>
                    <dt class="col-6">Penalty</dt>
                    <dd class="col-6">{{ rtrim(rtrim((string) $currentMonth['setting']->penalty_percent, '0'), '.') }}% per month unpaid</dd>
                   
                </dl>

            
            @else
                <div class="alert alert-secondary small mb-0">
                    No active contribution setting. <a href="{{ route('contribution-settings.index') }}">Masters &rsaquo; Contribution Settings</a>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Recent payments --}}
<div class="col-12">
    <div class="card dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Recent payments</h6>
            <a href="{{ route('contributions.payments.index') }}" class="small">All payments</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                <td><a href="{{ route('members.show', $payment->member_id) }}">{{ $payment->member?->name }}</a></td>
                                <td><span class="badge dash-ledger-badge-{{ $payment->paymentType?->ledger_role ?? 'none' }}">{{ $payment->paymentType?->name ?? '—' }}</span></td>
                                <td>{{ $payment->paymentMethod?->name ?? '—' }}</td>
                                <td>{{ $payment->reference ?: '—' }}</td>
                                <td class="text-end">TZS {{ formatAmount($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Dashboard");
    </script>
@endpush
