var cart = [];//for data displayed.
var default_cart = [];//for default values.
var details = [];
var sale_items = [];
var tax = Number(document.getElementById("vat").value);


var items_table = $('#items_table').DataTable({
	searching: true,
	bPaginate: true,
	bInfo: true,
	data: sale_items,
	columns: [
		{ title: "ID" },
		{ title: "Product Name" },
		{ title: "Quantity" },
		{
			title: "Unit Price", render: function (Price) {
				return formatMoney(Price);
			}
		},
		{
			title: "VAT", render: function (vat) {
				return formatMoney(vat);
			}
		},
		{
			title: "Discount", render: function (discount) {
				return formatMoney(discount);
			}
		},
		{
			title: "Amount", render: function (amount) {
				return formatMoney(amount);
			}
		},
		{ title: "Action", defaultContent: "<input type='button' value='Return' id='rtn_btn' class='btn btn-primary btn-rounded btn-sm'/>" }
	]
});
var cart_table = $('#cart_table').DataTable({
	searching: false,
	bPaginate: false,
	bInfo: false,
	data: cart,
	columns: [
		{ title: "Product Name" },
		{ title: "Price" },
		{ title: "Quantity" },
		{ title: "VAT" },
		{ title: "Amount" },
		{ title: "Stock Qty" },
		{ title: "productID" },
		{ title: "Action", defaultContent: "<div><input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/></div>" }
	]
});
cart_table.columns([5, 6]).visible(false);//this columns are just for manipulations
var details_table = $('#details_table').DataTable({
	searching: true,
	bPaginate: false,
	bInfo: true,
	data: details,
	columns: [
		{ title: "Product Name" },
		{ title: "Quantity" },
		{ title: "price" },
		{ title: "VAT" },
		{ title: "Amount" }
	]
});

var credit_payment_table = $('#credit_payment_table').DataTable({
	searching: false,
	bPaginate: false,
	bInfo: false,
	columns: [
		{ data: 'receipt_number' },
		{ data: 'name' },
		{
			data: 'date', render: function (date) {
				return moment(date).format('MMM DD,YYYY');
			}
		},
		{
			data: 'total_amount', render: function (total_amount) {
				return formatMoney(total_amount);
			}
		},
		{
			data: 'paid_amount', render: function (paid_amount) {
				return formatMoney(paid_amount);
			}
		},
		{
			data: 'balance', render: function (balance) {
				return formatMoney(balance);
			}
		},
		{ data: "action", defaultContent: "<button type='button' id='pay_btn' class='btn btn-sm btn-rounded btn-primary'>Pay</button>" }
	]
});

var sale_history_table = $('#sale_history_table').DataTable({
	bPaginate: false,
	bInfo: false,
	dom: 't',
	columns: [
		{ data: 'receipt_number' },
		{
			data: 'date', render: function (date) {
				return moment(date).format('MMM DD,YYYY');
			}
		},
		{ data: 'cost.name' },
		{
			data: 'cost', render: function (cost) {
				return formatMoney(((cost.amount - cost.discount) / (1 + (cost.vat / cost.sub_total))));
			}
		},

		{
			data: 'cost', render: function (cost) {
				return formatMoney(((cost.amount - cost.discount) * (cost.vat / cost.sub_total)));
			}
		},
		{ data: 'cost.discount' },
		{
			data: 'cost', render: function (cost) {
				return formatMoney(((cost.amount - cost.discount)));
			}
		},
		{ data: "action", defaultContent: "<button type='button' id='sale_details' class='btn btn-sm btn-rounded btn-success'>Details</button>" }
	]
});
var sale_list_Table = $('#sale_list_Table').DataTable({
	order: [[1, "desc"]],
	dom: 't',
	bPaginate: false,
	bInfo: true,
	fixedHeader: true,
});

