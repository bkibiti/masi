{{-- Member fields, shared by the "New member" and "Edit member" modals. $member is null when adding. --}}
@php
    // Only refill from old() when this form was the one submitted (the page has other forms too).
    $formId = $modalId ?? ($member ? 'edit-member' : 'create-member');
    $useOld = old('_modal') === $formId;
    $val = fn (string $key, $default = null) => $useOld ? old($key, $default) : $default;
@endphp
<input type="hidden" name="_modal" value="{{ $formId }}">

<div class="row g-3">
    <div class="col-12">
        <label class="form-label">Full name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" maxlength="150" required
            value="{{ $val('name', $member?->name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Mobile number <span class="text-danger">*</span></label>
        <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="12" required
            pattern="255[0-9]{9}" title="12 digits starting with 255, e.g. 255745777602" placeholder="255745777602"
            value="{{ $val('phone', $member?->phone) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" maxlength="255"
            value="{{ $val('email', $member?->email) }}">
    </div>
</div>
