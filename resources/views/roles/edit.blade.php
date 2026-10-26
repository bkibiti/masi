@extends("layouts.master")


@section('page_css')
@endsection

@section('content-title')
   Update Role

@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="#"> User Management / Roles / Update Role </a> </li>
@endsection



@section("content")

@php
    // old('permissions') wins after a failed validation (e.g. duplicate name), so the admin's
    // ticks aren't lost; otherwise fall back to what the role already has. Both sides are cast
    // to int so in_array(..., true) compares reliably regardless of DB driver string/int quirks.
    $selectedPermissions = old('permissions') !== null
        ? array_map('intval', (array) old('permissions'))
        : $rolePermissions;
@endphp

<div class="col-sm-12">

<div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Role</h3>
            </div>
    <div class="card-body">
        <form action="{{route('roles.update')}}" method="post">
            @csrf()
            <input type="hidden" name="id" value="{{$role->id}}">

        <div class="row mb-3">
                <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Role:<font color="red">*</font> </label>
                <div class="col-md-8">
                        <input id="role" type="text" class="form-control" name="name" value="{{$role->name}}" required autofocus>

                </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Description:<font color="red">*</font> </label>
            <div class="col-md-8">
                    <input id="description" type="text" class="form-control" name="description" value="{{$role->description}}" required>
            </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Permissions:<font color="red">*</font> </label>
            <div class="col-md-10">
                    <div class="row mb-3">
                            <div class="col-sm-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="check_all">
                                        <label for="check_all" class="form-check-label">Check All</label>
                                    </div>
                            </div>
                    </div>

                <div class="row mb-3">
                    @foreach ($permissionsAll as $permission)
                        <div class="col-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" id="{{$permission->id}}" value="{{$permission->id}}" @checked(in_array((int) $permission->id, $selectedPermissions, true))>
                                    <label for="{{$permission->id}}" class="form-check-label"> {{$permission->name}}</label>
                                </div>
                        </div>
                    @endforeach

                </div>
                <hr>
                <div class="row mb-3">
                        <div class="col-sm-8"></div>
                        <a href="{{route('roles.index')}}">
                                <button type="button" class="btn btn-danger">Back</button>
                        </a>
                        <button type="submit" class="btn btn-primary">Update</button>
                </div>

            </div>
        </div>

        </form>

    </div> {{-- /Card-Body --}}

</div>
</div>

@endsection

@push("page_scripts")
@include('partials.notification')

{{-- Plain JS (no jQuery) so "Check all" keeps working even if public/build assets drift. --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('check_all');
    var checkboxes = document.querySelectorAll('.perm-checkbox');

    if (!checkAll || checkboxes.length === 0) {
        return;
    }

    function syncCheckAll() {
        var checkedCount = 0;
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                checkedCount++;
            }
        });
        checkAll.checked = checkedCount === checkboxes.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }

    checkAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = checkAll.checked;
        });
        checkAll.indeterminate = false;
    });

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncCheckAll);
    });

    syncCheckAll();
});
</script>
@endpush
