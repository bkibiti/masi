@extends("layouts.master")

@section('content-title')
    Loans
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Loans</li>
@endsection

@section("content")
<div class="col-12 d-flex justify-content-end gap-2 mb-3">
    @can('Manage Loans')
        <a href="{{ route('loans.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> New loan application</a>
    @endcan
</div>

<div class="col-md-6 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Disbursed (this tab)</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($totals['disbursed']) }}</div>
        </div>
    </div>
</div>
<div class="col-md-6 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Outstanding (this tab)</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($totals['outstanding']) }}</div>
        </div>
    </div>
</div>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                @foreach ($tabs as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('loans.index', array_filter(['tab' => $key, 'member_id' => $memberId, 'loan_type_id' => $loanTypeId])) }}">
                            {{ $label }} <span class="badge text-bg-light">{{ $counts[$key] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
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
                            <th class="text-end">Outstanding</th>
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
                                <td class="text-end">{{ formatAmount($loan->outstandingBalance()) }}</td>
                                <td>{{ $loan->applied_at?->format('j M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No loans in this tab.</td></tr>
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
