@extends("layouts.master")

@section('content-title')
    My Loans
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">My Loans</li>
@endsection

@section("content")
<div class="col-12 d-flex justify-content-end mb-3">
    @if ($hasActiveLoan)
        <button type="button" class="btn btn-primary" disabled title="You already have an active loan">
            <i class="bi bi-cash-coin"></i> Apply for a loan
        </button>
        <span class="text-muted small align-self-center ms-2">You already have an active loan; apply again once it's settled.</span>
    @else
        <a href="{{ route('account.loans.create') }}" class="btn btn-primary"><i class="bi bi-cash-coin"></i> Apply for a loan</a>
    @endif
</div>

<div class="col-12">
    <div class="card">
        <div class="card-body p-0">
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
                                <td class="text-end"><a href="{{ route('account.loans.show', $loan) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">You have no loans yet.</td></tr>
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