$('#searching').on('keyup', function () {
	sale_history_table.search(this.value).draw();
});
$("#products").on('change', function () {
	valueCollection();
});
$("#customer").on('change', function () {
	discount();
});
$('#cart_table tbody').on('click', '#edit_btn', function () {
	var row_data = cart_table.row($(this).parents('tr')).data();
	var index = cart_table.row($(this).parents('tr')).index();
	quantity = row_data[2];
	row_data[2] = "<input type='number' min='1' class='form-control' id='edit_quantity' required  onkeypress='return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57'>";
	cart[index] = row_data;
	cart_table.clear();
	cart_table.rows.add(cart);
	cart_table.draw();
	document.getElementById("edit_quantity").value = quantity;
});



$('#cart_table tbody').on('change', '#edit_quantity', function () {
	var row_data = cart_table.row($(this).parents('tr')).data();
	var index = cart_table.row($(this).parents('tr')).index();
	row_data[2] = Number((document.getElementById("edit_quantity").value));
	if (row_data[2] < 1) { row_data[2] = 1 }
	dif = row_data[5] - row_data[2];
	if ($('#quotes_page').length) {//Qoutes has no maximum quantity
		row_data[1] = formatMoney(parseFloat(default_cart[index][0].replace(/\,/g, ''), 10));
		row_data[3] = formatMoney(parseFloat(default_cart[index][1].replace(/\,/g, ''), 10) * row_data[2]);
		row_data[4] = formatMoney(parseFloat(default_cart[index][2].replace(/\,/g, ''), 10) * row_data[2]);
	}
	else if (dif <= 0) {
		row_data[2] = row_data[5] + " " + "<span class='text text-danger'>Max</span>";
		row_data[1] = formatMoney(parseFloat(default_cart[index][0].replace(/\,/g, ''), 10));
		row_data[3] = formatMoney(parseFloat(default_cart[index][1].replace(/\,/g, ''), 10) * row_data[5]);
		row_data[4] = formatMoney(parseFloat(default_cart[index][2].replace(/\,/g, ''), 10) * row_data[5]);
	}
	else {
		row_data[1] = formatMoney(parseFloat(default_cart[index][0].replace(/\,/g, ''), 10));
		row_data[3] = formatMoney(parseFloat(default_cart[index][1].replace(/\,/g, ''), 10) * row_data[2]);
		row_data[4] = formatMoney(parseFloat(default_cart[index][2].replace(/\,/g, ''), 10) * row_data[2]);
	}//replace the quantity with max stock qty available

	cart[index] = row_data;
	discount();
});

$('#cart_table tbody').on('click', '#delete_btn', function () {
	var index = cart_table.row($(this).parents('tr')).index();
	var price = parseFloat(cart[index][1].replace(/\,/g, ''), 10);
	var unit_total = parseFloat(cart[index][4].replace(/\,/g, ''), 10);
	cart.splice(index, 1);
	default_cart.splice(index, 1);
	discount();
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
	}
}

