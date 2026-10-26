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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td>{{ $m->phone }}</td>
                                <td>{{ $m->email }}</td>
                                <td><a href="{{ route('members.show', $m) }}" class="btn btn-info btn-sm">Open</a></td>
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
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Members");

        @if ($errors->any() && old('_modal') === 'create-member')
            // Reopen the form after a failed save so the entered values are not lost
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('create-member')).show(); });
        @endif
    </script>
@endpush
