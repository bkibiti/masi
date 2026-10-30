@extends("layouts.master")

@section('content-title')
    Contribution Settings
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Contribution Settings</li>
@endsection

@section("content")
@php
    $statusBadge = fn ($status) => match ($status) {
        'Active' => 'text-bg-success',
        'Upcoming' => 'text-bg-info',
        'Closed' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
@endphp

@if ($active)
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card text-center"><div class="card-body">
            <div class="text-body-secondary small text-uppercase">Active setting</div>
            <div class="fs-5 fw-bold">{{ $active->name }}</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card text-center"><div class="card-body">
            <div class="text-body-secondary small text-uppercase">Monthly amount</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($active->monthly_amount) }}</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card text-center"><div class="card-body">
            <div class="text-body-secondary small text-uppercase">Penalty %</div>
            <div class="fs-5 fw-bold">{{ rtrim(rtrim((string) $active->penalty_percent, '0'), '.') }}%</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card text-center"><div class="card-body">
            <div class="text-body-secondary small text-uppercase">Penalty Amount</div>
            <div class="fs-5 fw-bold">TZS {{ formatAmount($active->penaltyForFullMonth()) }}</div>
        </div></div>
    </div>
@endif

<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Contribution Settings</h3>
            <div class="card-tools">
                @can('Manage Contribution Settings')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#setting-modal"
                        data-action="{{ route('contribution-settings.store') }}" data-method="POST" data-title="New contribution setting" data-locked="0">
                        <i class="bi bi-plus-lg"></i> Add setting
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
                            <th>Start date</th>
                            <th>End date</th>
                            <th class="text-end">Monthly amount</th>
                            <th class="text-end">Penalty %</th>
                            <th class="text-end">Penalty / full month</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settings as $setting)
                            @php $locked = $setting->isLocked(); @endphp
                            <tr>
                                <td>{{ $setting->name }}</td>
                                <td>{{ $setting->start_date->format('j M Y') }}</td>
                                <td>{{ $setting->end_date->format('j M Y') }}</td>
                                <td class="text-end">{{ formatAmount($setting->monthly_amount) }}</td>
                                <td class="text-end">{{ rtrim(rtrim((string) $setting->penalty_percent, '0'), '.') }}%</td>
                                <td class="text-end">{{ formatAmount($setting->penaltyForFullMonth()) }}</td>
                                <td><span class="badge {{ $statusBadge($setting->status()) }}">{{ $setting->status() }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Contribution Settings')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#setting-modal"
                                            data-action="{{ route('contribution-settings.update', $setting) }}" data-method="PUT"
                                            data-title="{{ $locked ? 'View contribution setting' : 'Edit contribution setting' }}" data-locked="{{ $locked ? 1 : 0 }}"
                                            data-name="{{ $setting->name }}" data-start="{{ $setting->start_date->toDateString() }}"
                                            data-end="{{ $setting->end_date->toDateString() }}" data-amount="{{ $setting->monthly_amount }}"
                                            data-percent="{{ $setting->penalty_percent }}" data-notes="{{ $setting->notes }}">
                                            {{ $locked ? 'View' : 'Edit' }}
                                        </button>
                                        @if (! $setting->hasDues())
                                            <form method="POST" action="{{ route('contribution-settings.destroy', $setting) }}" class="d-inline"
                                                onsubmit="return confirm('Delete setting {{ addslashes($setting->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted p-3">No contribution settings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.contribution_settings._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Contribution Settings");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('setting-modal')).show(); });
        @endif
    </script>
@endpush
