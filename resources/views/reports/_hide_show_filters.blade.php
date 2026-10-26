<script>
    var NeedDateFilter = ["1","4","7"];
    var NoDateFilter = ["2","3","5"];

    var reportType = $('#report_type').val();


    if( NeedDateFilter.includes(reportType)){
        $('#fromDateDiv').show();
        $('#toDateDiv').show();
    }
    if( NoDateFilter.includes(reportType)){
        $('#fromDateDiv').hide();
        $('#toDateDiv').hide();
    }

    $('#report_type').change(function () {
        var value = $(this).val();
     
        if( NeedDateFilter.includes(value)){
            $('#fromDateDiv').show();
            $('#toDateDiv').show();
        }
        if( NoDateFilter.includes(value)){
            $('#fromDateDiv').hide();
            $('#toDateDiv').hide();
        }
        if(value == 7){
            $('#product_list').show();
        }else{
            $('#product_list').hide();
        }
    
    });


</script>