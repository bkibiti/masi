@php
    $class = match ($status) {
        'Active', 'Alive' => 'text-bg-success',
        'Inactive' => 'text-bg-secondary',
        'Deceased' => 'text-bg-dark',
        default => 'text-bg-light',
    };
@endphp
<span class="badge {{ $class }}">{{ $status }}</span>
