@extends('layouts.app')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-body login-card-body">
        <p class="login-box-msg">{{ __('Reset Password') }}</p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="input-group mb-3 has-validation">
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                    value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus placeholder="{{ __('E-Mail Address') }}">
                <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="input-group mb-3 has-validation">
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                    name="password" required autocomplete="new-password" placeholder="{{ __('Password') }}">
                <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="input-group mb-3">
                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required
                    autocomplete="new-password" placeholder="{{ __('Confirm Password') }}">
                <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('Reset Password') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
