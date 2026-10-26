var cart = [];
var default_cart = [];
var order_cart = [];

//main tranfer table
var cart_table = $('#fixed-header1').DataTable({
    'language': {
        'emptyTable': "Please add issue products"
    },
    'columns': [
        {title: "Product Name"},
        {title: "Buy Price "},
        {title: "Issued Quantity"},
        {title: "Sub Total"},
        {
            title: "Action",
            defaultContent: "<input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/>"
        }

    ]
});

//table reprint issue
var table_reprint = $('#fixed-header-re-print').DataTable();

//table reprint issue filter
var table_reprint_filter = $('#fixed-header-re-print-filter').DataTable({
    'columns': [
        {'data': 'issue_no'},
        {'data': 'issue_location.name'},
        {
            'data': "action",
            defaultContent: "<button type='button' id='show_more' class='btn btn-sm btn-rounded btn-success'>Open</button>"
        }

    ]
});

//show table details
var table_stock_detail = $('#fixed-header2').DataTable({
    bPaginate: false,
    bInfo: false,
    'columns': [
        {'data': 'issue_no'},
        {'data': 'current_stock.product.name'},
        {'data': 'issue_location.name'},
        {
            'data': "action",
            defaultContent: "<button type='button' id='return' class='btn btn-sm btn-rounded btn-primary'>Return</button>"
        }

    ]

});


$('#tbodyRePrintFilter').on('click', '#show_more', function () {
    var row_data = table_reprint_filter.row($(this).parents('tr')).data();
    // console.log(row_data.issue_no);
    showIssue(row_data.issue_no);
});


$('#tbodyRePrintFilter').on('click', '#print', function () {
    var row_data = table_reprint_filter.row($(this).parents('tr')).data();

    var no = document.getElementById("issue_nos");
    no.value = row_data.issue_no;

});

$('#tbodyRePrint').on('click', '#print', function () {
    var row_data = table_reprint.row($(this).parents('tr')).data();

    var no = document.getElementById("issue_nos");
    no.value = row_data[0];

});


$('#tbody1').on('click', '#return', function () {
    var row_data = table_stock_detail.row($(this).parents('tr')).data();
    var index = table_stock_detail.row($(this).parents('tr')).index();

    var date = row_data.created_at.split(' ')[0];

    //return modal form show with data
    $('#edit').find('.modal-body #name_of_item').val(row_data.current_stock.product.name);
    $('#edit').find('.modal-body #quantity').val(numberWithCommas(row_data.quantity));

    $('#edit').find('.modal-body #issue_no_edit').val(row_data.issue_no);
    $('#edit').find('.modal-body #issued_date').val(date);
    $('#edit').find('.modal-body #stock_id').val(row_data.stock_id);
    $('#edit').find('.modal-body #issued_to').val(row_data.issue_location.name);
    $('#edit').find('.modal-body #unit_cost').val(row_data.unit_cost);
    $('#edit').find('.modal-body #row_index').val(index);
    $('#edit').find('.modal-body #id').val(row_data.id);
    $('#edit').find('.modal-body #current_stock').val(row_data.current_stock.quantity);


    $('#edit').modal('show');

});


$('#issue_return').on('submit', function () {
    var index = document.getElementById('row_index').value;

    table_stock_detail.row(index).remove().draw();

});


// $('#cancel').on('click', function () {
//
//     var from = document.getElementById("from_id");
//     var from_id = from.options[from.selectedIndex].value;
//
//     var date = document.getElementById("d_auto_7").value;
//
//     //make ajax call
//     retrieveFilterIssue(date, from_id);
//
// });


$('#rtn_qty').on('change', function () {

    var quantity = document.getElementById('rtn_qty').value;
    var quantity_issued = document.getElementById('quantity').value;

    console.log(quantity);
    console.log(quantity_issued.split('.')[0]);

    if (Number(quantity) > Number(quantity_issued.split('.')[0])) {  //   3 > 10

        document.getElementById('save_btn').disabled = 'true';
        document.getElementById('qty_error').style.display = 'block';
        $('#qty_error').text('The maximum quantity is ' + quantity_issued);

    }
    else {

        document.getElementById('qty_error').style.display = 'none';
        $('#save_btn').prop('disabled', false);

    }

});


$('#d_auto_7').datepicker({
    todayHighlight: true,
    format: 'yyyy-mm-dd',
    changeYear: true,

}).on('change', function () {

    filterIssue($(this).val());
    $('.datepicker').hide();
}).attr('readonly', 'readonly');


function filterIssue(data) {

    var date = data;
    var from = document.getElementById("from_id");
    var from_id = from.options[from.selectedIndex].value;

    //make ajax call
    retrieveFilterIssue(date, from_id);

}

function issueLocation() {

    var from = document.getElementById("from_id");
    var from_id = from.options[from.selectedIndex].value;

    var date = document.getElementById("d_auto_7").value;

    //make ajax call
    retrieveFilterIssue(date, from_id);

}


function retrieveFilterIssue(data, data1) {

    var date = data;
    var from_id = data1;

    var ajaxurl = config.routes.stockIssueFilter;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            date: date,
            from_id: from_id
        },
        success: function (data) {
            document.getElementById("tbodyRePrint").style.display = 'none';
            document.getElementById("tbodyRePrintFilter").style.display = 'block';
            document.getElementById("tbody1").style.display = 'none';

            bindDataFilter(data);


        },
        complete: function () {
            $('#loading').hide();
        }
    });

}


function showIssue(data) {
    var issue_no = data;

    var ajaxurl = config.routes.stockIssueShow;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            issue_no: issue_no
        },
        success: function (data) {
            document.getElementById("tbodyRePrint").style.display = 'none';
            document.getElementById("tbodyRePrintFilter").style.display = 'none';
            document.getElementById("tbody1").style.display = 'block';

            bindDataShow(data);

        },
        complete: function () {
            $('#loading').hide();
        }
    });
}

function bindDataShow(data) {

    table_stock_detail.clear();
    table_stock_detail.rows.add(data);
    table_stock_detail.draw();
    // $('#show').modal('show');

}

function bindDataFilter(data) {

    table_reprint_filter.clear();
    table_reprint_filter.rows.add(data);
    table_reprint_filter.draw();

}

function numberWithCommas(digit) {
    return String(parseFloat(digit)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
