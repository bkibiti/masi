@extends("layouts.master")

@section('content-title')
    Create logins for members without one
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('members.index') }}">Members</a></li>
    <li class="breadcrumb-item active">Bulk create logins</li>
@endsection

@section("content")
<div class="col-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $members->count() }} member(s) without a login</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Name</th><th>Mobile</th><th>Email</th></tr></thead>
                    <tbody>
                        @forelse ($members as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td>{{ $m->phone }}</td>
                                <td>{{ $m->email }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted p-3">Every member already has a login.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($members->isNotEmpty())
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('members.index') }}" class="btn btn-secondary">Cancel</a>
                <form method="POST" action="{{ route('members.login.bulk-create.store') }}"
                    onsubmit="return confirm('Create logins for all {{ $members->count() }} member(s) listed above? The temporary passwords will be shown once, on a printable page.');">
                    @csrf
                    <button type="submit" class="btn btn-primary">Create {{ $members->count() }} login(s)</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
