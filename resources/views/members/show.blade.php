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
{{-- Actions --}}
<div class="col-12 d-flex justify-content-end gap-2 mb-3">
    @can('Record Payments')
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#record-payment"
            data-member-id="{{ $member->id }}" data-member-name="{{ $member->name }}">
            <i class="bi bi-cash-coin"></i> Record payment
        </button>
    @endcan
</div>

{{-- Arrears + credit --}}
@can('View Contributions')
    <div class="col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-body-secondary small text-uppercase">Arrears</div>
                <div class="fs-5 fw-bold {{ $statement['arrears'] > 0 ? 'text-danger' : '' }}">TZS {{ formatAmount($statement['arrears']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-body-secondary small text-uppercase">Credit</div>
                <div class="fs-5 fw-bold">TZS {{ formatAmount($statement['credit']) }}</div>
            </div>
        </div>
    </div>
@endcan

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
                                        <th>Reference</th>
                                        <th>Recorded by</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                            <td class="text-end">{{ formatAmount($payment->amount) }}</td>
                                            <td>{{ $payment->reference ?: '—' }}</td>
                                            <td>{{ $payment->recorder?->name ?? '—' }}</td>
                                            <td class="text-end" style="white-space: nowrap">
                                                @can('Manage Payments')
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#edit-payment"
                                                        data-action="{{ route('contributions.payments.update', $payment) }}"
                                                        data-member-id="{{ $payment->member_id }}" data-member-name="{{ $member->name }}"
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
