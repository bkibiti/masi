@extends("layouts.master")

@section('content-title')
    Contributions Register
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Contributions Register</li>
@endsection

@section("content")
@php
    $badgeClass = fn ($status) => match (true) {
        str_starts_with($status, 'Paid on time') => 'text-bg-success',
        str_starts_with($status, 'Paid late') => 'text-bg-warning',
        str_starts_with($status, 'Partly paid') => 'text-bg-info',
        str_starts_with($status, 'Unpaid') => 'text-bg-danger',
        str_starts_with($status, 'Current month') => 'text-bg-primary',
        str_starts_with($status, 'Not yet due') => 'text-bg-secondary',
        default => 'text-bg-light',
    };
    $shortBadge = fn ($status, $paid = 0, $penalty = 0) => match (true) {
        str_starts_with($status, 'Paid on time') => formatAmount($paid),
        str_starts_with($status, 'Paid late') => formatAmount($paid),
        str_starts_with($status, 'Partly paid') => formatAmount($paid),
        str_starts_with($status, 'Unpaid') => $penalty > 0 ? formatAmount($penalty) : 'Unpaid',
        str_starts_with($status, 'Current month') => 'This month',
        str_starts_with($status, 'Not yet due') => 'Not due',
        default => '—',
    };
@endphp

<div class="col-sm-6 col-xl-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Collected ({{ $year }})</div>
        <div class="fs-5 fw-bold">TZS {{ formatAmount($summary['collected']) }}</div>
    </div></div>
</div>
<div class="col-sm-6 col-xl-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Penalties ({{ $year }})</div>
        <div class="fs-5 fw-bold">TZS {{ formatAmount($summary['penalties']) }}</div>
    </div></div>
</div>
<div class="col-sm-6 col-xl-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Arrears</div>
        <div class="fs-5 fw-bold text-danger">TZS {{ formatAmount($summary['arrears']) }}</div>
    </div></div>
</div>
<div class="col-sm-6 col-xl-3 mb-3">
    <div class="card text-center"><div class="card-body">
        <div class="text-body-secondary small text-uppercase">Members in arrears</div>
        <div class="fs-5 fw-bold">{{ $summary['in_arrears'] }}</div>
    </div></div>
</div>

<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Register</h3>
            <div class="card-tools">
                @can('Record Payments')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#record-payment">
                        <i class="bi bi-plus-lg"></i> Record payment
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-2">
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or mobile" value="{{ $search }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table id="fixed-header" class="display table nowrap table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Member</th>
                            @foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $m)
                                <th>{{ $m }}</th>
                            @endforeach
                            <th class="text-end">Paid</th>
                            <th class="text-end">Penalties</th>
                            <th class="text-end">Arrears</th>
                            <th class="text-end">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><a href="{{ route('members.show', $row['member']->id) }}">{{ $row['member']->name }}</a></td>
                                @foreach ($row['months'] as $cell)
                                    <td>
                                        <span class="badge {{ $badgeClass($cell['status']) }}"
                                            title="Paid: TZS {{ formatAmount($cell['paid']) }} · Balance: TZS {{ formatAmount($cell['balance']) }} · Penalties: TZS {{ formatAmount($cell['penalty_total']) }} ({{ $cell['penalty_count'] }})">
                                            {{ $shortBadge($cell['status'], $cell['paid'], $cell['penalty_total']) }}
                                        </span>
                                    </td>
                                @endforeach
                                <td class="text-end">{{ formatAmount($row['paid_this_year']) }}</td>
                                <td class="text-end">{{ formatAmount($row['penalties_this_year']) }}</td>
                                <td class="text-end {{ $row['arrears'] > 0 ? 'text-danger fw-bold' : '' }}">{{ formatAmount($row['arrears']) }}</td>
                                <td class="text-end">{{ formatAmount($row['credit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@can('Record Payments')
    @include('contributions.payments._form', ['mode' => 'create', 'members' => $rows->pluck('member')])
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Contributions Register");
    </script>
@endpush
