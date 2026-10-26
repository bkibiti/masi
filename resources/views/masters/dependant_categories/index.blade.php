@extends("layouts.master")

@section('content-title')
    Dependant Categories
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Masters / Dependant Categories</li>
@endsection

@section("content")
@php
    $genderLabel = fn ($g) => $g ?? 'Any';
@endphp

<div class="col-lg-10">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Dependant Categories</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#category-modal"
                    data-action="{{ route('dependant-categories.store') }}" data-method="POST" data-title="New category">
                    <i class="bi bi-plus-lg"></i> Add Category
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Name</th>
                            <th>Group heading</th>
                            <th>Dependant gender</th>
                            <th>Only for members who are</th>
                            <th class="text-end">Dependants</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $c)
                            <tr>
                                <td>{{ $c->sort_order }}</td>
                                <td><strong>{{ $c->name }}</strong></td>
                                <td>{{ $c->group_name }}</td>
                                <td>{{ $genderLabel($c->gender) }}</td>
                                <td>{{ $genderLabel($c->member_gender) }}</td>
                                <td class="text-end">
                                    {{ $c->living_count }}
                                    @if ($c->dependants_count > $c->living_count)
                                        <small class="text-muted">(+{{ $c->dependants_count - $c->living_count }} deceased)</small>
                                    @endif
                                </td>
                                <td class="text-end" style="white-space: nowrap">
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#category-modal"
                                        data-action="{{ route('dependant-categories.update', $c) }}" data-method="PUT" data-title="Edit category"
                                        data-name="{{ $c->name }}" data-group="{{ $c->group_name }}" data-gender="{{ $c->gender }}"
                                        data-member-gender="{{ $c->member_gender }}" data-order="{{ $c->sort_order }}">Edit</button>
                                    @if ($c->dependants_count === 0)
                                        <form method="POST" action="{{ route('dependant-categories.destroy', $c) }}" class="d-inline"
                                            onsubmit="return confirm('Delete category {{ addslashes($c->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer small text-muted">
            Renaming a category changes it for all its dependants. A category that has dependants cannot be deleted.
            Deaths already recorded keep the category name they had at the time.
        </div>
    </div>
</div>

<div class="modal fade" id="category-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_action', route('dependant-categories.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('dependant-categories.store')) }}">
            <input type="hidden" name="_title" value="{{ old('_title', 'New category') }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New category') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" maxlength="50" required placeholder="e.g. Mzazi" value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Group heading</label>
                        <input type="text" name="group_name" class="form-control" maxlength="50" placeholder="e.g. Wazazi" value="{{ old('group_name') }}">
                        <div class="form-text">Shown above the group on the member page. Blank = same as name.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dependant gender</label>
                        <select name="gender" class="form-select">
                            <option value="">Any (chosen per person)</option>
                            <option value="Male" @selected(old('gender') === 'Male')>Always Male</option>
                            <option value="Female" @selected(old('gender') === 'Female')>Always Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Only for members who are</label>
                        <select name="member_gender" class="form-select">
                            <option value="">Any member</option>
                            <option value="Male" @selected(old('member_gender') === 'Male')>Male</option>
                            <option value="Female" @selected(old('member_gender') === 'Female')>Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0" max="999" value="{{ old('sort_order', 0) }}">
                    </div>
                    <div class="col-12 form-text mt-1">
                        Example: <strong>Mume</strong> is always Male and only for female members; <strong>Mke</strong> is always Female and only for male members.
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
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Dependant Categories");

        $(function () {
            $('#category-modal').on('show.bs.modal', function (event) {
                if (! event.relatedTarget) {
                    return; // reopened after a validation error: keep what was typed
                }
                const b = $(event.relatedTarget);
                const $f = $(this).find('form');
                $f.attr('action', b.data('action'));
                $f.find('[name=_action]').val(b.data('action'));
                $f.find('[name=_method]').val(b.data('method'));
                $f.find('[name=_title]').val(b.data('title'));
                $(this).find('.modal-title').text(b.data('title'));
                $f.find('[name=name]').val(b.data('name') ?? '');
                $f.find('[name=group_name]').val(b.data('group') ?? '');
                $f.find('[name=gender]').val(b.data('gender') ?? '');
                $f.find('[name=member_gender]').val(b.data('memberGender') ?? '');
                $f.find('[name=sort_order]').val(b.data('order') ?? 0);
            });

            @if ($errors->any())
                bootstrap.Modal.getOrCreateInstance(document.getElementById('category-modal')).show();
            @endif
        });
    </script>
@endpush
