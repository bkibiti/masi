@if ($member->user_id === null)
    <form method="POST" action="{{ route('members.login.create', $member) }}" class="d-inline"
        onsubmit="return confirm('Create a login for {{ $member->name }}? A temporary password will be generated and shown once.');">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus"></i> Create login</button>
    </form>

    @if ($linkableUsers->isNotEmpty())
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#link-login">
            <i class="bi bi-link-45deg"></i> Link existing user
        </button>

        <div class="modal fade" id="link-login" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('members.login.link', $member) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Link an existing user to {{ $member->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <select name="user_id" class="form-select" required>
                            <option value="">Select a user&hellip;</option>
                            @foreach ($linkableUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email ?? $u->username }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Link</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@else
    <form method="POST" action="{{ route('members.login.reset-password', $member) }}" class="d-inline"
        onsubmit="return confirm('Reset the password for {{ $member->name }}? A new temporary password will be generated and shown once.');">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key"></i> Reset password</button>
    </form>

    @if ($member->user->status == 1)
        <form method="POST" action="{{ route('members.login.disable', $member) }}" class="d-inline"
            onsubmit="return confirm('Disable the login for {{ $member->name }}?');">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-slash-circle"></i> Disable login</button>
        </form>
    @else
        <form method="POST" action="{{ route('members.login.enable', $member) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Enable login</button>
        </form>
    @endif
@endif
