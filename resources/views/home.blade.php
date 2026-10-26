@extends("layouts.master")

@section('page_css')
<style>
    .dashboard-card {
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        box-shadow: none;
        height: 100%;
    }

    .dashboard-card > .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .dashboard-label {
        color: var(--bs-secondary-color);
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .dashboard-figure {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
</style>
@endsection

@section('content-title')
    Dashboard
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
@endsection

@section("content")
@php
    $year = now('Africa/Dar_es_Salaam')->year;
    $figures = [
        ['label' => 'Active members', 'value' => $stats['active'], 'sub' => $stats['male'].' male · '.$stats['female'].' female'],
        ['label' => 'Dependants', 'value' => $stats['dependants'], 'sub' => 'living, all families'],
        ['label' => 'Deaths in '.$year, 'value' => $stats['member_deaths'] + $stats['dependant_deaths'], 'sub' => $stats['member_deaths'].' members · '.$stats['dependant_deaths'].' dependants'],
        ['label' => 'Inactive / deceased', 'value' => $stats['inactive'].' / '.$stats['deceased'], 'sub' => 'members'],
    ];
@endphp

<div class="col-12 mb-3">
    <h4 class="mb-0">Welcome, {{ Auth::user()->name }}</h4>
</div>

@foreach ($figures as $figure)
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="dashboard-label">{{ $figure['label'] }}</div>
                <div class="dashboard-figure">{{ $figure['value'] }}</div>
                <div class="text-body-secondary small">{{ $figure['sub'] }}</div>
            </div>
        </div>
    </div>
@endforeach

<div class="col-lg-4 mb-3">
    <div class="card dashboard-card">
        <div class="card-header">
            <h5 class="card-title mb-0">Dependants by category</h5>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($categories as $category)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ $category->group_name }}</span>
                    <strong>{{ $dependantsByRelationship[$category->id] ?? 0 }}</strong>
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="col-lg-8 mb-3">
    <div class="card dashboard-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent deaths</h5>
            @can('View Deaths')
                <a href="{{ route('deaths.index') }}" class="small ms-auto">View all</a>
            @endcan
        </div>
        <div class="card-body p-0">
            @if ($recentDeaths->isEmpty())
                <p class="text-body-secondary p-3 mb-0">No deaths recorded.</p>
            @else
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Relationship</th>
                                <th>Member</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentDeaths as $death)
                                <tr>
                                    <td>{{ $death->dateLabel() }}</td>
                                    <td>{{ $death->deceased_name }}</td>
                                    <td>{{ $death->relationship }}</td>
                                    <td>
                                        @can('View Members')
                                            <a href="{{ route('members.show', $death->member_id) }}">{{ $death->member?->name }}</a>
                                        @else
                                            {{ $death->member?->name }}
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Dashboard");
    </script>
@endpush
