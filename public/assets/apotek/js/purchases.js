var cart = [];
var default_cart = [];
var order_cart = [];
var purchase_items = [];
var set_button = 0;
var tax = Number(document.getElementById("vats").value);

var cart_table = $('#cart_table').DataTable({
    searching: false,
    bPaginate: false,
    bInfo: false,
    data: cart,
    columns: [
        {title: "Product Name"},
        {title: "Price"},
        {title: "Quantity"},
        {title: "VAT"},
        {title: "Amount"},
        {title: "Stock Id"},
        {title: "Product Id"},
        {
            title: "Action",
            defaultContent: "<div><input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/></div>"
        }
    ], "columnDefs": [
        {
            "targets": [5],
            "visible": false,
            "searchable": false
        }, {
            "targets": [6],
            "visible": false,
            "searchable": false
        }
    ]

});

$('#cart_table tbody').on('click', '#edit_btn', function () {
    if (set_button === 0) {
        var row_data = cart_table.row($(this).parents('tr')).data();
        var index = cart_table.row($(this).parents('tr')).index();
        quantity = row_data[2];
        price = row_data[1];
        row_data[2] = "<input type='number' style='width: 80%' class='form-control' id='edit_quantity' value='1' required/>";
        row_data[1] = "<input type='text' onkeypress='return isNumberKey(event,this)' style='width: 110%' class='form-control' id='edit_price' required/>";

        cart[index] = row_data;
        cart_table.clear();
        cart_table.rows.add(cart);
        cart_table.draw();
        price_value = parseFloat(price.replace(/\,/g, ''), 10);
        if (isNaN(price_value)) {

            price_value = 0;
        }


        document.getElementById("edit_quantity").value = quantity;
        document.getElementById("edit_price").value = formatMoney(price_value);
        set_button = 1;

    }
});


$('#cart_table tbody').on('change', '#edit_quantity', function () {
    set_button = 0;
    var row_data = cart_table.row($(this).parents('tr')).data();
    var index = cart_table.row($(this).parents('tr')).index();
    row_data[2] = document.getElementById("edit_quantity").value;
    // row_data[1] = parseFloat(document.getElementById("edit_price").value.replace(/\,/g, ''), 10);
    row_data[1] = document.getElementById("edit_price").value;
    // default_cart[index][1] = Number(row_data[1]);
    quantity = Number(row_data[2]);

    // row_data[1] = formatMoney(row_data[1]);
    // row_data[3] = formatMoney(default_cart[index][1] * quantity * tax);
    // row_data[4] = formatMoney(default_cart[index][1] * quantity * (1 + tax));
    cart[index] = row_data;
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
});


$('#cart_table tbody').on('change', '#edit_price', function () {
    set_button = 0;
    var row_data = cart_table.row($(this).parents('tr')).data();
    var index = cart_table.row($(this).parents('tr')).index();
    row_data[2] = document.getElementById("edit_quantity").value;
    row_data[1] = document.getElementById("edit_price").value;
    default_cart[index][1] = parseFloat(row_data[1].replace(/\,/g, ''), 10);
    row_data[1] = default_cart[index][1];

    row_data[3] = formatMoney(default_cart[index][1] * row_data[2] * tax);
    row_data[4] = formatMoney(default_cart[index][1] * row_data[2] * (1 + tax));
    row_data[1] = formatMoney(row_data[1]);

    cart[index] = row_data;

    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();

});


$('#cart_table tbody').on('click', '#delete_btn', function () {
    set_button = 0;
    var index = cart_table.row($(this).parents('tr')).index();
    cart.splice(index, 1);
    default_cart.splice(index, 1);
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
});

