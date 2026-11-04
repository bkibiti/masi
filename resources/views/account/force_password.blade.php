@extends("layouts.master")

@section('content-title')
    Change your password
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item active">Change your password</li>
@endsection

@section("content")
<div class="col-lg-6">
    <div class="card">
        <div class="card-header"><h3 class="card-title">You must set a new password</h3></div>
        <form method="POST" action="{{ route('account.force-password.update') }}">
            @csrf
            <div class="card-body">
                <p class="text-muted">A temporary password was generated for you. Choose a new password to continue.</p>
                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="6" autofocus>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Set password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
