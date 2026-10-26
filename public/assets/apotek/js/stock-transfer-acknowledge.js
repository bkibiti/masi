//main filter tranfer table
var table_stock_transfer = $('#fixed-header1').DataTable({
    'language': {
        'emptyTable': "Please choose 'From' and 'To' store inorder to complete pending transfers"
    },
    'columns': [
        {'data': 'transfer_no'},
        {'data': 'current_stock.product.name'},
        {
            'data': 'transfer_qty', render: function (data) {
                return numberWithCommas(data);
            }
        },
        {
            'data': 'action',
            defaultContent: "<div class='row'><button id='completed' class='btn btn-sm btn-rounded btn-success' type='button'>Acknowledge</button></div>"
        }
    ]
});


//main transfer table list
var table_stock_transfer_list = $('#fixed-header-main').DataTable();


//show table details
var table_stock_detail = $('#fixed-header2').DataTable({
    'columns': [
        {'data': 'from_store.name'},
        {'data': 'to_store.name'},
        {'data': 'current_stock.product.name'},
        {
            'data': 'transfer_qty', render: function (data) {
                return numberWithCommas(data);
            }
        },
        {'data': 'remarks'}
    ]

});

var table_re_print1 = $('#fixed-header-re-print').DataTable({
    searching: true,
    bPaginate: true,
    bInfo: true
});

//reprint stock tranfer table
var table_re_print = $('#fixed-header-re-print1').DataTable({
    ordering: false,
    'columns': [
        {'data': 'transfer_no'},
        {'data': 'date'},
        {
            'data': 'transfer_qty', render: function (data) {
                return numberWithCommas(data);
            }
        },
        {
            'data': 'action',
            defaultContent: "<div class='row'><button id='show-infos' class='btn btn-sm btn-rounded btn-success' type='button'>Show</button><button id='prints' class='btn btn-sm btn-rounded btn-secondary'><span class='fa fa-print' aria-hidden='true'></span>Print</button></div>"
        }
    ]
});


function storeSelect() {


    try {
        var from = document.getElementById("from_id");
        var from_id = from.options[from.selectedIndex].value;
        var to = document.getElementById("to_id");
        var to_id = to.options[to.selectedIndex].value

    } catch (e) {

    }

    if (from_id != null || from_id != 0) {
        $('#to_id').prop('disabled', false);
    }

    if (from_id === to_id) {
        notify('From and To cannot be the same', 'top', 'right', 'info');
        return false;
    }

    filterByStore(from_id, to_id);

}


function storeSelectRePrint() {


    try {
        var from = document.getElementById("from_id");
        var from_id = from.options[from.selectedIndex].value;
        var to = document.getElementById("to_id");
        var to_id = to.options[to.selectedIndex].value

    } catch (e) {

    }

    if (from_id != null || from_id != 0) {
        $('#to_id').prop('disabled', false);
    }

    filterByStoreRePrint(from_id, to_id);

}

function filterByStore(data, data1) {
    var from_val = data;
    var to_val = data1;

    var ajaxurl = config.routes.stockTransferFilterDetail;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            from_val: from_val,
            to_val: to_val
        },
        success: function (data) {
            //show table and hide them
            document.getElementById('tbody').style.display = 'block';
            document.getElementById('tbody-main').style.display = 'none';
            bindData(data);

        },
        complete: function () {
            $('#loading').hide();
        }
    });
}

function filterByStoreRePrint(data, data1) {
    var from_val = data;
    var to_val = data1;


    var ajaxurl = config.routes.stockTransferFilter;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            from_val: from_val,
            to_val: to_val
        },
        success: function (data) {
            document.getElementById('tbodyRePrint1').style.display = 'block';
            document.getElementById('tbodyRePrint').style.display = 'none';
            bindDataRePrint(data);

        },
        complete: function () {
            $('#loading').hide();
        }
    });
}


$('#tbody').on('click', '#completed', function () {
    var datas = table_stock_transfer.row($(this).parents('tr')).data();
    var index = table_stock_transfer.row($(this).parents('tr')).index();

    // console.log(datas);

    $('#completes').modal('show');

    $('#completes').find('.modal-body #transfer_no').val(datas.transfer_no);
    $('#completes').find('.modal-body #from').val(datas.from_store.name);
    $('#completes').find('.modal-body #to').val(datas.to_store.name);
    $('#completes').find('.modal-body #quantity_trn').val(numberWithCommas(datas.transfer_qty));
    $('#completes').find('.modal-body #id').val(datas.id);
    $('#completes').find('.modal-body #stock_id').val(datas.stock_id);

});


