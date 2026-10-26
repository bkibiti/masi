@extends("layouts.master")


@section('page_css')
@endsection

@section('content-title')
    Roles

@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="#"> User Management / Roles </a> </li>
@endsection



@section("content")

<div class="col-sm-12">

<div class="card">
            <div class="card-header">
                <h3 class="card-title">Roles</h3>
                <div class="card-tools">
                    <a href="{{route('roles.create')}}" class="btn btn-primary btn-sm" title="Add Role"><i class="bi bi-plus-lg"></i> Add Role</a>
                </div>
            </div>



    <div class="card-body">
        <div class="table-responsive">
            <table id="fixed-header" class="display table nowrap table-striped table-hover" style="width:100%">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                <tr>
                <td>{{$role->name}}</td>
                <td>{{$role->description}}</td>
                <td>
                    <a href="{{route('roles.edit',$role->id)}}">
                        <button class="btn btn-info btn-sm"  type="button">Edit</button>
                    </a>
                    <a href="#">
                        <button class="btn btn-danger btn-sm" data-id="{{$role->id}}" data-name="{{$role->name}}" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal" > Delete</button>
                    </a>


                </td>
                </tr>
                @endforeach

                </tbody>
            </table>
        </div>

    </div>

</div>
</div>

@endsection

@push("page_scripts")
@include('roles.delete')

@include('partials.notification')
<script>
    var title = document.title;
    document.title = title.concat(" | Roles");
</script>

<script>

   $('#deleteModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget)
      var id = button.data('id')
      var message =  "Are you sure you want to delete Role '".concat(button.data('name'), "'?") ;
      var modal = $(this)
      modal.find('.modal-body #message').text(message);
      modal.find('.modal-body #role_id').val(id)
    })

</script>

@endpush
