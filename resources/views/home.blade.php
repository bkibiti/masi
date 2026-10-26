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
    $figures = [
        ['label' => 'Members', 'value' => $stats['members'], 'sub' => 'total registered'],
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
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Dashboard");
    </script>
@endpush
