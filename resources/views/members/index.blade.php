@extends("layouts.master")

@section('content-title')
    Members
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Members</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Members</h3>
            <div class="card-tools">
                @can('Manage Members')
                    <a href="{{ route('members.login.bulk-create') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-people"></i> Create logins for members without one
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#create-member">
                        <i class="bi bi-plus-lg"></i> Add Member
                    </button>
                @endcan
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="fixed-header" class="display table nowrap table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td>{{ $m->phone }}</td>
                                <td>{{ $m->email }}</td>
                                <td>@include('members._login_badge', ['member' => $m])</td>
                                <td style="white-space: nowrap">
                                    <a href="{{ route('members.show', $m) }}" class="btn btn-info btn-sm">View</a>
                                    @can('Manage Members')
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-member-{{ $m->id }}">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
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

@can('Manage Members')
    <div class="modal fade" id="create-member" tabindex="-1">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <form method="POST" action="{{ route('members.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('members._form', ['member' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($members as $m)
        <div class="modal fade" id="edit-member-{{ $m->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
                <form method="POST" action="{{ route('members.update', $m) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('members._form', ['member' => $m, 'modalId' => 'edit-member-' . $m->id])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Members");

        @if ($errors->any() && old('_modal'))
            // Reopen the form that failed validation so the entered values are not lost
            $(function () {
                const failed = document.getElementById(@json(old('_modal')));
                if (failed) {
                    bootstrap.Modal.getOrCreateInstance(failed).show();
                }
            });
        @endif
    </script>
@endpush
