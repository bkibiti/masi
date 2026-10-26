<div class="modal fade" id="editUser" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-sm-down" role="document">
          <div class="modal-content">
                <div class="modal-header">
                        <h5 class="modal-title" >Update User </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
            <div class="modal-body">

                <div>
                    <form method="POST" action="{{route('users.update') }}" >
                        @csrf

                        <div class="row mb-3">
                            <label for="name1" class="col-md-4 col-form-label text-md-end">{{ __('Name') }} <font color="red">*</font></label>

                            <div class="col-md-8">
                                <input id="name1" type="text" class="form-control" name="name1" value="{{ old('name') }}" required autofocus>

                                <span class="text-danger">
                                    <strong id="name-error1"></strong>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="username" class="col-md-4 col-form-label text-md-end">{{ __('Username') }} <font color="red">*</font></label>

                            <div class="col-md-8">
                                <input id="username1" type="text" class="form-control{{ $errors->has('username') ? ' is-invalid' : '' }}" name="username1" value="{{ old('username') }}" disabled>

                                <span class="text-danger">
                                     <strong id="username1-error"></strong>
                                </span>
                            </div>
                        </div>


                        <div class="row mb-3">
                            <label for="email1" class="col-md-4 col-form-label text-md-end">{{ __('E-Mail') }} </label>

                            <div class="col-md-8">
                                <input id="email1" type="email" class="form-control" name="email1" value="{{ old('email1') }}" >

                                <span class="text-danger">
                                     <strong id="email-error1"></strong>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                                <label for="mobile1" class="col-md-4 col-form-label text-md-end">{{ __('Mobile Number') }}</label>

                                <div class="col-md-8">
                                    <input id="mobile1" type="text" class="form-control{{ $errors->has('mobile1') ? ' is-invalid' : '' }}" name="mobile1" value="{{ old('mobile1') }}" data-inputmask='"mask": "0999-999-9999"' data-mask>

                                    <span class="text-danger">
                                         <strong id="mobile-error1"></strong>
                                    </span>
                                </div>
                        </div>
                        <div class="row mb-3">
                                <label for="job1" class="col-md-4 col-form-label text-md-end">{{ __('Position') }}</label>

                                <div class="col-md-8">
                                    <input id="position1" type="text" class="form-control" name="position1" value="{{ old('position1') }}">

                                    <span class="text-danger">
                                         <strong id="position-error1"></strong>
                                    </span>
                                </div>
                        </div>

                        <div class="row mb-3">
                                <label for="role1" class="col-md-4 col-form-label text-md-end">{{ __('User Role') }} <font color="red">*</font></label>
                                <div class="col-md-8">
                                        <select class="form-select select2"  class="form-select" id="role1" name="role1[]"  data-placeholder="Select Role" required data-width="100%">
                                            @foreach(getRoles() as $role)
                                                <option value="{{$role->id}}" {{ ($role->id == old('role1') ? "selected":"") }}>{{$role->name}}</option>
                                            @endforeach
                                        </select>
                                <span class="text-danger">
                                    <strong id="role-error1"></strong>
                                </span>
                                </div>
                                <input id="UserID" name="UserID" type="hidden">
                        </div>



                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" id="update" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>

            </div>


          </div><!-- /.modal-content -->
    </div><!--/.modal-dialog -->
</div><!--/.modal -->
