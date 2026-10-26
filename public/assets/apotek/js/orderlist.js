var details = [];
var order_items = [];

var order_list_table = $('#order_list_table').DataTable({
    searching: true,
    bPaginate: false,
    bInfo: true,
    ordering: false
});

var order_history_datatable = $('#order_history_datatable').DataTable({
    searching: true,
    bPaginate: true,
    bInfo: true,
    columns: [
        {data: 'order_number'},
        {data: 'supplier.name'},
        {
            data: 'ordered_at', render: function (ordered_at) {
                return moment(ordered_at).format('D-M-YYYY');
            }
        },
        {
            data: 'total_amount', render: function (total_amount) {
                return formatMoney(total_amount);
            }
        },
        {
            data: "action",
            defaultContent: "<input type='button' value='Show' id='dtl_btn' class='btn btn-success btn-rounded btn-sm' size='2'/><button id='print_btn' class='btn btn-secondary btn-rounded btn-sm'><span class='fa fa-print' aria-hidden='true'></span> Print</button><input type='button' value='Cancel' id='cancel_btn' class='btn btn-danger btn-rounded btn-sm' size='2' />"
        }
    ]
});

var order_details_table = $('#order_details_table').DataTable({
    searching: true,
    bPaginate: true,
    bInfo: true,
    data: order_items,
    columns: [
        {title: "ID"},
        {title: "Product Name"},
        {title: "Quantity"},
        {title: "Price"},
        {title: "VAT"},
        {title: "Discount"},
        {title: "Amount"},
        {
            title: "Action",
            defaultContent: "<input type='button' value='Receive' id='rtn_btn' class='btn btn-primary btn-rounded btn-sm'/>"
        }
    ]
});

$(function () {
    var start = moment().subtract(6, 'days');
    var end = moment();

    function cb(start, end) {
        $('#date_filter span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
    }

    $('#date_filter').daterangepicker({
        startDate: start,
        endDate: end,
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


function formatMoney(amount, decimalCount = 2, decimal = ".", thousands = ",") {
    try {
        decimalCount = Math.abs(decimalCount);
        decimalCount = isNaN(decimalCount) ? 2 : decimalCount;

        const negativeSign = amount < 0 ? "-" : "";

        let i = parseInt(amount = Math.abs(Number(amount) || 0).toFixed(decimalCount)).toString();
        let j = (i.length > 3) ? i.length % 3 : 0;

        return negativeSign + (j ? i.substr(0, j) + thousands : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousands) + (decimalCount ? decimal + Math.abs(amount - i).toFixed(decimalCount).slice(2) : "");
    } catch (e) {
        console.log(e)
    }
}

$('#order_history_datatable tbody').on('click', '#print_btn', function () {
    var data = order_history_datatable.row($(this).parents('tr')).data();

    document.getElementById('order_no').value = data.details[0].order_id;

});

function getOrderHistory() {
    var range = document.getElementById("date_filter").value;
    var date = range.split('-');
    $.ajax({
        url: config.routes.getOrderHistory,
        data: {
            "_token": '{{ csrf_token() }}',
            "date": date
        },
        type: 'get',
        dataType: 'json',
        success: function (data) {

            data.forEach(function (data) {

                if (data.status === 'Cancelled') {
                    data.action = "<input type='button' value='Show' id='dtl_btn' class='btn btn-success btn-rounded btn-sm' size='2'/><button id='print_btn' class='btn btn-secondary btn-rounded btn-sm'><span class='fa fa-print' aria-hidden='true'></span> Print</button><span class='badge badge-warning badge-lg'>Cancelled</span>"
                }
            });
            order_history_datatable.clear();
            order_history_datatable.rows.add(data);
            order_history_datatable.draw();
        },

    });

}

$('#order_history_datatable tbody').on('click', '#dtl_btn', function () {
    var data = order_history_datatable.row($(this).parents('tr')).data();
    var index = order_history_datatable.row($(this).parents('tr')).index();
    orderDetails(data.details);
    $('#purchases-details').modal('show');

});

function orderDetails(items) {
    document.getElementById('order_details_table').style.display = 'true';
    order_items = [];
    items.forEach(function (item) {
        var item_data = [];
        item_data.push(5);
        item_data.push(item.name);
        item_data.push(item.quantity);
        item_data.push(formatMoney(item.price));
        item_data.push(formatMoney(item.vat));
        item_data.push(formatMoney(item.discount));
        item_data.push(formatMoney(item.amount));
        order_items.push(item_data);

    });
    order_details_table.clear();
    order_details_table.rows.add(order_items);
    order_details_table.column(0).visible(false);
    order_details_table.column(7).visible(false);
    order_details_table.draw();


    $('#cancel').on('click', function () {
        document.getElementById('purchases').style.display = 'block';
        document.getElementById('items').style.display = 'none';
    });

}

$('#order_history_datatable tbody').on('click', '#cancel_btn', function () {
    var data = order_history_datatable.row($(this).parents('tr')).data();
    var index = order_history_datatable.row($(this).parents('tr')).index();
    $('#cancel-order').modal('show');
    var message = "Are you sure you want to Cancel Order '".concat(data.order_number, "'?");
    var modal = $(this);
    $('#cancel-order').find('.modal-body #message').text(message);
    $('#cancel-order').find('.modal-body #delete_id').val(data.id);

});


$('#order_history_datatable tbody').on('click', '#print_btn', function () {
    var data = order_history_datatable.row($(this).parents('tr')).data();
    // var index =  order_history_datatable.row( $(this).parents('tr') ).index();
    //     var print_data = [];
    //     print_data.push(data);

    document.getElementById('order_no').value = data.details[0].order_id;


});


