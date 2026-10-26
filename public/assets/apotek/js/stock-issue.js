var cart = [];
var default_cart = [];
var order_cart = [];

var edit_btn_status = 0;


//main tranfer table
var cart_table = $('#fixed-header1').DataTable({
    ordering: false,
    'language': {
        'emptyTable': "Please add products"
    },
    'columns': [
        {title: "Product Name"},
        {title: "Quantity on Hand"},
        {title: "Buy Price "},
        {title: "Sell Price"},
        {title: "Stock Id"},
        {title: "Product Id"},
        {title: "Issue Quantity"},
        {title: "Sub Total"},
        {
            title: "Action",
            defaultContent: "<input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/>"
        }

    ],
    "columnDefs": [
        {
            "targets": [3],
            "visible": false,
            "searchable": false
        }, {
            "targets": [4],
            "visible": false,
            "searchable": false
        }, {
            "targets": [5],
            "visible": false,
            "searchable": false
        }
    ]

});

//table reprint issue
var table_reprint = $('#fixed-header-re-print').DataTable();

//table reprint issue filter
var table_reprint_filter = $('#fixed-header-re-print-filter').DataTable({
    "aaSorting": [],
    'columns': [
        {'data': 'issue_no'},
        {
            'data': 'created_at', render: function (date) {
                return moment(date).format('DD-MM-YYYY');
            }
        },
        {
            'data': 'quantity', render: function (data) {
                return numberWithCommas(data);
            }
        },
        {'data': 'issue_location.name'},
        {
            'data': "action",
            defaultContent: "<button type='button' id='show_more' class='btn btn-sm btn-rounded btn-success'>Show</button><button id='print' name='print' type='submit' class='btn btn-sm btn-rounded btn-secondary'><span class='fa fa-print' aria-hidden='true'></span> Print</button>"
        }

    ]
});

//show table details
var table_stock_detail = $('#fixed-header2').DataTable({
    'columns': [
        {'data': 'issue_no'},
        {'data': 'current_stock.product.name'},
        {
            'data': 'quantity', render: function (data) {
                return numberWithCommas(data);
            }
        },
        {'data': 'issue_location.name'}

    ]

});


function calculateTotal() {

    var total = 0;
    var order_cart = [];

    var stringified_cart;
    if (cart[0]) {


        var reduced__obj_cart = {}, incremental_cart;

        for (var i = 0, c; c = cart[i]; ++i) {
            if (undefined === reduced__obj_cart[c[0]]) {
                reduced__obj_cart[c[0]] = c;
                reduced__obj_cart[c[0]][7] =
                    formatMoney(Number(reduced__obj_cart[c[0]][6]) * parseFloat(c[2].replace(/\,/g, ''), 10));
            } else {

                reduced__obj_cart[c[0]][6] = Number(reduced__obj_cart[c[0]][6]) + Number(c[6]);
                if (reduced__obj_cart[c[0]][6] > Number(c[1])) {
                    reduced__obj_cart[c[0]][6] = Number(c[1]);
                    reduced__obj_cart[c[0]][7] = formatMoney(Number(c[1]) * parseFloat(c[2].replace(/\,/g, ''), 10));
                } else {
                    reduced__obj_cart[c[0]][7] =
                        formatMoney(Number(reduced__obj_cart[c[0]][6]) * parseFloat(c[2].replace(/\,/g, ''), 10));
                }
            }
        }

        incremental_cart = Object.keys(reduced__obj_cart).map(function (val) {
            return reduced__obj_cart[val]
        });

        cart = incremental_cart;

        cart.forEach(function (item, index, arr) {
            var sale_products = {};
            sale_products.quantityIn = item[1];
            sale_products.issuedQty = item[6];
            sale_products.issue_qty = item[6];
            sale_products.sub_totals = item[7];
            sale_products.quantity = item[1];
            sale_products.buy_price = item[2];
            sale_products.sell_price = item[3];
            sale_products.product_id = item[5];
            sale_products.stock_id = item[4];
            order_cart.push(sale_products);
            total += parseFloat(item[7].replace(/\,/g, ''), 10);
        });
        stringified_cart = JSON.stringify(order_cart);
    }
    document.getElementById("order_cart").value = stringified_cart;
    document.getElementById("total").value = formatMoney(total);
}

function val() {
    var item = [];
    var cart_data = [];
    var product = document.getElementById("select_id").value;
    var selected_fields = product.split(',');
    var item_name = selected_fields[0];
    var buy_price = Number(selected_fields[1]);
    var sell_price = Number(selected_fields[2]);
    var quantity = selected_fields[4];
    var product_id = Number(selected_fields[5]);
    var stock_id = selected_fields[3];
    var sub_total = 0;
    var issue_qty = 1;
    item.push(item_name);
    item.push(quantity);
    item.push(formatMoney(buy_price));
    item.push(formatMoney(sell_price));
    item.push(stock_id);
    item.push(product_id);
    item.push(issue_qty);
    item.push(formatMoney(sub_total));

    cart_data.push(formatMoney(buy_price));
    cart_data.push(formatMoney(sub_total));
    default_cart.push(cart_data);
    cart.push(item);
    calculateTotal();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();

}


function deselect() {
    calculateTotal();
    cart = [];
    default_cart = [];
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
}

$('#tbody').on('click', '#edit_btn', function () {

    var quantity;
    if (edit_btn_status === 0) {
        /*not set then set it*/
        var row_data = cart_table.row($(this).parents('tr')).data();
        var index = cart_table.row($(this).parents('tr')).index();
        quantity = row_data[6];

        if (!(isNaN(Number(quantity)))) {
            row_data[6] = "<div><input type='number' min='1' class='form-control' id='edit_quantity' required/><span id='span_danger' style='display: none; color: red; font-size: 0.9em;'></span></div>";
            cart[index] = row_data;
            cart_table.clear();
            cart_table.rows.add(cart);
            cart_table.draw();
            document.getElementById("edit_quantity").value = quantity;
        }

        edit_btn_status = 1;

    }


});