function discount() {
	var dis = (document.getElementById("sale_discount").value);
	sale_discount = (parseFloat(dis.replace(/\,/g, ''), 10) || 0);
	var sub_total, total_vat, total = 0;
	if (cart[0]) {
		var result = [];
		var order_cart = [];//for data sent into database.
		cart.reduce(function (reducedCart, value) {
			if (!reducedCart[value[6]]) {
				reducedCart[value[6]] = value;
				result.push(reducedCart[value[6]])
			} else {
				p = parseFloat(reducedCart[value[6]][1].replace(/\,/g, ''), 10);
				if (typeof reducedCart[value[6]][2] != 'number') {
					reducedCart[value[6]][2] = reducedCart[value[6]][5] //avoid Max string
				}
				reducedCart[value[6]][2] += value[2];
				dif = reducedCart[value[6]][5] - reducedCart[value[6]][2];
				if ($('#quotes_page').length) {//Qoutes has no maximum quantity
					reducedCart[value[6]][1] = formatMoney(p);
					reducedCart[value[6]][3] = formatMoney(p * reducedCart[value[6]][2] * tax);
					reducedCart[value[6]][4] = formatMoney(p * reducedCart[value[6]][2] * (1 + tax));
				}
				else if (dif <= 0) {
					reducedCart[value[6]][2] = reducedCart[value[6]][5] + " " + "<span class='text text-danger'>Max</span>";
					reducedCart[value[6]][1] = formatMoney(p);
					reducedCart[value[6]][3] = formatMoney(p * reducedCart[value[6]][5] * tax);
					reducedCart[value[6]][4] = formatMoney(p * reducedCart[value[6]][5] * (1 + tax));
				}
				else {
					reducedCart[value[6]][1] = formatMoney(p);
					reducedCart[value[6]][3] = formatMoney(p * reducedCart[value[6]][2] * tax);
					reducedCart[value[6]][4] = formatMoney(p * reducedCart[value[6]][2] * (1 + tax));
				}//replace the quantity with max qty on stock

			}
			return reducedCart;
		}, []);
		cart = result;
		var price_category = document.getElementById("price_category").value;
		$('#price_category').prop('disabled', true);
		document.getElementById('cat_label').style.color = 'red';
		cart.forEach(function (item, index, arr) {
			var bought_product = {};
			bought_product.price = parseFloat(item[1].replace(/\,/g, ''), 10);
			if (typeof item[2] != 'number') {
				bought_product.quantity = item[5]; //avoid Max string
			}
			else {
				bought_product.quantity = item[2];
			}
			bought_product.amount = parseFloat(item[4].replace(/\,/g, ''), 10);
			bought_product.product_id = item[6];
			sub_total += bought_product.price;
			total_vat += parseFloat(item[3].replace(/\,/g, ''), 10);
			total += bought_product.amount;
			order_cart.push(bought_product);
		});
		//SUBTOTAL WITH DISCOUNT
		total -= sale_discount;
		sub_total = total / (1 + tax);
		total_vat = total - sub_total;
	}
	else {
		$('#price_category').prop('disabled', false);
		document.getElementById('cat_label').style.color = 'black';
	}

	if (total < 0) {
		document.getElementById('save_btn').disabled = 'true';
		document.getElementById('discount_error').style.display = 'block';
	}
	else {
		document.getElementById('discount_error').style.display = 'none';
		$('#save_btn').prop('disabled', false);
	}

	//Change Calculator
	var change = 0;
	var paid = document.getElementById("sale_paid").value;
	sale_paid_amount = (parseFloat(paid.replace(/\,/g, ''), 10) || 0);
	change = sale_paid_amount - total;

	//Credit Sales
	if ($('#credit_sale').length) {
		customer = JSON.parse(document.getElementById("customer").value);
		max_credit = ((customer.credit_limit - customer.total_credit) || 0);
		balance = total - sale_paid_amount;
		if (balance > max_credit) {
			document.getElementById('save_btn').disabled = 'true';
			$('div.credit_max').text(formatMoney(max_credit)).css({ "font-weight": "Bold", "color": "red" });
		}
		else {
			$('#save_btn').prop('disabled', false);
			$('div.credit_max').text(formatMoney(max_credit)).css({ "font-weight": "Bold", "color": "green" });
		}
		document.getElementById("paid_value").value = sale_paid_amount;
		$('div.sub-total').text(formatMoney(sub_total)).css("font-weight", "Bold");
		$('div.tax-amount').text(formatMoney(total_vat)).css("font-weight", "Bold");
		$('div.total-amount').text(formatMoney(total)).css("font-weight", "Bold");
		$('div.balance-amount').text(formatMoney(balance)).css("font-weight", "Bold");
	}
	else {
		document.getElementById('change_amount').value = formatMoney(change);
	}



	stringified_cart = JSON.stringify(order_cart);
	document.getElementById("order_cart").value = stringified_cart;
	document.getElementById("price_cat").value = price_category;
	document.getElementById("discount_value").value = sale_discount;
	document.getElementById("total").value = formatMoney(total);
	document.getElementById("sub_total").value = formatMoney(sub_total);
	var t = document.getElementById("total").value;
	var st = document.getElementById("sub_total").value;

	document.getElementById("total_vat").value =
		formatMoney(parseFloat(t.replace(/\,/g, ''), 10) - parseFloat(st.replace(/\,/g, ''), 10));

	cart_table.clear();
	cart_table.rows.add(cart);
	cart_table.draw();
}




