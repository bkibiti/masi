@extends("layouts.master")

@section('content-title')
    Payment Types
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Payment Types</li>
@endsection

@section("content")
@php
    $roleLabel = fn ($role) => match ($role) {
        'monthly_fee' => 'Monthly fee',
        'penalty' => 'Penalty',
        default => 'None',
    };
    $roleBadge = fn ($role) => match ($role) {
        'monthly_fee' => 'text-bg-primary',
        'penalty' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
@endphp

<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payment Types</h3>
            <div class="card-tools">
                @can('Manage Payment Types')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payment-type-modal"
                        data-action="{{ route('payment-types.store') }}" data-method="POST" data-title="New payment type" data-system="0">
                        <i class="bi bi-plus-lg"></i> Add type
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
                            <th>Description</th>
                            <th>Ledger role</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentTypes as $type)
                            <tr>
                                <td>{{ $type->name }} @if ($type->is_system) <span class="badge text-bg-light border">System</span> @endif</td>
                                <td>{{ $type->description ?: '—' }}</td>
                                <td><span class="badge {{ $roleBadge($type->ledger_role) }}">{{ $roleLabel($type->ledger_role) }}</span></td>
                                <td><span class="badge {{ $type->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Payment Types')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#payment-type-modal"
                                            data-action="{{ route('payment-types.update', $type) }}" data-method="PUT" data-title="Edit payment type"
                                            data-system="{{ $type->is_system ? 1 : 0 }}"
                                            data-name="{{ $type->name }}" data-description="{{ $type->description }}"
                                            data-role="{{ $type->ledger_role }}" data-active="{{ $type->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        @if (! $type->is_system && ! $type->isInUse())
                                            <form method="POST" action="{{ route('payment-types.destroy', $type) }}" class="d-inline"
                                                onsubmit="return confirm('Delete payment type {{ addslashes($type->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No payment types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.payment_types._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Payment Types");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('payment-type-modal')).show(); });
        @endif
    </script>
@endpush