//multiply quantity with amount....in stock transfer i don not need it
$('#tbody').on('change', '#edit_quantity', function () {
    edit_btn_status = 0;
    var row_data = cart_table.row($(this).parents('tr')).data();
    var index = cart_table.row($(this).parents('tr')).index();

    row_data[6] = document.getElementById("edit_quantity").value;

    if (Number(row_data[6]) > Number(row_data[1])) {
        row_data[6] = 0;
        // order_cart[index].issuedQty = row_data[3];
        document.getElementById("edit_quantity").style.borderColor = 'red';
        document.getElementById("span_danger").style.display = 'block';
        $('#span_danger').text('Maximum quantity is ' + row_data[1]);
        // delete order_cart[index]['issuedQty'];
        row_data[7] = formatMoney(parseFloat(default_cart[index][0].replace(/\,/g, ''), 10) * row_data[6]);
        return;
    } else {
        //multiply sell and the issued qty
        row_data[7] = formatMoney(parseFloat(default_cart[index][0].replace(/\,/g, ''), 10) * row_data[6]);

    }

    cart[index] = row_data;
    calculateTotal();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
});

$('#tbody').on('click', '#delete_btn', function () {
    edit_btn_status = 0;
    var index = cart_table.row($(this).parents('tr')).index();
    cart.splice(index, 1);
    default_cart.splice(index, 1);
    calculateTotal();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
});


$('#tbodyRePrintFilter').on('click', '#show_more', function () {
    var row_data = table_reprint_filter.row($(this).parents('tr')).data();
    showIssue(row_data.issue_no);
});


$('#tbodyRePrintFilter').on('click', '#print', function () {
    var row_data = table_reprint_filter.row($(this).parents('tr')).data();

    var no = document.getElementById("issue_nos");
    no.value = row_data.issue_no;

});


$('#deselect-all').on('click', function () {
    deselect();
});

$('#issue').on('submit', function () {

    var check_cart = document.getElementById("order_cart").value;
    var date = document.getElementById("d_auto_6").value;

    console.log(check_cart);

    //if cart is empty, then dont submit form
    if (check_cart === '') {
        notify('Please complete issue', 'top', 'right', 'warning');
        return false;

    }

    var check_cart_to_array = JSON.parse(check_cart);

    //check if cart is empty

    if (!(check_cart_to_array && check_cart_to_array.length)) {
        notify('Issue list is empty', 'top', 'right', 'warning');
        return false;
    }


    var from = document.getElementById("from_id");
    var from_id = from.options[from.selectedIndex].value;
    var select = document.getElementById("select_id");
    var select_id = select.options[select.selectedIndex].value;

    //if nothing has been selected do not submit form
    if (select_id === '0' || from_id === '0') {
        notify('Please complete issue', 'top', 'right', 'warning');
        return false;

    }

    //check_cart the cart array if issued qty is missing
    var tran = "issuedQty";

    for (var key in check_cart_to_array) {

        if (check_cart_to_array[key].hasOwnProperty(tran)) {
            //present
            if (Number(check_cart_to_array[key][tran]) === Number(0)) {
                notify('Cannot issue 0 quantity', 'top', 'right', 'warning');
                return false;
            }
        } else {
            //not present
            notify('Please check issued quantities', 'top', 'right', 'warning');
            return false;
        }

    }

    /*check issued date*/
    if (date === '') {
        notify('Please check issue date', 'top', 'right', 'warning');
        document.getElementById('date_border').style.borderColor = 'red';
        return false;
    }

    document.getElementById('date_border').style.borderColor = 'white';

    location.reload();

});


$('#tbodyRePrint').on('click', '#print', function () {
    var row_data = table_reprint.row($(this).parents('tr')).data();

    var no = document.getElementById("issue_nos");
    no.value = row_data[0];

});

$('#d_auto_7').datepicker({
    todayHighlight: true,
    format: 'yyyy-mm-dd',
    changeYear: true,
    maxDate: 0

}).on('change', function () {

    filterIssue($(this).val());
    $('.datepicker').hide();
}).attr('readonly', 'readonly');

$(function () {
    var start = moment();
    var end = moment();

    $('#d_auto_71').daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        maxDate: end,
        autoUpdateInput: false,
        locale: {
            format: 'DD-M-YYYY'
        }
    });
});

$('input[name="issued_date"]').on('apply.daterangepicker', function (ev, picker) {
    $(this).val(picker.startDate.format('DD-M-YYYY'));
    filterIssueDate(picker.startDate.format('DD-M-YYYY'))
});

function filterIssueDate(date) {
    // var date = document.getElementById('d_auto_71').value;
    filterIssue(date);
}


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

    var date = document.getElementById("d_auto_71").value;

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
            bindDataFilter(data);


        },
        complete: function () {
            $('#loading').hide();
        }
    });

}

function showIssue(data) {

    //make an ajax request

    showIssue(data);


}


function showIssue(data) {
    var issue_no = data;

    var ajaxurl = config.routes.stockIssueShowReprint;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            issue_no: issue_no
        },
        success: function (data) {
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
    $('#show').modal('show');

}

function bindDataFilter(data) {

    table_reprint_filter.clear();
    table_reprint_filter.rows.add(data);
    table_reprint_filter.draw();

}

function numberWithCommas(digit) {
    return String(parseFloat(digit)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

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
