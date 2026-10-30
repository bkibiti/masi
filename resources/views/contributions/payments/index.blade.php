@extends("layouts.master")

@section('content-title')
    Payments
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('contributions.register') }}">Contributions</a></li>
    <li class="breadcrumb-item active">Payments</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payments</h3>
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
                    <input type="date" name="from_date" class="form-control" value="{{ $from }}" placeholder="From">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to_date" class="form-control" value="{{ $to }}" placeholder="To">
                </div>
                <div class="col-md-3">
                    <select name="member_id" class="form-select">
                        <option value="">All members</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected((string) $memberId === (string) $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table id="fixed-header" class="display table nowrap table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Paid on</th>
                            <th>Member</th>
                            <th class="text-end">Amount</th>
                            <th>Reference</th>
                            <th>Recorded by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                <td><a href="{{ route('members.show', $payment->member_id) }}">{{ $payment->member?->name }}</a></td>
                                <td class="text-end">{{ formatAmount($payment->amount) }}</td>
                                <td>{{ $payment->reference ?: '—' }}</td>
                                <td>{{ $payment->recorder?->name ?? '—' }}</td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Payments')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#edit-payment"
                                            data-action="{{ route('contributions.payments.update', $payment) }}"
                                            data-member-id="{{ $payment->member_id }}"
                                            data-amount="{{ $payment->amount }}" data-paid-on="{{ $payment->paid_on->toDateString() }}"
                                            data-reference="{{ $payment->reference }}" data-notes="{{ $payment->notes }}">Edit</button>
                                        <form method="POST" action="{{ route('contributions.payments.destroy', $payment) }}" class="d-inline"
                                            onsubmit="return confirm('Delete this payment of TZS {{ formatAmount($payment->amount) }} for {{ addslashes($payment->member?->name) }}? The ledger will be rebuilt.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@can('Record Payments')
    @include('contributions.payments._form', ['mode' => 'create', 'members' => $members])
@endcan
@can('Manage Payments')
    @include('contributions.payments._form', ['mode' => 'edit', 'members' => $members])
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Payments");
    </script>
@endpush
