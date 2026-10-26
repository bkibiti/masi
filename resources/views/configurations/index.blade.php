@extends("layouts.master")

@section('content-title')
    Settings
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Settings</li>
@endsection

@section("content")
@php
    $logo = $settings['logo'] ?? null;
    $logoUrl = $logo && file_exists(public_path('logo/'.$logo)) ? asset('logo/'.$logo) : null;
@endphp

<div class="col-lg-8">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Group settings</h3>
        </div>

        <form action="{{ route('configurations.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Group name <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input type="text" name="group_name" class="form-control" maxlength="150" required
                            value="{{ old('group_name', $settings['group_name'] ?? '') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Registration number</label>
                    <div class="col-md-8">
                        <input type="text" name="registration_number" class="form-control" maxlength="100"
                            value="{{ old('registration_number', $settings['registration_number'] ?? '') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Slogan</label>
                    <div class="col-md-8">
                        <textarea name="slogan" class="form-control" rows="2" maxlength="255">{{ old('slogan', $settings['slogan'] ?? '') }}</textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Logo</label>
                    <div class="col-md-8">
                        @if ($logoUrl)
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <img src="{{ $logoUrl }}" alt="Logo" style="max-width: 100px; max-height: 100px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                    <label class="form-check-label" for="remove_logo">Remove logo</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG, JPG or WEBP, up to 2 MB.{{ $logoUrl ? ' Choosing a file replaces the current logo.' : '' }}</div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Settings");
    </script>
@endpush
