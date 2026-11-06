@extends("layouts.master")

@section('content-title')
    Fine Types
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Fine Types</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Fine Types</h3>
            <div class="card-tools">
                @can('Manage Fine Types')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fine-type-modal"
                        data-action="{{ route('fine-types.store') }}" data-method="POST" data-title="New fine type">
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
                            <th class="text-end">Default amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fineTypes as $type)
                            <tr>
                                <td>{{ $type->name }}</td>
                                <td>{{ $type->description ?: '—' }}</td>
                                <td class="text-end">{{ formatAmount($type->default_amount) }}</td>
                                <td><span class="badge {{ $type->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Fine Types')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#fine-type-modal"
                                            data-action="{{ route('fine-types.update', $type) }}" data-method="PUT" data-title="Edit fine type"
                                            data-name="{{ $type->name }}" data-description="{{ $type->description }}"
                                            data-amount="{{ $type->default_amount }}" data-active="{{ $type->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        @if (! $type->isInUse())
                                            <form method="POST" action="{{ route('fine-types.destroy', $type) }}" class="d-inline"
                                                onsubmit="return confirm('Delete fine type {{ addslashes($type->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No fine types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.fine_types._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Fine Types");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('fine-type-modal')).show(); });
        @endif
    </script>
@endpush
