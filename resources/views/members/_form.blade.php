{{-- Member fields, shared by the "New member" and "Edit member" modals. $member is null when adding. --}}
@php
    // Only refill from old() when this form was the one submitted (the page has other forms too).
    $formId = $member ? 'edit-member' : 'create-member';
    $useOld = old('_modal') === $formId;
    $val = fn (string $key, $default = null) => $useOld ? old($key, $default) : $default;
    $phones = $val('phones', $member ? $member->phones->pluck('phone')->all() : []);
@endphp
<input type="hidden" name="_modal" value="{{ $formId }}">

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Full name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" maxlength="150" required
            value="{{ $val('name', $member?->name) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Member no.</label>
        <input type="text" name="member_no" class="form-control" maxlength="20"
            value="{{ $val('member_no', $member?->member_no) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">Gender <span class="text-danger">*</span></label>
        <select name="gender" class="form-select" required>
            <option value="">Select</option>
            @foreach (\App\Models\Member::GENDERS as $gender)
                <option value="{{ $gender }}" @selected($val('gender', $member?->gender) === $gender)>{{ $gender }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Date of birth</label>
        <input type="date" name="date_of_birth" class="form-control" max="{{ now()->toDateString() }}"
            value="{{ $val('date_of_birth', $member?->date_of_birth?->toDateString()) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Joined on</label>
        <input type="date" name="joined_on" class="form-control" max="{{ now()->toDateString() }}"
            value="{{ $val('joined_on', $member?->joined_on?->toDateString()) }}">
    </div>

    <div class="col-12">
        <label class="form-label mb-1">Mobile numbers</label>
        <div class="form-text mt-0 mb-2">Up to {{ \App\Models\Member::MAX_PHONES }} numbers, 12 digits starting with 255. The first one is the main number.</div>
        <div class="row g-2">
            @for ($i = 0; $i < \App\Models\Member::MAX_PHONES; $i++)
                <div class="col-md-4">
                    <input type="text" name="phones[]" class="form-control" inputmode="numeric" maxlength="12"
                        pattern="255[0-9]{9}" title="12 digits starting with 255, e.g. 255745777602"
                        placeholder="{{ $i === 0 ? 'Main, e.g. 255745777602' : 'Mobile '.($i + 1).' (optional)' }}"
                        value="{{ $phones[$i] ?? '' }}">
                </div>
            @endfor
        </div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" maxlength="100"
            value="{{ $val('location', $member?->location) }}">
    </div>
    @if ($member && ! $member->isDeceased())
        <div class="col-md-4">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
                @foreach (['Active', 'Inactive'] as $status)
                    <option value="{{ $status }}" @selected($val('status', $member->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ $val('notes', $member?->notes) }}</textarea>
    </div>
</div>
