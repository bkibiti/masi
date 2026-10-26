@extends("layouts.master")

@section('content-title')
    {{ $member->name }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('members.index') }}">Members</a></li>
    <li class="breadcrumb-item active">{{ $member->name }}</li>
@endsection

@section("content")
@php
    $today = now()->toDateString();
    $grouped = $member->dependants->groupBy('dependant_category_id');
@endphp

{{-- Member details --}}
<div class="col-lg-4">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Member details</h3>
            <div class="card-tools">
                @include('members._status', ['status' => $member->status])
            </div>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Member no.</dt><dd class="col-7">{{ $member->member_no ?: '—' }}</dd>
                <dt class="col-5">Gender</dt><dd class="col-7">{{ $member->gender }}</dd>
                <dt class="col-5">Date of birth</dt>
                <dd class="col-7">
                    {{ $member->date_of_birth?->format('j M Y') ?? '—' }}
                    @if ($member->date_of_birth && ! $member->isDeceased())
                        <small class="text-muted">({{ ageInYears($member->date_of_birth) }} yrs)</small>
                    @endif
                </dd>
                <dt class="col-5">Mobile</dt>
                <dd class="col-7">
                    @forelse ($member->phones as $phone)
                        <div>{{ $phone->phone }} @if ($phone->is_primary)<small class="text-muted">(main)</small>@endif</div>
                    @empty
                        —
                    @endforelse
                </dd>
                <dt class="col-5">Location</dt><dd class="col-7">{{ $member->location ?: '—' }}</dd>
                <dt class="col-5">Joined on</dt><dd class="col-7">{{ $member->joined_on?->format('j M Y') ?? '—' }}</dd>
                @if ($member->notes)
                    <dt class="col-5">Notes</dt><dd class="col-7">{{ $member->notes }}</dd>
                @endif
            </dl>

            @if ($member->ownDeath)
                <div class="alert alert-secondary mt-3 mb-0">
                    <strong>Died {{ $member->ownDeath->date_of_death ? $member->ownDeath->dateLabel() : '(date unknown)' }}</strong>
                    @if ($member->ownDeath->place_of_death) at {{ $member->ownDeath->place_of_death }}@endif.
                    @if ($member->ownDeath->burial_date)
                        <br>Buried {{ $member->ownDeath->burial_date->format('j M Y') }}@if ($member->ownDeath->burial_place), {{ $member->ownDeath->burial_place }}@endif.
                    @endif
                </div>
            @endif
        </div>
        <div class="card-footer d-flex gap-2">
            @can('Manage Members')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-member">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            @endcan
            @can('Record Deaths')
                @unless ($member->isDeceased())
                    <button type="button" class="btn btn-outline-dark btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#record-death"
                        data-action="{{ route('deaths.member.store', $member) }}" data-name="{{ $member->name }} (member)">
                        <i class="bi bi-journal-plus"></i> Record death
                    </button>
                @endunless
            @endcan
        </div>
    </div>
</div>

{{-- Dependants --}}
<div class="col-lg-8">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Dependants</h3>
            <div class="card-tools">
                @can('Manage Dependants')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#add-dependant">
                        <i class="bi bi-plus-lg"></i> Add dependant
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body p-0">
            @if ($member->dependants->isEmpty())
                <p class="text-muted p-3 mb-0">No dependants recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                @continue(! $grouped->has($category->id))
                                <tr class="table-light">
                                    <th colspan="6">{{ $category->group_name }} <span class="text-muted fw-normal">({{ $grouped[$category->id]->count() }})</span></th>
                                </tr>
                                @foreach ($grouped[$category->id] as $d)
                                    <tr>
                                        <td>
                                            {{ $d->name }}
                                            @if ($d->notes)<br><small class="text-muted">{{ $d->notes }}</small>@endif
                                        </td>
                                        <td>{{ $d->gender }}</td>
                                        <td>
                                            @if ($d->date_of_birth)
                                                {{ ageInYears($d->date_of_birth, $d->death?->date_of_death) }}
                                            @endif
                                        </td>
                                        <td>{{ $d->phone }}</td>
                                        <td>
                                            @include('members._status', ['status' => $d->status])
                                            @if ($d->death)
                                                <br><small class="text-muted">{{ $d->death->dateLabel() }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end" style="white-space: nowrap">
                                            @can('Manage Dependants')
                                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#edit-dependant"
                                                    data-action="{{ route('dependants.update', $d) }}"
                                                    data-name="{{ $d->name }}" data-category="{{ $d->dependant_category_id }}"
                                                    data-gender="{{ $d->gender }}" data-dob="{{ $d->date_of_birth?->toDateString() }}"
                                                    data-phone="{{ $d->phone }}" data-notes="{{ $d->notes }}">Edit</button>
                                            @endcan
                                            @can('Record Deaths')
                                                @unless ($d->isDeceased())
                                                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#record-death"
                                                        data-action="{{ route('deaths.dependant.store', $d) }}"
                                                        data-name="{{ $d->name }} ({{ $d->relationshipName() }})">Record death</button>
                                                @endunless
                                            @endcan
                                            @can('Manage Dependants')
                                                @unless ($d->isDeceased())
                                                    <form method="POST" action="{{ route('dependants.destroy', $d) }}" class="d-inline"
                                                        onsubmit="return confirm('Remove {{ addslashes($d->name) }} from the dependants? Use this only for a wrong entry.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove wrong entry"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                @endunless
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Death history of the family --}}
<div class="col-12">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Death history</h3>
        </div>
        <div class="card-body p-0">
            @if ($member->deaths->isEmpty())
                <p class="text-muted p-3 mb-0">No deaths recorded for this member or the dependants.</p>
            @else
                @include('deaths._table', ['deaths' => $member->deaths, 'showMember' => false])
            @endif
        </div>
    </div>
