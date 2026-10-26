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
{{-- Member details --}}
<div class="col-lg-4">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Member details</h3>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Mobile</dt><dd class="col-7">{{ $member->phone }}</dd>
                <dt class="col-5">Email</dt><dd class="col-7">{{ $member->email ?: '—' }}</dd>
            </dl>
        </div>
        <div class="card-footer d-flex gap-2">
            @can('Manage Members')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-member">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            @endcan
        </div>
    </div>
</div>

{{-- ===================== Modals ===================== --}}

@can('Manage Members')
    <div class="modal fade" id="edit-member" tabindex="-1">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <form method="POST" action="{{ route('members.update', $member) }}" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('members._form', ['member' => $member])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
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
