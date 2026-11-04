@extends("layouts.master")

@section('content-title')
    Logins created
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('members.index') }}">Members</a></li>
    <li class="breadcrumb-item active">Logins created</li>
@endsection

@section('page_css')
<style>
    @media print {
        .no-print { display: none !important; }
    }
</style>
@endsection

@section("content")
<div class="col-12">
    <div class="alert alert-warning no-print">
        These temporary passwords are shown once and cannot be retrieved again. Print or copy this page before leaving it.
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center no-print">
            <h3 class="card-title">{{ $created->count() }} login(s) created</h3>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Name</th><th>Mobile</th><th>Username</th><th>Temporary password</th></tr></thead>
                    <tbody>
                        @foreach ($created as $row)
                            <tr>
                                <td>{{ $row['member']->name }}</td>
                                <td>{{ $row['member']->phone }}</td>
                                <td>{{ $row['username'] }}</td>
                                <td><code>{{ $row['password'] }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer no-print">
            <a href="{{ route('members.index') }}" class="btn btn-secondary">Back to Members</a>
        </div>
    </div>
</div>
@endsection
