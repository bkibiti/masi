@extends("layouts.master")

@section('content-title')

Change Password

@endsection

@section('content-sub-title')
<li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
<li class="breadcrumb-item"><a href="#">Users / Change Password</a></li>
@endsection
@section("content")

<div class="col-sm-12">
    <div class="card">
            <div class="card-header">
                <h3 class="card-title">Change Password</h3>
            </div>
        <div class="card-body">
            <div>
                <form method="POST" action="{{ route('change-password') }}">
                    @csrf


                    <div class="row mb-3">
                        <label for="current_password" class="col-md-3 col-form-label text-md-end">Current Password</label>
                        
                        <div class="col-md-9">
                            <input type="password" class="form-control" name="current_password"  required autofocus>
                            
                         
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="new_password" class="col-md-3 col-form-label text-md-end">New Password</label>
                        
                        <div class="col-md-9">
                            <input  type="password" class="form-control" name="new_password"    required>
                            
                       
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="username" class="col-md-3 col-form-label text-md-end">Confirm New Password</label>
                        
                        <div class="col-md-9">
                            <input  type="password" class="form-control" name="new_password_confirmation" id="new_password_confirmation"  required>
                           
                        </div>
                    </div>


                    <div class="modal-footer">


                        <button type="submit" class="btn btn-primary">Change</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
@push("page_scripts")
@include('partials.notification')

<script>


      

</script>

@endpush