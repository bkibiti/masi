@extends("layouts.master")

@section('content-title')
    Loan Types
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Loan Types</li>
@endsection

@section("content")
@php
    $methodLabel = fn ($method) => $method === 'fixed' ? 'Fixed (once, on principal)' : 'Monthly reducing';
@endphp

<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Loan Types</h3>
            <div class="card-tools">
                @can('Manage Loan Types')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#loan-type-modal"
                        data-action="{{ route('loan-types.store') }}" data-method="POST" data-title="New loan type" data-locked="0">
                        <i class="bi bi-plus-lg"></i> Add loan type
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Method</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount range</th>
                            <th class="text-end">Duration</th>
                            <th class="text-end">Penalty %/mo</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loanTypes as $type)
                            <tr>
                                <td>{{ $type->name }}</td>
                                <td>{{ $methodLabel($type->interest_method) }}</td>
                                <td class="text-end">{{ formatAmount($type->interest_rate) }}%</td>
                                <td class="text-end">{{ formatAmount($type->min_amount) }} – {{ formatAmount($type->max_amount) }}</td>
                                <td class="text-end">{{ $type->min_duration_months }} – {{ $type->max_duration_months }} mo</td>
                                <td class="text-end">{{ formatAmount($type->penalty_percent) }}%</td>
                                <td><span class="badge {{ $type->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Loan Types')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#loan-type-modal"
                                            data-action="{{ route('loan-types.update', $type) }}" data-method="PUT" data-title="Edit loan type"
                                            data-locked="{{ $type->isInUse() ? 1 : 0 }}"
                                            data-name="{{ $type->name }}" data-description="{{ $type->description }}"
                                            data-method-value="{{ $type->interest_method }}" data-rate="{{ $type->interest_rate }}"
                                            data-min-amount="{{ $type->min_amount }}" data-max-amount="{{ $type->max_amount }}"
                                            data-min-months="{{ $type->min_duration_months }}" data-max-months="{{ $type->max_duration_months }}"
                                            data-penalty="{{ $type->penalty_percent }}" data-active="{{ $type->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        @if (! $type->isInUse())
                                            <form method="POST" action="{{ route('loan-types.destroy', $type) }}" class="d-inline"
                                                onsubmit="return confirm('Delete loan type {{ addslashes($type->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted p-3">No loan types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.loan_types._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Loan Types");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('loan-type-modal')).show(); });
        @endif
    </script>
@endpush