$('#deselect-all').on('click', function () {
    deselect();
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

function discount() {
    sale_dicount = document.getElementById("purchase_discount").value;
    var sub_total, total_vat, total = 0;
    var purchase_order_cart = [];
    var stringified_cart;

    if (cart[0]) {

        var reduced__obj_cart = {}, incremental_cart;

        for (var i = 0, c; c = cart[i]; ++i) {
            if (undefined === reduced__obj_cart[c[0]]) {
                reduced__obj_cart[c[0]] = c;
                reduced__obj_cart[c[0]][4] = formatMoney(Number(reduced__obj_cart[c[0]][2]
                    * parseFloat(reduced__obj_cart[c[0]][1].replace(/\,/g, ''), 10)) * (1 + tax));
                reduced__obj_cart[c[0]][3] = formatMoney(Number(reduced__obj_cart[c[0]][2]
                    * parseFloat(reduced__obj_cart[c[0]][1].replace(/\,/g, ''), 10) * tax));
            } else {

                reduced__obj_cart[c[0]][2] = Number(reduced__obj_cart[c[0]][2]) + Number(c[2]);
                reduced__obj_cart[c[0]][4] = formatMoney(Number(reduced__obj_cart[c[0]][2])
                    * parseFloat(reduced__obj_cart[c[0]][1].replace(/\,/g, ''), 10) * (1 + tax));
                reduced__obj_cart[c[0]][3] = formatMoney(Number(reduced__obj_cart[c[0]][2])
                    * parseFloat(reduced__obj_cart[c[0]][1].replace(/\,/g, ''), 10) * tax);

            }

        }

        incremental_cart = Object.keys(reduced__obj_cart).map(function (val) {
            return reduced__obj_cart[val]
        });

        cart = incremental_cart;

        cart.forEach(function (item, index, arr) {

            var purchase_product = {};
            sub_total += parseFloat(item[1].replace(/\,/g, ''), 10);
            total_vat += parseFloat(item[3].replace(/\,/g, ''), 10);
            total += parseFloat(item[4].replace(/\,/g, ''), 10);

            purchase_product.quantity = item[2];
            purchase_product.stock_id = item[5];
            purchase_product.product_id = item[6];
            purchase_product.item_name = item[0];
            purchase_product.price = item[1];
            purchase_product.vat = item[3];
            purchase_product.amount = item[4];
            purchase_order_cart.push(purchase_product);

        });

        total = total - sale_dicount;
        sub_total = total / (1 + tax);
        total_vat = total - sub_total;

        stringified_cart = JSON.stringify(purchase_order_cart);
    }
    document.getElementById("order_cart").value = stringified_cart;
    document.getElementById("id_vat").value = formatMoney(total_vat);
    document.getElementById("purchase_discount").value = sale_dicount;
    document.getElementById("total_price").value = total;
    document.getElementById("vat").value = formatMoney(total_vat);
    document.getElementById("sub_total_price").value = formatMoney(sub_total);
    document.getElementById("total").value = formatMoney(total);
    document.getElementById("sub_total").value = formatMoney(sub_total);

    // document.getElementById("paid_value").value = sale_paid_amount;
    $('div.sub-total').text(formatMoney(sub_total)).css("font-weight", "Bold");
    $('div.tax-amount').text(formatMoney(total_vat)).css("font-weight", "Bold");
    $('div.total-amount').text(formatMoney(total)).css("font-weight", "Bold");


    var carts = document.getElementById("order_cart").value;
    if (carts === '' || carts === 'undefined') {
        $('#supplier').prop('disabled', false);
        $('#select_id').prop('disabled', false);
    }

}

$('#select_id').on('change', function () {
    val();
});

function val() {
    /*supplier option*/
    $('#supplier').prop('disabled', true);

    /*set values to table*/
    var item = [];
    var cart_data = [];
    product = document.getElementById("select_id").value;
    document.getElementById("select_id").value = "";
    var selected_fields = product.split(',');
    var item_name = selected_fields[0];
    var price = Number(selected_fields[1]);
    var vat = Number((price * tax).toFixed(2));
    var unit_total = Number(price + vat);
    var quantity = 1;
    item.push(item_name);
    item.push(formatMoney(price));
    item.push(quantity);
    item.push(formatMoney(vat));
    item.push(formatMoney(unit_total));
    item.push(selected_fields[3]);
    item.push(selected_fields[2]);
    cart_data.push(formatMoney(price));
    cart_data.push(formatMoney(vat));
    cart_data.push(quantity);
    cart_data.push(formatMoney(unit_total));
    default_cart.push(cart_data);
    cart.push(item);
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();

}

$('cancel-all').on('click', function () {
    set_button = 0;
    deselect();
});

function deselect() {
    $('#supplier').prop('disabled', false);
    $('#select_id').prop('disabled', false);
    sub_total = 0;
    total = 0;
    total_vat = 0;
    discount();
    cart = [];
    document.getElementById("vat").value = formatMoney(total_vat);
    document.getElementById("total").value = formatMoney(total);
    document.getElementById("sub_total").value = formatMoney(sub_total);
    document.getElementById("order_cart").value = cart;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
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

$('#order_form').on('submit', function () {
    var cart = document.getElementById("order_cart").value;

    if (cart === '' || cart === 'undefined') {
        notify('Purchase order list empty', 'top', 'right', 'warning');
        return false;
    }

    var check_cart_to_array = JSON.parse(cart);

    var price = "price";

    for (var key in check_cart_to_array) {

        if (check_cart_to_array[key].hasOwnProperty(price)) {
            //present
            if (parseFloat(check_cart_to_array[key][price]) === Number(0)) {
                notify(check_cart_to_array[key].item_name + ' price cannot be 0 ', 'top', 'right', 'warning');
                // $('#from_id').prop('disabled', true);
                return false;
            }
        }

    }

});

$('#select_id').prop('disabled', true);

function filterSupplierProduct() {
    /*ajax filter products by supplier*/
    var supplier = document.getElementById('supplier');
    var supplier_id = supplier.options[supplier.selectedIndex].value;
    document.getElementById('supplier_ids').value = supplier_id;

    $.ajax({
        url: config.routes.filterSupplierProduct,
        type: "get",
        dataType: "json",
        data: {
            supplier_id: supplier_id
        },
        success: function (data) {
            $('#select_id').prop('disabled', false);
            $("#select_id option").remove();
            $('#select_id').append($('<option>', {
                value: '',
                text: 'Select Product...',
                disabled: true,
                selected: true
            }));
            $.each(data, function (id, detail) {

                var datas = [detail.name, detail.unit_cost, detail.product_id, detail.incoming_id];

                $('#select_id').append($('<option>', {value: datas, text: detail.name}));
            });
        },
        complete: function () {
            // $('#loading').hide();
        }
    });
}

$('#select_id').select2({
    language: {
        noResults: function () {
            var search_input = $("#select_id").data('select2').$dropdown.find("input").val();
            var supplier = document.getElementById('supplier');
            var supplier_id = supplier.options[supplier.selectedIndex].value;

            /*make ajax call for more*/
            $.ajax({
                url: config.routes.filterSupplierProductInput,
                type: "get",
                dataType: "json",
                data: {
                    word: search_input,
                    supplier_id: supplier_id
                },
                success: function (data) {
                    $('#select_id').prop('disabled', false);
                    $('#supplier').prop('disabled', true);
                    $("#select_id option").remove();
                    $('#select_id').append($('<option>', {
                        value: '',
                        text: 'Select Product...',
                        disabled: true,
                        selected: true
                    }));
                    $.each(data, function (id, detail) {

                        var datas = [detail.name, detail.unit_cost, detail.product_id, detail.incoming_id];

                        $('#select_id').append($('<option>', {value: datas, text: detail.name}));
                    });
                }
            });
        }
    }
});
