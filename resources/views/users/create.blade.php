  <div class="modal fade" id="register" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-sm-down" role="document">
          <div class="modal-content">

            <div class="modal-header">
                    <h5 class="modal-title" >Add User </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            <div class="modal-body">

                <div>
                    <form method="POST" action="{{ route('users.register') }}" aria-label="{{ __('Register') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-end">{{ __('Name') }}<font color="red">*</font></label>

                            <div class="col-md-8">
                                <input id="name" type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" value="{{ old('name') }}" required >

                                <span class="text-danger">
                                    <strong id="name-error"></strong>
                                </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="username" class="col-md-4 col-form-label text-md-end">{{ __('Username') }} <font color="red">*</font></label>

                            <div class="col-md-8">
                                <input id="username" type="text" class="form-control{{ $errors->has('username') ? ' is-invalid' : '' }}" name="username" value="{{ old('username') }}" required>

                                <span class="text-danger">
                                     <strong id="username-error"></strong>
                                </span>
                            </div>
                        </div>
               
                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('E-Mail') }} </label>

                            <div class="col-md-8">
                                <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" >

                                <span class="text-danger">
                                     <strong id="email-error"></strong>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                                <label for="mobile" class="col-md-4 col-form-label text-md-end">{{ __('Mobile Number') }}</label>

                                <div class="col-md-8">
                                    <input id="mobile" type="text" class="form-control{{ $errors->has('mobile') ? ' is-invalid' : '' }}" name="mobile" value="{{ old('mobile') }}" data-inputmask='"mask": "0999-999-9999"' data-mask>

                                    <span class="text-danger">
                                         <strong id="email-error"></strong>
                                    </span>
                                </div>
                        </div>
                        <div class="row mb-3">
                                <label for="job" class="col-md-4 col-form-label text-md-end">{{ __('Position') }}</label>

                                <div class="col-md-8">
                                    <input id="job" type="text" class="form-control" name="position" value="{{ old('position') }}">

                                    <span class="text-danger">
                                         <strong id="postion-error"></strong>
                                    </span>
                                </div>
                        </div>
                        <div class="row mb-3">
                                <label for="new_password" class="col-md-4 col-form-label text-md-end">Password <font color="red">*</font></label>

                                <div class="col-md-8">
                                    <input  type="password" class="form-control" name="password" required>

                                    <span class="text-danger">
                                        <strong id="password-error"></strong>
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="username" class="col-md-4 col-form-label text-md-end">Confirm Password <font color="red">*</font></label>

                                <div class="col-md-8">
                                    <input  type="password" class="form-control" name="password_confirmation" id="password_confirmation"  required>

                                    <span class="text-danger">
                                        <strong id="password-confirmation-error"></strong>
                                    </span>
                                </div>
                            </div>

                        <div class="row mb-3">
                                <label for="role" class="col-md-4 col-form-label text-md-end">{{ __('User Role') }} <font color="red">*</font></label>
                                <div class="col-md-8">
                                        <select class="form-select select2"  class="form-select" id="role" name="role[]"  data-placeholder="Select Role" required data-width="100%">
                                                @foreach(getRoles() as $role)
                                                    <option value="{{$role->id}}" {{ ($role->id == old('role') ? "selected":"") }}>{{$role->name}}</option>
                                                @endforeach
                                        </select>
                                <span class="text-danger">
                                    <strong id="role-error"></strong>
                                </span>
                                </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>


            </div>

          </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
