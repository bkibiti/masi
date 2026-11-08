@extends("layouts.master")

@section('content-title')
    Loans
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Loans</li>
@endsection

@section('page_css')
<style>
    .lo-card { background: #fff; border: 1px solid #E2E5EC; border-radius: 12px; padding: 16px 18px; height: 100%; }
    .lo-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); }
    .lo-big { font-size: 1.35rem; font-weight: 700; line-height: 1.25; margin: 4px 0; }
    .lo-note { font-size: .78rem; color: var(--bs-secondary-color); }
    .lo-split { display: flex; height: 22px; border-radius: 6px; overflow: hidden; gap: 2px; background: #E6E9F0; }
    .lo-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; }
    .lo-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px solid #E6E9F0; font-size: .85rem; }
    .lo-row:last-child { border-bottom: 0; }
    .lo-stat { border-radius: 8px; padding: 10px 4px; text-align: center; }
    .lo-bars { display: flex; align-items: flex-end; gap: 8px; height: 130px; border-bottom: 1px solid #E6E9F0; }
    .lo-bar-col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 2px; }
    .lo-bar { width: 100%; background: #1A2358; border-radius: 3px 3px 0 0; }
    .lo-bar-labels { display: flex; gap: 8px; font-size: 11px; color: #8A93A5; }
    .lo-bar-labels span { flex: 1; text-align: center; }
</style>
@endsection

@section("content")
@php
    $o = $overview;
    $fmt = fn ($v) => formatAmount($v);
    $millions = fn ($v) => $v > 0 ? number_format($v / 1000000, 1) : '—';
@endphp
<div class="col-12 d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
    <form method="GET" class="d-flex align-items-center gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($memberId)<input type="hidden" name="member_id" value="{{ $memberId }}">@endif
        @if ($loanTypeId)<input type="hidden" name="loan_type_id" value="{{ $loanTypeId }}">@endif
        <label for="lo-year" class="small text-body-secondary mb-0">Released in</label>
        <select id="lo-year" name="year" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
            <option value="all" @selected($overviewYear === null)>All years</option>
            @foreach ($overviewYears as $y)
                <option value="{{ $y }}" @selected($overviewYear === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
    @can('Manage Loans')
        <a href="{{ route('loans.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> New loan application</a>
    @endcan
</div>

{{-- Headline cards --}}
<div class="col-12 mb-3">
    <div class="row g-3">
        <div class="col-sm-6 col-xl">
            <div class="lo-card" style="background:#0E1430;border-color:#0E1430;color:#fff">
                <div class="lo-label" style="color:#AEB6D3">Total released</div>
                <div class="lo-big">{{ $fmt($o['released']) }}</div>
                <div class="lo-note" style="color:#AEB6D3">{{ $o['count'] }} {{ \Illuminate\Support\Str::plural('loan', $o['count']) }} · average {{ $fmt(round($o['average'], -2)) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="lo-card" style="border-left:5px solid #2E7D32">
                <div class="lo-label">Repaid</div>
                <div class="lo-big">{{ $fmt($o['repaid']) }}</div>
                <div class="lo-note">Principal back: {{ $fmt($o['principal_back']) }} ({{ $o['principal_back_pct'] }}%)</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="lo-card" style="border-left:5px solid #C4512B">
                <div class="lo-label">Still to collect</div>
                <div class="lo-big">{{ $fmt($o['still_to_collect']) }}</div>
                <div class="lo-note">Principal {{ $fmt($o['principal_outstanding']) }} · interest {{ $fmt($o['interest_due']) }} · penalties {{ $fmt($o['penalties_unpaid']) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="lo-card" style="border-left:5px solid #2A3A8C">
                <div class="lo-label">Profit earned</div>
                <div class="lo-big">{{ $fmt($o['profit_earned']) }}</div>
                <div class="progress mb-1" style="height:6px"><div class="progress-bar" style="width: {{ $o['profit_pct'] }}%; background:#2A3A8C"></div></div>
                <div class="lo-note">{{ $o['profit_pct'] }}% of <b class="text-body">{{ $fmt($o['profit_expected']) }}</b> expected</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="lo-card" style="background:#FBEDEA;border-color:#F3CFC7">
                <div class="lo-label" style="color:#A12A1C">Overdue</div>
                <div class="lo-big" style="color:#A12A1C">{{ $fmt($o['overdue_total']) }}</div>
                <div class="lo-note" style="color:#7A3328">{{ $o['overdue']->count() }} {{ \Illuminate\Support\Str::plural('loan', $o['overdue']->count()) }} · {{ $o['overdue_share'] }}% of what is still to collect</div>
            </div>
        </div>
    </div>
</div>

{{-- Where the money is + status --}}
<div class="col-lg-6 mb-3">
    <div class="lo-card d-flex flex-column gap-3">
        <h6 class="fw-bold mb-0">Where the loan money is</h6>
        <div>
            <div class="small text-body-secondary mb-1">Principal released · {{ $fmt($o['released']) }}</div>
            <div class="lo-split mb-2">
                <div style="flex: {{ $o['principal_back'] }}; background:#2E7D32"></div>
                <div style="flex: {{ $o['principal_on_track'] }}; background:#2A3A8C"></div>
                <div style="flex: {{ $o['principal_overdue'] }}; background:#D32F2F"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 small">
                <span><span class="lo-dot" style="background:#2E7D32"></span>Back in the group <b>{{ $fmt($o['principal_back']) }}</b></span>
                <span><span class="lo-dot" style="background:#2A3A8C"></span>With members, on track <b>{{ $fmt($o['principal_on_track']) }}</b></span>
                <span><span class="lo-dot" style="background:#D32F2F"></span>In overdue loans <b>{{ $fmt($o['principal_overdue']) }}</b></span>
            </div>
        </div>
        <div class="border-top pt-3">
            <div class="d-flex justify-content-between small mb-1"><span class="text-body-secondary">Profit · interest + loan penalties</span><span>Earned <b>{{ $fmt($o['profit_earned']) }}</b> of <b>{{ $fmt($o['profit_expected']) }}</b></span></div>
            <div class="lo-split mb-2">
                <div style="flex: {{ $o['interest_received'] }}; background:#2A3A8C"></div>
                <div style="flex: {{ $o['penalties_received'] }}; background:#6B78C2"></div>
                <div style="flex: {{ $o['interest_due'] }}; background: repeating-linear-gradient(45deg, #D7DBEA 0 6px, #EEF0F6 6px 12px)"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 small">
                <span><span class="lo-dot" style="background:#2A3A8C"></span>Interest received <b>{{ $fmt($o['interest_received']) }}</b></span>
                <span><span class="lo-dot" style="background:#6B78C2"></span>Penalties received <b>{{ $fmt($o['penalties_received']) }}</b></span>
                <span><span class="lo-dot" style="background:#D7DBEA"></span>Interest to come <b>{{ $fmt($o['interest_due']) }}</b></span>
            </div>
            <div class="small text-body-secondary mt-2">
                @if ($o['penalties_unpaid'] > 0)Plus <b class="text-danger">{{ $fmt($o['penalties_unpaid']) }}</b> in unpaid loan penalties (not counted until paid) · @endif
                return so far <b class="text-body">{{ $o['return_pct'] }}%</b> of money lent
            </div>
        </div>
    </div>
</div>
<div class="col-lg-6 mb-3">
    <div class="lo-card d-flex flex-column gap-3">
        <h6 class="fw-bold mb-0">Loan status</h6>
        <div class="row g-2">
            <div class="col-3"><div class="lo-stat" style="background:#F4F5F8"><div class="fs-4 fw-bold" style="color:#E08A00">{{ $counts['pending_guarantors'] + $counts['pending_approval'] + $counts['approved'] }}</div><div class="small text-body-secondary">Pending</div></div></div>
            <div class="col-3"><div class="lo-stat" style="background:#EEF1FA"><div class="fs-4 fw-bold" style="color:#1F3A68">{{ $counts['active'] }}</div><div class="small text-body-secondary">Active</div></div></div>
            <div class="col-3"><div class="lo-stat" style="background:#FBEDEA"><div class="fs-4 fw-bold" style="color:#D32F2F">{{ $counts['overdue'] }}</div><div class="small text-body-secondary">Overdue</div></div></div>
            <div class="col-3"><div class="lo-stat" style="background:#EEF6F0"><div class="fs-4 fw-bold" style="color:#2E7D32">{{ $counts['completed'] }}</div><div class="small text-body-secondary">Completed</div></div></div>
        </div>
        <div>
            <div class="d-flex justify-content-between align-items-baseline border-top pt-2 mb-1">
                <b class="small">Due in the next 30 days</b>
                <span class="small text-body-secondary">{{ $o['soon']->count() }} {{ \Illuminate\Support\Str::plural('installment', $o['soon']->count()) }} · <b class="text-body">{{ $fmt($o['soon_total']) }}</b></span>
            </div>
            @forelse ($o['soon']->take(4) as $item)
                <div class="lo-row">
                    <span><b>{{ $item['due_date']->format('j M') }}</b> · <a href="{{ route('loans.show', $item['loan']) }}" class="text-body">{{ $item['member'] }}</a>{{ $item['last'] ? ' (last)' : '' }}</span>
                    <b>{{ $fmt($item['amount']) }}</b>
                </div>
            @empty
                <div class="small text-body-secondary">Nothing due in the next 30 days.</div>
            @endforelse
            @if ($o['soon']->count() > 4)
                <div class="small text-body-secondary pt-1">+ {{ $o['soon']->count() - 4 }} more</div>
            @endif
        </div>
        @if ($o['overdue']->isNotEmpty())
            <div class="rounded-2 p-2 px-3 small" style="background:#FBEDEA">
                <b style="color:#A12A1C">Overdue loans</b>
                @foreach ($o['overdue'] as $row)
                    <div class="d-flex justify-content-between gap-2">
                        <a href="{{ route('loans.show', $row['loan']) }}" class="text-body">{{ $row['loan']->member->name }}@if ($row['since']) · since {{ $row['since']->format('j M') }}@endif</a>
                        <span>{{ $fmt($row['outstanding']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Released per month --}}
<div class="col-12 mb-3">
    <div class="lo-card">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <h6 class="fw-bold mb-0">Loans released per month · {{ $o['chart_year'] }}</h6>
            <span class="small text-body-secondary">TZS millions</span>
        </div>
        <div class="lo-bars">
            @foreach ($o['months'] as $month)
                <div class="lo-bar-col" title="{{ $month['label'] }}: {{ $fmt($month['amount']) }}">
                    <span class="small text-body-secondary" style="font-size:11px">{{ $millions($month['amount']) }}</span>
                    <div class="lo-bar" style="height: {{ $month['height'] }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="lo-bar-labels">@foreach ($o['months'] as $month)<span>{{ $month['label'] }}</span>@endforeach</div>
    </div>
</div>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                @php
                    $tabColors = [
                        'pending_guarantors' => 'warning',
                        'pending_approval' => 'warning',
                        'approved' => 'info',
                        'active' => 'primary',
                        'overdue' => 'danger',
                        'completed' => 'success',
                        'rejected_cancelled' => 'secondary',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link d-inline-flex align-items-center {{ $tab === $key ? 'active' : '' }}" href="{{ route('loans.index', array_filter(['tab' => $key, 'member_id' => $memberId, 'loan_type_id' => $loanTypeId, 'year' => $overviewYear])) }}">
                            {{ $label }}
                            <span class="tab-count tab-count-{{ $counts[$key] > 0 ? ($tabColors[$key] ?? 'primary') : 'zero' }}">{{ $counts[$key] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($overviewYear)<input type="hidden" name="year" value="{{ $overviewYear }}">@endif
                <div class="col-auto">
                    <select name="member_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All members</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected((string) $memberId === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="loan_type_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All loan types</option>
                        @foreach ($loanTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) $loanTypeId === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Loan type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Duration</th>
                            <th class="text-end">{{ $tab === 'completed' ? 'Total paid' : 'Total to pay' }}</th>
                            <th style="min-width: 150px">Repaid</th>
                            <th class="text-end">Outstanding</th>
                            <th>Next due</th>
                            <th>Applied</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $loan)
                            <tr>
                                <td>{{ $loan->member->name }}</td>
                                <td>{{ $loan->loanType->name }}</td>
                                <td class="text-end">{{ formatAmount($loan->effectiveAmount()) }}</td>
                                <td class="text-end">{{ $loan->effectiveDurationMonths() }} mo</td>
                                <td class="text-end">{{ $loan->installments->isNotEmpty() ? formatAmount($loan->totalPayable()) : '—' }}</td>
                                <td>
                                    @if ($loan->installments->isNotEmpty())
                                        @php $pct = $overviewService->repaidPercent($loan); @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px"><div class="progress-bar" style="width: {{ $pct }}%; background: #2A3A8C"></div></div>
                                            <span class="small text-body-secondary">{{ $pct }}%</span>
                                        </div>
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ formatAmount($loan->outstandingBalance()) }}</td>
                                @php $next = $overviewService->nextDue($loan); @endphp
                                <td class="{{ $next['overdue'] ? 'text-danger fw-semibold' : '' }}" style="white-space: nowrap">{{ $next['text'] }}</td>
                                <td>{{ $loan->applied_at?->format('j M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-muted p-3">No loans in this tab.</td></tr>
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
@endpush
