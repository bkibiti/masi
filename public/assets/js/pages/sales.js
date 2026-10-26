    var cart=[];
    var default_cart=[];
    var order_cart=[];  

    var cart_table= $('#cart_table').DataTable({
        searching: false,
        bPaginate: false,
        bInfo: false,
        data: cart,
        columns: [
            { title: "Item Name" },
            { title: "Price" },
            { title: "Quantity"},
            { title: "VAT"},
            { title: "Amount"},
            { title: "Action",defaultContent: "<div><input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/></div>"}
        ]
    } );

    function discount(){
      sale_dicount = document.getElementById("sale_discount").value; 
      var sub_total,total_vat,total = 0; 
      if (cart[0]){
        cart.forEach(function(item, index, arr){
        sub_total += parseFloat(item[1].replace(/\,/g,''), 10);
        total_vat += parseFloat(item[3].replace(/\,/g,''), 10); 
        total += parseFloat(item[4].replace(/\,/g,''), 10);
        })
        total=total-sale_dicount;
        sub_total=total/1.18;
        total_vat= total-sub_total;  
      } 
        document.getElementById("id_vat").value = total_vat;
        document.getElementById("sale_discount").value = sale_dicount;
        document.getElementById("total_price").value =total;
        document.getElementById("sub_total_price").value = sub_total;
        document.getElementById("total").value = formatMoney(total);
        document.getElementById("sub_total").value =  formatMoney(sub_total);
    }
   

    function val() {
    var item=[];
    var cart_data=[];
    var sale_product={};
    product = document.getElementById("select_id").value;
    var selected_fields = product.split(',');
    console.log(selected_fields);
    var item_name=selected_fields[0];
    var price=Number(selected_fields[1]);
    var vat = Number((price*0.18).toFixed(2));
    var unit_total= Number(price + vat);
    var quantity=1;
    item.push(item_name);
    item.push(formatMoney(price));
    item.push(quantity);
    item.push(formatMoney(vat));
    item.push(formatMoney(unit_total));
    cart_data.push(formatMoney(price));
    cart_data.push(formatMoney(vat));
    cart_data.push(formatMoney(unit_total));
    cart.push(item);
    default_cart.push(cart_data);
    sale_product.quantity = quantity;
    sale_product.stock_id = selected_fields[3];
    sale_product.item_name = selected_fields[0];
    sale_product.price =price;
    sale_product.vat = vat;
    sale_product.amount = unit_total;
    order_cart.push(sale_product);
    stringified_cart=JSON.stringify( order_cart );
    document.getElementById("order_cart").value =  stringified_cart;
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();

    }

     
    function deselect(){
    sub_total=0;
    total=0;
    discount();
    cart=[];
    order_cart=[]; 
    stringified_cart=JSON.stringify( order_cart );
    document.getElementById("total").value = formatMoney(total);
    document.getElementById("sub_total").value =  formatMoney(sub_total);
    document.getElementById("order_cart").value =  stringified_cart;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    } 

    $('#cart_table tbody').on( 'click', '#edit_btn', function () {
    var row_data =  cart_table.row( $(this).parents('tr') ).data();
    var index =  cart_table.row( $(this).parents('tr') ).index();
    quantity=row_data[2];
    row_data[2]="<input type='number'class='form-control' id='edit_quantity' required/>";
    cart[index] = row_data;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    document.getElementById("edit_quantity").value=quantity;
    } );



    $('#cart_table tbody').on( 'change', '#edit_quantity', function () {
    var row_data =  cart_table.row( $(this).parents('tr') ).data();
    var index =  cart_table.row( $(this).parents('tr') ).index();
    row_data[2]= document.getElementById("edit_quantity").value;
    row_data[1]=formatMoney(parseFloat(default_cart[index][0].replace(/\,/g,''), 10)*row_data[2]);
    row_data[3]=formatMoney(parseFloat(default_cart[index][1].replace(/\,/g,''), 10)*row_data[2]);
    row_data[4]=formatMoney(parseFloat(default_cart[index][2].replace(/\,/g,''), 10)*row_data[2]);
    order_cart[index].quantity = row_data[2];
    order_cart[index].price = parseFloat(default_cart[index][0].replace(/\,/g,''), 10)*row_data[2];
    order_cart[index].vat = parseFloat(default_cart[index][1].replace(/\,/g,''), 10)*row_data[2];
    order_cart[index].amount = parseFloat(default_cart[index][2].replace(/\,/g,''), 10)*row_data[2];
    stringified_cart=JSON.stringify( order_cart );
    cart[index] = row_data;
    document.getElementById("order_cart").value =  stringified_cart;
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    } );

    $('#cart_table tbody').on( 'click', '#delete_btn', function () {
    var index =  cart_table.row( $(this).parents('tr') ).index();
    var price = parseFloat(cart[index][1].replace(/\,/g,''), 10);
    var unit_total = parseFloat(cart[index][4].replace(/\,/g,''), 10);
    order_cart.splice(index, 1); 
    stringified_cart=JSON.stringify( order_cart );
    document.getElementById("order_cart").value =  stringified_cart;
    cart.splice(index, 1); 
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    } );

    $('#deselect-all').on('click', function() {
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
    };

    $('#view-quote-details').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget)
      var modal = $(this)
      var items = button.data('item');
      var details = [];
      items.forEach(function(item){
        var item_data=[];
        item_data.push(item.item_name);
        item_data.push(item.quantity);
        item_data.push(item.price);
        item_data.push(item.vat);
        item_data.push(item.amount);
        details.push(item_data);
      })
       showQuoteDetails(details);
    }); 

    $('#view-sale-details').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget)
      var modal = $(this)
      var items = button.data('item');
      var details = [];
      items.forEach(function(item){
        var item_data=[];
        item_data.push(item.item_name);
        item_data.push(item.quantity);
        item_data.push(item.price);
        item_data.push(item.vat);
        item_data.push(item.amount);
        details.push(item_data);
      })
       showSaleDetails(details);
     });   


     function showQuoteDetails(details){
          var details_table = $('#details_table').DataTable({
            searching: true,
            bPaginate:false,
            bInfo: true,
            data: details,
            columns: [
                { title: "Item Name" },
                { title: "Quantity" },
                { title: "price" },
                { title: "VAT" },
                { title: "Amount" }
            ]
        } ); 
        details_table.destroy();
        details_table.clear();
        details_table.rows.add(details);
        details_table.draw();

         }

    function showSaleDetails(details){
      var sale_details_table = $('#sale_details_table').DataTable({
        searching: true,
        bPaginate:false,
        bInfo: true,
        data: details,
        columns: [
            { title: "Item Name" },
            { title: "Quantity" },
            { title: "price" },
            { title: "VAT" },
            { title: "Amount" },
             { title: "Action",defaultContent: "<input type='button' value='Return' id='rtn_btn' class='btn btn-warning btn-rounded btn-sm'/>"}
        ]
    } ); 

    sale_details_table.destroy();
    sale_details_table.clear();
    sale_details_table.rows.add(details);
    sale_details_table.draw();

    $('#sale_details_table tbody').on( 'click', '#rtn_btn', function () {
    var index =  sale_details_table.row( $(this).parents('tr') ).index();
    console.log(index);
    console.log(details);
    } );


     }