function valueCollection() {
	var item = [];
	var cart_data = [];
	var bought_product = {};
	product = document.getElementById("products").value;
	document.getElementById("products").value = "";
	if (product) {
		var selected_fields = product.split('#@');
		var item_name = selected_fields[0];
		var price = Number(selected_fields[1]);
		var productID = Number(selected_fields[2]);
		var qty = Number(selected_fields[3]);
		var vat = Number((price * tax).toFixed(2));
		var unit_total = Number(price + vat);
		var quantity = 1;
		item.push(item_name);
		item.push(formatMoney(price));
		item.push(quantity);
		item.push(formatMoney(vat));
		item.push(formatMoney(unit_total));
		item.push(qty);
		item.push(productID);
		cart_data.push(formatMoney(price));
		cart_data.push(formatMoney(vat));
		cart_data.push(formatMoney(unit_total));
		cart.push(item);
		default_cart.push(cart_data);
		discount();


	}

}


function deselect() {
	document.getElementById("sales_form").reset();

	sub_total = 0;
	total = 0;
	cart = [];
	order_cart = [];
	default_cart = [];
	discount();
}





function saleReturn(items, sale_id) {
	var return_cart = [];
	var id = sale_id;
	localStorage.setItem("id", id);
	document.getElementById('sales').style.display = 'none';
	sale_items = [];
	returned = " <h4><span class='badge badge-secondary'>Partial</span></h4>";
	pending = " <h4><span class='badge badge-warning'>Pending</span></h4>";
	rejected = " <h4><span class='badge badge-danger'>Rejected</span></h4>";
	items.forEach(function (item) {
		var item_data = [];
		if (item.status !== 3) {
			item_data.push(item.id);
			item_data.push(item.name);
			item_data.push(item.quantity);
			item_data.push(item.price);
			item_data.push(item.vat);
			item_data.push(item.discount);
			item_data.push(item.amount);
			if (item.status == 2) {
				item_data.push(pending)
			}
			if (item.status == 4) {
				item_data.push(rejected)
			}
			if (item.status == 5) {
				item_data.push(returned)
			}
			sale_items.push(item_data);
		}


	});
	items_table.clear();
	items_table.rows.add(sale_items);
	items_table.column(0).visible(false);
	items_table.draw();
	document.getElementById('items').style.display = 'block';

	$('#cancel').on('click', function () {
		return_cart = [];
		localStorage.removeItem("id");
		document.getElementById('sales').style.display = 'block';
		document.getElementById('items').style.display = 'none';
	});

}

function quoteDetails(remark, items) {
	$('div.quote_remark').text(remark);
	action = "<input type='button' value='Sale' id='sale_btn' class='btn btn-primary btn-rounded btn-sm'/>";
	sale_items = [];
	items.forEach(function (item) {
		var item_data = [];
		item_data.push(item.id);
		item_data.push(item.name);
		item_data.push(item.quantity);
		item_data.push(item.price / item.quantity);//unit price
		item_data.push(item.vat);
		item_data.push(item.discount);
		item_data.push(item.amount);
		item_data.push(action);//this is not used now (Just an Idea for quote to sale conversion)
		sale_items.push(item_data);
	});
	items_table.clear();
	items_table.rows.add(sale_items);
	items_table.column(7).visible(false);
	items_table.column(0).visible(false);
	items_table.draw();
}

$('#sale_quotes-Table tbody').on('click', '#quote_details', function () {
	$('#quote-details').modal('show');
});

