@extends("layouts.master")

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="#">Masters / Expense Categories</a></li>
@endsection

@section("content")

    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Expense Categories</h3>
                <div class="card-tools">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" title="Add Category" data-bs-target="#create"><i class="bi bi-plus-lg"></i> Add Category</button>
                </div>
            </div>
            <div class="card-body">

                        <div class="table-responsive">
                            <table id="fixed-header-1" class="display table nowrap table-striped table-hover"
                                   style="width:100%">
                                <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    @foreach($expense_categories as $category)
                                        <td>{{$category->name}}</td>
                                        <td>
                                            <a href="#">
                                                <button class="btn btn-sm btn-info"
                                                        data-id="{{$category->id}}"
                                                        data-name="{{$category->name}}"
                                                        type="button"
                                                        data-bs-toggle="modal" data-bs-target="#edit">Edit
                                                </button>
                                            </a>
                                            <a href="#">
                                                <button class="btn btn-sm btn-danger"
                                                        data-id="{{$category->id}}"
                                                        data-name="{{$category->name}}" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#delete">
                                                    Delete
                                                </button>
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
@include('masters.expense_category.create')
    @include('masters.expense_category.delete')
    @include('masters.expense_category.edit')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        var title = document.title;
        document.title = title.concat(" | Expense Category");
    </script>
    <script>

        $('#edit').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var modal = $(this);

            modal.find('.modal-body #id_edit').val(button.data('id'));
            modal.find('.modal-body #name_edit').val(button.data('name'))
        });

        $('#delete').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);

            var message = "Are you sure you want to delete '".concat(button.data('name'), "'?");
            var modal = $(this);

            modal.find('.modal-body #message').text(message);
            modal.find('.modal-body #id').val(button.data('id'))
        });

        $('#fixed-header-1').DataTable({
            bAutoWidth: false,
            columnDefs: [
                {
                    "targets": 1,
                    "className": "text-center"
                }
            ]
        });


    </script>
@endpush