</div>

{{-- ===================== Modals ===================== --}}

@can('Manage Members')
    <div class="modal fade" id="edit-member" tabindex="-1">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <form method="POST" action="{{ route('members.update', $member) }}" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('members._form', ['member' => $member])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
@endcan

@can('Manage Dependants')
    @foreach (['add-dependant' => 'Add dependant', 'edit-dependant' => 'Edit dependant'] as $modalId => $modalTitle)
        @php $isOld = old('_modal') === $modalId; @endphp
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1">
            <div class="modal-dialog modal-fullscreen-sm-down">
                <form method="POST" class="modal-content"
                    action="{{ $modalId === 'add-dependant' ? route('dependants.store', $member) : ($isOld ? old('_action') : '#') }}">
                    @csrf
                    @if ($modalId === 'edit-dependant')
                        @method('PUT')
                        <input type="hidden" name="_action" value="{{ $isOld ? old('_action') : '' }}">
                    @endif
                    <input type="hidden" name="_modal" value="{{ $modalId }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalTitle }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control dep-name" maxlength="150" required value="{{ $isOld ? old('name') : '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="dependant_category_id" class="form-select dep-category" required>
                                    <option value="">Select</option>
                                    @foreach ($categories as $category)
                                        @continue(! $category->allowsMember($member))
                                        <option value="{{ $category->id }}" data-fixed-gender="{{ $category->gender }}"
                                            @selected($isOld && (int) old('dependant_category_id') === $category->id)>
                                            {{ $category->name }}{{ $category->group_name !== $category->name ? ' ('.$category->group_name.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select dep-gender">
                                    <option value="">Select</option>
                                    <option value="Male" @selected($isOld && old('gender') === 'Male')>Male</option>
                                    <option value="Female" @selected($isOld && old('gender') === 'Female')>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of birth</label>
                                <input type="date" name="date_of_birth" class="form-control dep-dob" max="{{ $today }}" value="{{ $isOld ? old('date_of_birth') : '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile</label>
                                <input type="text" name="phone" class="form-control dep-phone" inputmode="numeric" maxlength="12"
                                    pattern="255[0-9]{9}" title="12 digits starting with 255" placeholder="255..." value="{{ $isOld ? old('phone') : '' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control dep-notes" maxlength="255" value="{{ $isOld ? old('notes') : '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endcan

@can('Record Deaths')
    @php $isOld = old('_modal') === 'record-death'; @endphp
    <div class="modal fade" id="record-death" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form method="POST" action="{{ $isOld ? old('_action') : '#' }}" class="modal-content">
                @csrf
                <input type="hidden" name="_modal" value="record-death">
                <input type="hidden" name="_action" value="{{ $isOld ? old('_action') : '' }}">
                <input type="hidden" name="_name" value="{{ $isOld ? old('_name') : '' }}">
                <div class="modal-header">
                    <h5 class="modal-title">Record death: <span class="death-name">{{ $isOld ? old('_name') : '' }}</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date of death <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_death" class="form-control" max="{{ $today }}" required value="{{ $isOld ? old('date_of_death') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Place of death</label>
                            <input type="text" name="place_of_death" class="form-control" maxlength="150" value="{{ $isOld ? old('place_of_death') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Burial date</label>
                            <input type="date" name="burial_date" class="form-control" value="{{ $isOld ? old('burial_date') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Burial place</label>
                            <input type="text" name="burial_place" class="form-control" maxlength="150" value="{{ $isOld ? old('burial_place') : '' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reported by</label>
                            <input type="text" name="reported_by" class="form-control" maxlength="150" value="{{ $isOld ? old('reported_by') : '' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ $isOld ? old('notes') : '' }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-dark">Record death</button>
                </div>
            </form>
        </div>
    </div>
@endcan

@include('deaths._reverse_modal')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | " + @json($member->name));

        $(function () {
            // Categories with a fixed gender (e.g. Mume, Mke) set the gender themselves
            $('.dep-category').on('change', function () {
                const gender = $(this).find('option:selected').data('fixedGender');
                const $gender = $(this).closest('form').find('.dep-gender');
                if (gender) {
                    $gender.val(gender).prop('disabled', true);
                } else {
                    $gender.prop('disabled', false);
                }
            }).trigger('change');

            $('#edit-dependant').on('show.bs.modal', function (event) {
                const b = event.relatedTarget ? $(event.relatedTarget) : null;
                if (! b) {
                    return; // reopened after a validation error: keep the submitted values
                }
                const $form = $(this).find('form');
                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('.dep-name').val(b.data('name'));
                $form.find('.dep-category').val(b.data('category')).trigger('change');
                $form.find('.dep-gender').val(b.data('gender'));
                $form.find('.dep-dob').val(b.data('dob'));
                $form.find('.dep-phone').val(b.data('phone'));
                $form.find('.dep-notes').val(b.data('notes'));
            });

            $('#record-death').on('show.bs.modal', function (event) {
                const b = event.relatedTarget ? $(event.relatedTarget) : null;
                if (! b) {
                    return;
                }
                const $form = $(this).find('form');
                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_name]').val(b.data('name'));
                $(this).find('.death-name').text(b.data('name'));
            });

            @if ($errors->any() && old('_modal'))
                // Reopen the form that failed validation
                const failed = document.getElementById(@json(old('_modal')));
                if (failed) {
                    bootstrap.Modal.getOrCreateInstance(failed).show();
                }
            @endif
        });
    </script>
@endpush