$('#sale_history_table tbody').on('click', '#sale_details', function () {
	var row_data = sale_history_table.row($(this).parents('tr')).data();
	$('#sale-details').modal('show');
	var items = row_data.details;
	sold = " <h4><span class='badge badge-success'>Bought</span></h4>";
	pending = " <h4><span class='badge badge-warning'>Pending</span></h4>";
	returned = " <h4><span class='badge badge-danger'>Returned</span></h4>";
	sale_items = [];
	items.forEach(function (item) {
		var item_data = [];
		item_data.push(item.id);
		item_data.push(item.name);
		item_data.push(item.quantity);
		item_data.push((item.price / item.quantity));
		item_data.push(item.vat);
		item_data.push(item.discount);
		item_data.push(item.amount);
		if (item.status == 2) {
			item_data.push(pending)
		}
		else if (item.status == 3) {
			item_data.push(returned)
		}
		else {
			item_data.push(sold)
		}
		sale_items.push(item_data);
	});
	items_table.clear();
	items_table.rows.add(sale_items);
	items_table.columns([0]).visible(false);
	items_table.draw();
});





$('#items_table tbody').on('click', '#rtn_btn', function () {
	var index = items_table.row($(this).parents('tr')).index();
	var data = items_table.row($(this).parents('tr')).data();
	$('#sale-return').modal('show');
	$('#sale-return').find('.modal-body #id_of_item').val(data[0]);
	$('#sale-return').find('.modal-body #og_item_qty').val(data[2]);
	$('#sale-return').find('.modal-body #name_of_item').val(data[1]);
	document.getElementById('save_btn').style.display = 'block';
	$('#sale-return').on('change', '#rtn_qty', function () {
		var quantity = document.getElementById('rtn_qty').value;
		if (quantity > data[2] || quantity < 0) {
			document.getElementById('save_btn').disabled = 'true';
			document.getElementById('qty_error').style.display = 'block';
			$('#sale-return').find('.modal-body #qty_error').text('Must be greated than 0 and less than ' + data[2]);
		}
		else {
			document.getElementById('qty_error').style.display = 'none';
			$('#save_btn').prop('disabled', false);
		}
	});
});

$("#sale_discount").on('change', function (evt) {
	if (evt.which != 110) {//not a fullstop
		var n = Math.abs((parseFloat($(this).val().replace(/\,/g, ''), 10) || 0));
		$(this).val(n.toLocaleString("en", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}));
	}
});

$("#paying").on('change', function (evt) {
	if (evt.which != 110) {//not a fullstop
		var n = Math.abs((parseFloat($(this).val().replace(/\,/g, ''), 10) || 0));
		$(this).val(n.toLocaleString("en", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}));
	}
	var paid = (document.getElementById("paying").value);
	paid_amount = (parseFloat(paid.replace(/\,/g, ''), 10) || 0);
	$('#credit-sale-payment').find('.modal-body #paid-amount').val(paid_amount);
});

$("#sale_paid").on('change', function (evt) {
	if (evt.which != 110) {//not a fullstop
		var n = Math.abs((parseFloat($(this).val().replace(/\,/g, ''), 10) || 0));
		$(this).val(n.toLocaleString("en", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}));
	}
});



$('#price_category').change(function () {
	$("#products option").remove();
	var id = $(this).val();
	if (id) {
		$.ajax({
			url: config.routes.selectProducts,
			data: {
				"_token": config.token,
				"id": id
			},
			type: 'post',
			dataType: 'json',
			success: function (result) {
				$.each(result, function (detail, name) {
					$('#products').append($('<option>', { value: detail, text: name }));
				});
			},
		});
	}

});


$("#save-customer").click(function () {
	document.getElementById('new-task').value = 'New Customer';
});
$("#cancel-customer").click(function () {
	document.getElementById('new-task').value = 'Existing Customer';
	document.getElementById('new-customer').style.display = 'none';
	document.getElementById('sale-panel').style.display = 'block';
	document.getElementById('add-customer').style.display = 'block';
});
$("#add-customer").click(function () {
	document.getElementById('new-customer').style.display = 'block';
	document.getElementById('sale-panel').style.display = 'none';
	document.getElementById('add-customer').style.display = 'none';
});


