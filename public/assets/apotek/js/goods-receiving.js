var cart = [];
var default_cart = [];
var received_cart = [];
var details = [];
var order_items = [];
var product_ids = 0;
var edit_btn_set = 0;

var items_table = $('#items_table').DataTable({
    searching: true,
    bPaginate: false,
    bInfo: true,
    data: order_items,
    columns: [
        {title: "ProductID"},
        {title: "Product Name"},
        {title: "Price"},
        {title: "Quantity"},
        {title: "VAT"},
        {title: "Discount"},
        {title: "Amount"},
        {title: "Supplier"},
        {title: "OrderItemId"},
        {
            title: "Action",
            defaultContent: "<input type='button' value='Receive' id='receive_btn' class='btn btn-warning btn-rounded btn-sm' size='2' />"
        }
    ]
});

var cart_table = $('#cart_table').DataTable({
    searching: false,
    bPaginate: false,
    bInfo: false,
    data: cart,
    columns: [
        {title: "Item Name"},
        {title: "Quantity"},
        {
            title: "Action",
            defaultContent: "<div><input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/></div>"
        }
    ]
});

$('#cart_table tbody').on('click', '#edit_btn', function () {
    if (edit_btn_set === 0){
        var row_data = cart_table.row($(this).parents('tr')).data();
        var index = cart_table.row($(this).parents('tr')).index();
        quantity = row_data[1];
        row_data[1] = "<input style='width: 45%' type='number'class='form-control' id='edit_quantity' required/>";
        cart[index] = row_data;
        cart_table.clear();
        cart_table.rows.add(cart);
        cart_table.draw();
        document.getElementById("edit_quantity").value = quantity;

        edit_btn_set = 1;

    }

});


$('#cart_table tbody').on('change', '#edit_quantity', function () {
    edit_btn_set = 0;
    var row_data = cart_table.row($(this).parents('tr')).data();
    var index = cart_table.row($(this).parents('tr')).index();
    row_data[1] = document.getElementById("edit_quantity").value;
    valuesCollection(row_data[1]);
    cart[index] = row_data;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
});

