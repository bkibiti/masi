@extends('layouts.app')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-body login-card-body">
        <p class="login-box-msg">{{ __('Reset Password') }}</p>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="input-group mb-3 has-validation">
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                    value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="{{ __('E-Mail Address') }}">
                <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('Send Password Reset Link') }}</button>
            </div>
        </form>

        <p class="mb-0 mt-3">
            <a href="{{ route('login') }}">Back to login</a>
        </p>
    </div>
</div>
@endsection
