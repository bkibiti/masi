@extends("layouts.master")

@section('page_css')
<style>


</style>
@endsection

@section('content-title')
Expense
@endsection

@section('content-sub-title')
<li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
<li class="breadcrumb-item"><a href="#"> Expense Management / Expense </a></li>
@endsection

@section("content")

<style>
    ol.linenums {
        margin: 0 0 0 -8px;
    }

    .ms-container {
        background: transparent url('../assets/plugins/multi-select/img/switch.png') no-repeat 50% 50%;
        width: 100%;
    }

    .ms-selectable,
    .ms-selection {
        background: #fff;
        color: #555555;
        float: left;
        width: 45%;
    }

    #loading {
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        position: fixed;
        display: none;
        opacity: 0.7;
        background-color: #fff;
        z-index: 99;
        text-align: center;
    }

    #loading-image {
        position: absolute;
        top: 50%;
        left: 50%;
        z-index: 100;
    }

    input[type=button]:focus {
        background-color: #748892;
        border-color: #748892;
        color: white;
    }
</style>

<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
                <h3 class="card-title">Expenses</h3>

                <div class="card-tools">
                    @can('Add Expenses')
                        <a href="{{ route('expense.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Record Expense</a>
                    @endcan
                </div>
            </div>
        <div class="card-body">
            <form action="{{ route('expense.search') }}" method="GET">
                <div class="row g-2 g-md-3 mb-3">
                    <div class="col-12 col-md">
                        <label for="filter_category" class="form-label history-filter-label">Category</label>
                        <select name="expense_category" id="filter_category" class="form-select">
                            <option value="0">All Categories</option>
                            @foreach($expense_category as $x)
                                <option value="{{ $x->id }}" @selected(request('expense_category') == $x->id)>{{ $x->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @include('partials.date_filter', ['resetRoute' => 'expense.index'])
                </div>
            </form>
    <hr>
            <div class="table-responsive">
                <table id="fixed-header1" class="display table nowrap table-striped table-hover" style="width:100%">

                    <thead>
                            <tr>
                                <th>Expense Date</th>
                                <th>Expense Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                @can('View Advanced Dashboard')
                                <th>Added By</th>
                                @endcan
                                @can('Edit Expenses')
                                <th>Action</th>
                                @endcan
                            </tr>
                    </thead>
                    <tbody>

                        @foreach($Expenses as $x)
                        <tr>
                            {{-- <td>{{$x->created_at}}</td> --}}
                            <td data-order="{{ $x->created_at }}">{{date_format(new DateTime($x->created_at),'d M Y')}}</td>
                            <td>{{$x->category->name}}</td>
                            <td>{{ $x->expense_description }}</td>
                            <td>{{number_format($x->amount,2)}}</td>
                            @can('View Advanced Dashboard')
                            <td>{{ $x->user->name ?? '—' }}</td>
                            @endcan
                                @can('Edit Expenses')
                                <td>

                                    <a href="#">
                                        <button class="btn btn-info btn-sm" data-expense_description="{{$x->expense_description}}"
                                            data-id="{{$x->id}}"   data-date="{{$x->created_at}}"  
                                            data-amount="{{$x->amount}}"    data-expense_category="{{$x->expense_category_id}}"
                                            type="button" data-bs-toggle="modal" data-bs-target="#edit">Edit</button>
                                    </a>
                                </td>

                                @endcan

                    
            

                        </tr>
                        @endforeach


                    </tbody>
            
                </table>
            

            </div>

            <hr>
            {{-- show staff summary --}}
            @if ($total > 0)
                
                <div class="row mb-3">
                    <div class="col-md-2"> <h6> Total Expenses</h6></div>
                    <div class="col-md-2">
                        <h6>{{number_format($total,2)}}</h6>
                    </div>
                </div>
            @endif
  
        </div>
    </div>
</div>

@include("expense.edit")


@endsection

@push("page_scripts")
@include('partials.notification')

<script>
    var title = document.title;
    document.title = title.concat(" | Expenses");
</script>

<script>

    $('#fixed-header1').DataTable({
      bAutoWidth: true,
      order: [[0, "desc"]]
    });


    $('#edit').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget)
        var modal = $(this)

        modal.find('.modal-body #date2').val(String(button.data('date')).substring(0, 10));
        modal.find('.modal-body #expense_amount2').val(button.data('amount'));
        modal.find('.modal-body #expense_category2').val(button.data('expense_category'));
        modal.find('.modal-body #expense_description2').val(button.data('expense_description'));
        modal.find('.modal-body #id').val(button.data('id'));
      });//end edit



</script>


@endpush