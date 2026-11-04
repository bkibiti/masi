@php
    $status = match (true) {
        $member->user === null => ['No login', 'secondary'],
        $member->user->status != 1 => ['Disabled', 'danger'],
        $member->user->must_change_password => ['Must change password', 'warning'],
        default => ['Active', 'success'],
    };
@endphp
<span class="badge text-bg-{{ $status[1] }}">{{ $status[0] }}</span>
