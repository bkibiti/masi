@extends("layouts.master")

@section('content-title')
    Payment Methods
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Payment Methods</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payment Methods</h3>
            <div class="card-tools">
                @can('Manage Payment Methods')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payment-method-modal"
                        data-action="{{ route('payment-methods.store') }}" data-method="POST" data-title="New payment method">
                        <i class="bi bi-plus-lg"></i> Add method
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
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentMethods as $method)
                            <tr>
                                <td>{{ $method->name }}</td>
                                <td>{{ $method->description ?: '—' }}</td>
                                <td><span class="badge {{ $method->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $method->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Payment Methods')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#payment-method-modal"
                                            data-action="{{ route('payment-methods.update', $method) }}" data-method="PUT" data-title="Edit payment method"
                                            data-name="{{ $method->name }}" data-description="{{ $method->description }}" data-active="{{ $method->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        @if (! $method->isInUse())
                                            <form method="POST" action="{{ route('payment-methods.destroy', $method) }}" class="d-inline"
                                                onsubmit="return confirm('Delete payment method {{ addslashes($method->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No payment methods yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.payment_methods._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Payment Methods");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('payment-method-modal')).show(); });
        @endif
    </script>
@endpush
