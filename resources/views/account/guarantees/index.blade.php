@extends("layouts.master")

@section('content-title')
    Guarantee Requests
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Guarantee Requests</li>
@endsection

@section("content")
<div class="col-12">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Waiting for your response</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Applicant</th><th>Loan type</th><th class="text-end">Loan amount</th><th class="text-end">Your guarantee</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($pending as $guarantee)
                            <tr>
                                <td>{{ $guarantee->loan->member->name }}</td>
                                <td>{{ $guarantee->loan->loanType->name }}</td>
                                <td class="text-end">{{ formatAmount($guarantee->loan->effectiveAmount()) }}</td>
                                <td class="text-end">{{ formatAmount($guarantee->guaranteed_amount) }}</td>
                                <td class="text-end" style="white-space: nowrap">
                                    <form method="POST" action="{{ route('account.guarantees.accept', $guarantee) }}" class="d-inline"
                                        onsubmit="return confirm('Accept this guarantee request for TZS {{ formatAmount($guarantee->guaranteed_amount) }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm">Accept</button>
                                    </form>
                                    <form method="POST" action="{{ route('account.guarantees.decline', $guarantee) }}" class="d-inline"
                                        onsubmit="return confirm('Decline this guarantee request?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Decline</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No guarantee requests waiting for you.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Guarantees you've given</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Applicant</th><th>Loan type</th><th class="text-end">Your guarantee</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($responded as $guarantee)
                            <tr>
                                <td>{{ $guarantee->loan->member->name }}</td>
                                <td>{{ $guarantee->loan->loanType->name }}</td>
                                <td class="text-end">{{ formatAmount($guarantee->guaranteed_amount) }}</td>
                                <td>
                                    <span class="badge {{ $guarantee->status === 'accepted' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($guarantee->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No guarantees given yet.</td></tr>
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