$('#cart_table tbody').on('click', '#delete_btn', function () {
    edit_btn_set = 0;
    var index = cart_table.row($(this).parents('tr')).index();
    // valuesCollection();
    cart.splice(index, 1);
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    document.getElementById("received_cart").value = JSON.stringify(cart);
    document.getElementById('buy_price').value = '0';
    document.getElementById('sell_price_id').value = '0';


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

function isNumberKey(evt, obj) {

    var charCode = (evt.which) ? evt.which : event.keyCode;
    var value = obj.value;
    var dotcontains = value.indexOf(".") !== -1;
    if (dotcontains)
        if (charCode === 46) return false;
    if (charCode === 46) return true;
    if (charCode > 31 && (charCode < 48 || charCode > 57))
        return false;
    return true;
}

$('#selected-product').on('change', function () {
    valuesCollection();
});

function valuesCollection(qty) {
    qty = (qty || 1);
    var item = [];
    var cart_data = [];
    var item_received = {};
    product = document.getElementById("selected-product").value;
    var selected_fields = product.split('#@');
    var item_name = selected_fields[0];
    var product_id = selected_fields[1];
    /*set the global variable*/
    product_ids = product_id;
    item_received.name = selected_fields[0];
    item_received.id = selected_fields[1];
    document.getElementById("pr_id").value = formatMoney(selected_fields[2]);

    var price_category = document.getElementById("price_category").value;
    var e = document.getElementById("supplier_ids");
    var supplier_id = e.options[e.selectedIndex].value;

    getPrice(product_id, price_category, supplier_id);
    item_received.quantity = qty;
    item.push(item_name);
    item.push(qty);
    cart[0] = item;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    item_received = JSON.stringify(item_received);
    document.getElementById("received_cart").value = item_received;

}

$('#cancel-all').on('click', function () {
    deselect();
});

function getPrice(product_id, price_category, supplier_id) {

    $.ajax({
        url: config.routes.goodsreceiving,
        type: "get",
        dataType: "json",
        data: {
            'product_id': product_id,
            'price_category': price_category,
            'supplier_id': supplier_id
        },
        success: function (data) {
            // console.log(data)
            if (data.length === 0) {
                /*empty*/
                $("#sell_price_id").val(formatMoney('0'));
                $("#buy_price").val(formatMoney('0'));
            } else {
                $("#buy_price").val(formatMoney(data[0]['unit_cost']));
                $("#sell_price_id").val(formatMoney(data[0]['price']));
            }
        }

    });
}

function deselect() {
    edit_btn_set = 0;
    cart = [];
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    document.getElementById("received_cart").value = JSON.stringify(cart);

}

function orderReceive(items, supplier) {
    received = " <h4><span class='badge badge-success'>Received</span></h4>";
    var receive_cart = [];
    document.getElementById('purchases').style.display = 'none';
    order_items = [];
    items.forEach(function (item) {
        var item_data = [];
        item_data.push(item.product_id);
        item_data.push(item.name);
        item_data.push(item.price);
        item_data.push(item.quantity);
        item_data.push(item.vat);
        item_data.push(item.discount);
        item_data.push(item.amount);
        if (supplier) {
            item_data.push(supplier);
            item_data.push(item.order_item_id);
            if (item.item_status == "Received") {
                item_data.push(received);
            }
            items_table.column(9).visible(true);
        } else {
            item_data.push('Preview');
            item_data.push(item.order_item_id);
            items_table.column(9).visible(false);
        }

        order_items.push(item_data);
    });

    items_table.clear();
    items_table.rows.add(order_items);
    items_table.columns([0, 7, 8]).visible(false);
    items_table.draw();
    document.getElementById('items').style.display = 'block';

    $('#cancel').on('click', function () {
        receive_cart = [];
        document.getElementById('purchases').style.display = 'block';
        document.getElementById('items').style.display = 'none';
    });

}

$('#items_table tbody').on('click', '#receive_btn', function () {
    var index = items_table.row($(this).parents('tr')).index();
    var data = items_table.row($(this).parents('tr')).data();
    $('#receive').modal('show');
    $('#receive').find('.modal-body #rec_qty').val(data[3]);
    $('#receive').find('.modal-body #name_of_item').val(data[1]);
    $('#receive').find('.modal-body #pr_id').val(data[2]);
    $('#receive').find('.modal-body #product-id').val(data[0]);
    $('#receive').find('.modal-body #order-item-id').val(data[8]);
    $('#receive').find('.modal-body #id_of_supplier').val(data[7]);
    var supplier_ids = data[7];
    var item_id = data[0];
    var e = document.getElementById("price_cat");
    var price_cat = e.options[e.selectedIndex].value;

    document.getElementById('save_btn').style.display = 'block';

//     function getPrice2(item_id, price_cat,supplier_ids) {
//     $.ajax({
//         url: configurations.routes.goodsreceiving,
//         type: "get",
//         dataType: "json",
//         data: {
//             'item_id': item_id,
//             'price_cat': price_cat,
//             'supplier_ids':supplier_ids
//         },

//         // success: function (data) {
//         //     if (data.length === 0) {
//         //         /*empty*/
//         //         $("#sell_price_i").val(formatMoney('0'));
//         //     } else {
//         //         $("#pr_id").val(formatMoney(data[0]['unit_cost']));
//         //         $("#sell_price_i").val(formatMoney(data[0]['price']));
//         //     }
//         // }

//     });
// }

    $('#receive').on('change', '#rec_qty', function () {
        var quantity = document.getElementById('rec_qty').value;
        if (quantity > data[3]) {
            document.getElementById('save_btn').disabled = 'true';
            document.getElementById('qty_error').style.display = 'block';
            $('#receive').find('.modal-body #qty_error').text('The maximum quantity is ' + data[3]);
        } else {
            document.getElementById('qty_error').style.display = 'none';
            $('#save_btn').prop('disabled', false);
        }
    });

});

function orderamountCheck() {

    var unit_price = document.getElementById('pr_id').value;
    var sell_price = document.getElementById('sell_price_i').value;
    // document.getElementById('sell_price_i').value = formatMoney(sell_price);
    // document.getElementById('pr_id').value = formatMoney(unit_price);
    unit_price_parse = (parseFloat(unit_price.replace(/\,/g, ''), 10));
    sell_price_parse = (parseFloat(sell_price.replace(/\,/g, ''), 10));

    console.log(sell_price_parse);
    console.log(unit_price_parse);

    if (Number(sell_price_parse) < Number(unit_price_parse)) {

        $('#save_btn').prop('disabled', true);
        $('div.amount_error').text('Cannot be less than Buy Price');
    } else if (sell_price_parse == unit_price_parse) {
        $('#save_btn').prop('disabled', true);
        $('div.amount_error').text('Cannot be equal to Buy Price');
    } else {

        $('#save_btn').prop('disabled', false);
        $('div.amount_error').text('');

    }
}

function priceByCategory() {

    if (product_ids !== 0) {
        var price_category = document.getElementById("price_category").value;
        getPrice(product_ids, price_category);

    } else {
        return false;
    }

}

$('#invoice_id').prop('disabled', true);

function filterInvoiceBySupplier() {
    var supplier = document.getElementById('supplier_ids');
    var supplier_id = supplier.options[supplier.selectedIndex].value;

    /*ajax filter invoice by supplier*/
    $.ajax({
        url: config.routes.filterBySupplier,
        type: "get",
        dataType: "json",
        data: {
            supplier_id: supplier_id
        },
        success: function (data) {
            $('#invoice_id').prop('disabled', false);
            $("#invoice_id option").remove();
            $('#invoice_id').append($('<option>', {
                value: '',
                text: 'Select Invoice...',
                disabled: true,
                selected: true
            }));
            $.each(data, function (id, detail) {

                var datas = [detail.invoice_no];

                $('#invoice_id').append($('<option>', {value: datas, text: detail.invoice_no}));
            });
        },
        complete: function () {
            // $('#loading').hide();
        }
    });

}

$('#myFormId').on('submit', function (e) {
    e.preventDefault();

    var item_cart = JSON.parse(document.getElementById("received_cart").value);

    if (item_cart.length === 0) {
        notify('Item receive list is empty', 'top', 'right', 'warning')
        return false;
    }

    saveInvoiceForm();

});

function saveInvoiceForm() {
    var form = $('#myFormId').serialize();

    $.ajax({
        url: config.routes.itemFormSave,
        type: "post",
        dataType: "json",
        data: form,
        success: function (data) {
            if (data[0].message === 'success') {
                notify('Product added successfully', 'top', 'right', 'success');
                document.getElementById('buy_price').value = '0';
                document.getElementById('sell_price_id').value = '0';
                deselect();
            } else {
                notify('Product name exists', 'top', 'right', 'danger');
                document.getElementById('buy_price').value = '0';
                document.getElementById('sell_price_id').value = '0';
                deselect();
            }
        }
    });
}
