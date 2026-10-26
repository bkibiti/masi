@extends("layouts.master")

@section('page_css')
<style>


</style>
@endsection

@section('content-title')
Reports
@endsection

@section('content-sub-title')
<li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
<li class="breadcrumb-item"><a href="#"> Reports </a></li>
@endsection

@section("content")


<div class="col-sm-12">
    <div class="card">
            <div class="card-header">
                <h3 class="card-title">Reports</h3>
            </div>
        <div class="card-body">
     
            @include('reports._search')

         <hr>
     
 
  
        </div>
    </div>
</div>


@endsection

@push("page_scripts")
<script src="{{asset("assets/plugins/bootstrap-datetimepicker/js/bootstrap-datepicker.min.js")}}"></script>
<script src="{{asset("assets/js/pages/ac-datepicker.js")}}"></script>
@include('partials.notification')

@include('reports._hide_show_filters')

<script>
    var title = document.title;
    document.title = title.concat(" | Reports");
</script>

<script>

    $('#fixed-header1').DataTable({
      bAutoWidth: true,
      order: [[0, "desc"]]
    });

    $('#product_list').hide();

    $(function () {
        var start = moment();
        var end = moment();

        $('#from_date').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                maxDate: end,
                autoUpdateInput: true,
                locale: {
                    format: 'DD-M-YYYY'
                }
            });
    });

    $(function () {
        var start = moment();
        var end = moment();

        $('#to_date').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                maxDate: end,
                autoUpdateInput: true,
                locale: {
                    format: 'DD-M-YYYY'
                }
            });
    });





</script>


@endpush