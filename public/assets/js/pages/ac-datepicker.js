'use strict';
$(document).ready(function () {
    $('#d_week').datepicker({
        daysOfWeekDisabled: "2"
    });

    $('#d_highlight').datepicker({
        daysOfWeekHighlighted: "1"
    });

    $('#d_auto').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#date_edit').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

     $('#due_date').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });
     $('#expire_d').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });
        $('#expire_date_1').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });
         $('#expire_date_2').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

     $('#credit_sale_date').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });
       $('#cash_sale_date').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });
        $('#rec_d').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_1').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_2').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_3').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_4').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_5').datepicker({
        autoclose: true,
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    });

    $('#d_auto_6').datepicker({
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        changeYear: true
    }).on('change', function(){
        $('.datepicker').hide();
    }).attr('readonly','readonly');


    $('#d_disable').datepicker({
        datesDisabled: ['10/15/2018', '10/16/2018', '10/17/2018', '10/18/2018']
    });

    $('#d_toggle').datepicker({
        keyboardNavigation: false,
        forceParse: false,
        toggleActive: true
    });

    $('#d_today').datepicker({
        keyboardNavigation: false,
        forceParse: false,
        todayHighlight: true
    });

    $('#disp_week').datepicker({
        calendarWeeks: true
    });

    $('#datepicker_range').datepicker({});
});