if ($('#can_pay').length) {
	credit_payment_table.column(6).visible(true);
}
else {
	credit_payment_table.column(6).visible(false);

}



function getCredits() {
	if ($('#track').length) {
		var status = document.getElementById('payment-status').value;
		var dates = document.querySelector('input[name=date_of_sale]').value;
		dates = dates.split('-');
	}
	var id = document.getElementById('customer_payment').value;
	if (id || status || dates) {
		$.ajax({
			url: config.routes.getCreditSale,
			data: {
				"_token": config.token,
				"id": id,
				'date': dates
			},
			type: 'get',
			dataType: 'json',
			success: function (data) {
				//Remove Pay Button for Balance < 1
				data.forEach(function (data) {
					if (data.balance < 1) {
						data.action = " <h4><span class='badge badge-success'>Paid</span></h4>";
					}
				});
				if (status == 'all') {
					data = data;
				}
				else if (status == 'full_paid') {
					data = data.filter(function (el) { return el.balance < 1; });
				}
				else if (status == 'not_paid') {

					data = data.filter(function (el) {
						return el.balance == el.total_amount;
					});
				}
				else if (status == 'partial_paid') {

					data = data.filter(function (el) { return (el.paid_amount > 0 && el.balance > 0); });
				}
				else {
					data = data.filter(function (el) {
						return el.balance > 0;
					});
				}


				if (id) {
					credit_payment_table.column(1).visible(false);
				}
				else {
					credit_payment_table.column(1).visible(true);
				}




				credit_payment_table.clear();
				credit_payment_table.rows.add(data);
				credit_payment_table.draw();

			},
			complete: function () {
			}
		});
	}

}

function getHistory() {
	var range = document.getElementById('daterange').value;
	range = range.split('-');
	if (range) {
		$.ajax({
			url: config.routes.getSalesHistory,
			data: {
				"_token": config.token,
				"range": range
			},
			type: 'get',
			dataType: 'json',
			success: function (data) {
				sale_history_table.clear();
				sale_history_table.rows.add(data);
				sale_history_table.draw();

			},
			complete: function () {
				// $('#loading').hide();
			}
		});
	}

}

$('#daterange').change(function () {
	getHistory();

});

$('#customer_payment').change(function () {
	getCredits();

});

$('#payment-status').change(function () {
	getCredits();
});
$('#sales_date').change(function () {
	getCredits();
});


$('#credit_payment_table tbody').on('click', '#pay_btn', function () {
	var index = credit_payment_table.row($(this).parents('tr')).index();
	var data = credit_payment_table.row($(this).parents('tr')).data();
	$('#credit-sale-payment').modal('show');
	$('#credit-sale-payment').find('.modal-body #id_of_sale').val(data.sale_id);
	$('#credit-sale-payment').find('.modal-body #customer-id').val(data.customer_id);
	$('#credit-sale-payment').find('.modal-body #receipt-number').val(data.receipt_number);
	$('#credit-sale-payment').find('.modal-body #balance-amount').val(data.balance);
	$('#credit-sale-payment').find('.modal-body #outstanding').val(formatMoney(data.balance));
	document.getElementById('save_btn').style.display = 'block';
	$('#credit-sale-payment').on('change', '#rtn_qty', function () {
		var quantity = document.getElementById('rtn_qty').value;
		if (quantity > data[2]) {
			document.getElementById('save_btn').disabled = 'true';
			document.getElementById('qty_error').style.display = 'block';
			$('#credit-sale-payment').find('.modal-body #qty_error').text('The maximum quantity is ' + data[2]);
		}
		else {
			document.getElementById('qty_error').style.display = 'none';
			$('#save_btn').prop('disabled', false);
		}
	});
});

