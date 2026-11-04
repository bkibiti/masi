@extends("layouts.master")

@section('content-title')
    My Statement
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">My Statement</li>
@endsection

@section("content")
<div class="col-12 d-flex justify-content-end mb-3">
    <a href="{{ route('account.statement.pdf', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download"></i> Download PDF
    </a>
</div>

<div class="col-md-3 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Paid this year</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($statement['year_totals']['paid']) }}</div>
        </div>
    </div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Penalties paid / owed</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($penalties_paid) }} / TZS {{ formatAmount($penalties_owed) }}</div>
        </div>
    </div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Arrears</div>
            <div class="fs-5 fw-bold {{ $statement['arrears'] > 0 ? 'text-danger' : '' }}">TZS {{ formatAmount($statement['arrears']) }}</div>
        </div>
    </div>
</div>
<div class="col-md-3 mb-3">
    <div class="card text-center">
        <div class="card-body">
            <div class="text-body-secondary small text-uppercase">Credit</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($statement['credit']) }}</div>
        </div>
    </div>
</div>

@if ($next_due)
    <div class="col-12 mb-3">
        <div class="alert alert-info mb-0">
            Next due: <strong>{{ $next_due->period->format('F Y') }}</strong>, TZS {{ formatAmount($next_due->balance()) }} on {{ $next_due->due_date->format('j M Y') }}.
        </div>
    </div>
@endif

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
            </div>
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
