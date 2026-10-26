var table_ledger_filter = $('#fixed-header-ledger').DataTable({
    searching: true,
    bPaginate: true,
    bInfo: true,
    'columns': [
        {'data': 'current_stock.product.name'},
        {'data': 'out_mode'},
        {'data': 'quantity'},
        {'data': 'user.name'},
        {'data': 'updated_at'}
    ]
});

var table_daily_stock = $('#fixed-header2').DataTable({
    searching: true,
    bPaginate: false,
    bInfo: true,
    'columns': [
        {'data': 'product_name'},
        {'data': 'quantity_sold'},
        {'data': 'quantity_on_hand'}
    ]
});

$(function () {

    var start = moment();
    var end = moment();

    function cb(start, end) {
        $('#outgoing-date span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
    }

    $('#outgoing-date').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        maxDate: end,
        autoUpdateInput: true,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'This Year': [moment().startOf('year'), moment()]
        }
    }, cb);

    cb(start, end);

});

//daily stock count date picker
$('#d_auto_8').datepicker({
    todayHighlight: true,
    format: 'yyyy-mm-dd',
    changeYear: true
}).on('change', function () {
    filterByDate();
    $('.datepicker').hide();
}).attr('readonly', 'readonly');

function getOutgoingDate() {
    var dates = document.querySelector('input[name=outgoing-date]').value;
    dates = dates.split('-');
    outgoingFilter(dates);
}

//daily stock count filter by date
function filterByDate() {
    var date = document.getElementById('d_auto_8').value;

    if (date == '') {
        return false;
    }

    var ajaxurl = config.routes.filterShow;

    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            date: date
        },
        success: function (data) {
            bindStockCountData(data);


        },
        complete: function () {
            $('#loading').hide();
        }
    });

}


// outgoing stock filter ajax call
function outgoingFilter(dates) {

    var ajaxurl = config.routes.ledgerShow;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            date: dates[1],
            date_from: dates[0]
        },
        success: function (data) {
            document.getElementById('tbodyRePrintFilter').style.display = 'block';
            document.getElementById('tbody').style.display = 'none';
            bindData(data);
        },
        complete: function () {
            $('#loading').hide();
        }
    });

}

function bindData(data) {
    table_ledger_filter.clear();
    table_ledger_filter.rows.add(data);
    table_ledger_filter.draw();
}

function bindStockCountData(data) {
    table_daily_stock.clear();
    table_daily_stock.rows.add(data);
    table_daily_stock.draw();
}