$('#tbody').on('click', '#shows', function () {

    var datas = table_stock_transfer.row($(this).parents('tr')).data();
    var index = table_stock_transfer.row($(this).parents('tr')).index();

    //get the store names first

    var from = document.getElementById("from_id");
    var from_id = from.options[from.selectedIndex].value;
    var to = document.getElementById("to_id");
    var to_id = to.options[to.selectedIndex].value;

    if (from_id === to_id) {
        notify('Cannot view transfer of the same store', 'top', 'right', 'info');
        return false;
    } else if (from_id === 0 && to_id !== 0) {
        notify('Cannot view a transfer that has no a From store', 'top', 'right', 'info');
        return false;
    } else if (from_id !== 0 && to_id === 0) {
        notify('Cannot view a transfer that has no a To store', 'top', 'right', 'info');
        return false;
    }

    showStockTransfer(datas.transfer_no, from_id, to_id);

});

//table reprint main table
$('#tbodyRePrint1').on('click', '#show-infos', function () {

    var datas = table_re_print.row($(this).parents('tr')).data();
    var index = table_re_print.row($(this).parents('tr')).index();

    //get the store names first
    try {
        var from = document.getElementById("from_id");
        var from_id = from.options[from.selectedIndex].value;
        var to = document.getElementById("to_id");
        var to_id = to.options[to.selectedIndex].value

    } catch (e) {

    }

    showStockTransfer(datas.transfer_no, from_id, to_id);

});

$('#tbodyRePrint').on('click', '#show-info', function () {

    var datas = table_re_print1.row($(this).parents('tr')).data();
    var index = table_re_print1.row($(this).parents('tr')).index();

    //get the store names first
    try {
        var from = document.getElementById("from_id");
        var from_id = from.options[from.selectedIndex].value;
        var to = document.getElementById("to_id");
        var to_id = to.options[to.selectedIndex].value

    } catch (e) {

    }

    showStockTransfer(datas[0], from_id, to_id);

});

//re-print transfer button
$('#tbodyRePrint1').on('click', '#prints', function () {

    var datas = table_re_print.row($(this).parents('tr')).data();
    var index = table_re_print.row($(this).parents('tr')).index();

    document.getElementById("transfer_no").value = datas.transfer_no;

});

$('#tbodyRePrint').on('click', '#print', function () {

    var datas = table_re_print1.row($(this).parents('tr')).data();
    var index = table_re_print1.row($(this).parents('tr')).index();

    document.getElementById("transfer_no").value = datas[0];
});


$('#quantity_rcvd').on('change', function () {

    var in_stock = document.getElementById('quantity_trn').value;
    var returned = document.getElementById('quantity_rcvd').value;

    /*check if returned exceed the available*/
    if (Number(returned) > Number(in_stock.split('.')[0])) {

        document.getElementById('quantity_rcvd').style.borderColor = 'red';
        $('#span_danger').text('The maximum quantity is ' + in_stock);
        document.getElementById('span_danger').style.display = 'block';
        $('#ack').prop('disabled', true);

        return;
    }

    $('#ack').prop('disabled', false);
    document.getElementById('quantity_rcvd').style.borderColor = '#ced4da';
    document.getElementById('span_danger').style.display = 'none';

    /*check if return is equal + add attribute required*/
    if (Number(returned) < Number(in_stock.split('.')[0])) {
        $('#remarks').prop('required', true);
    } else {
        $('#remarks').prop('required', false);

    }


});


function showStockTransfer(data, data1, data2) {
    var transfer_no = data;
    var from_val = data1;
    var to_val = data2;

    var ajaxurl = config.routes.stockTransferShow;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            transfer_no: transfer_no,
            from_val: from_val,
            to_val: to_val
        },
        success: function (data) {
            bindDataShow(data);
            console.log(data);


        },
        complete: function () {
            $('#loading').hide();
        }
    });
}


function updateStockTransfer(data, data1, data2) {
    var transfer_no = data;
    var from_val = data1;
    var to_val = data2;

    var ajaxurl = config.routes.stockTransferUpdate;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            transfer_no: transfer_no,
            from_val: from_val,
            to_val: to_val
        },
        success: function (data) {
            bindData(data);

        },
        complete: function () {
            $('#loading').hide();
        }
    });
}

//bind data to main data table
function bindData(data) {

    table_stock_transfer.clear();
    table_stock_transfer.rows.add(data);
    table_stock_transfer.draw();

}

function bindDataRePrint(data) {

    table_re_print.clear();
    table_re_print.rows.add(data);
    table_re_print.draw();

}

//bind data to show modal data table
function bindDataShow(data) {

    table_stock_detail.clear();
    table_stock_detail.rows.add(data);
    table_stock_detail.draw();
    $('#show').modal('show');

}

function numberWithCommas(digit) {
    return String(parseFloat(digit)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
