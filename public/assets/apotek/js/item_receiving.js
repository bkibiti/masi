    var cart=[];
    var default_cart=[];
    var order_cart=[];  
    var details = [];
    var sale_items=[];


    var items_table = $('#items_table').DataTable({
        searching: true,
        bPaginate:false,
        bInfo: true,
        data:sale_items,
        columns: [
            {title:"ID"},
            { title: "Item Name" },
            { title: "Quantity" },
            { title: "Unit Price" },
            { title: "VAT" },
            { title: "Discount" },
            { title: "Net Price" },
            { title: "Action",defaultContent: "<input type='button' value='Receive' id='receive_btn' class='btn btn-primary btn-rounded btn-sm'/>"}
        ]
    }); 

    var cart_table= $('#cart_table').DataTable({
        searching: false,
        bPaginate: false,
        bInfo: false,
        data: cart,
        columns: [
            { title: "Item Name" },
            { title: "Unit Buy Price" },
            { title: "Quantity"},
            { title: "VAT"},
            { title: "Amount"},
            { title: "Action",defaultContent: "<div><input type='button' value='Edit' id='edit_btn' class='btn btn-info btn-rounded btn-sm'/><input type='button' value='Delete' id='delete_btn' class='btn btn-danger btn-rounded btn-sm'/></div>"}
        ]
    } );

    var details_table = $('#details_table').DataTable({
            searching: true,
            bPaginate:false,
            bInfo: true,
            data: details,
            columns: [

                { title: "Item Name" },
                { title: "Quantity" },
                { title: "Price" },
                { title: "VAT" },
                { title: "Net Price" }
            ]
        }); 

    $('#sale_list_Table').DataTable({
        searching: true,
        bPaginate:false,
        bInfo: true,
        fixedHeader: true
    });
    
    $('#cart_table tbody').on( 'click', '#edit_btn', function () {
    var row_data =  cart_table.row( $(this).parents('tr') ).data();
    var index =  cart_table.row( $(this).parents('tr') ).index();
    quantity=row_data[2];
    price=row_data[1];
    row_data[2]="<input type='number'class='form-control' id='edit_quantity' required/>";
    row_data[1]="<input type='number'class='form-control' id='edit_price' required/>";
    cart[index] = row_data;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    document.getElementById("edit_quantity").value=quantity;
    document.getElementById("edit_price").value=formatMoney(price);
    });


    $('#cart_table tbody').on( 'change', '#edit_quantity', function () {
    var row_data =  cart_table.row( $(this).parents('tr') ).data();
    var index =  cart_table.row( $(this).parents('tr') ).index();
    row_data[2]= document.getElementById("edit_quantity").value;
    row_data[1]= document.getElementById("edit_price").value;

    row_data[1]=formatMoney(parseFloat(default_cart[index][0].replace(/\,/g,''), 10));
    row_data[3]=formatMoney(parseFloat(default_cart[index][1].replace(/\,/g,''), 10)*row_data[2]);
    row_data[4]=formatMoney(parseFloat(default_cart[index][2].replace(/\,/g,''), 10)*row_data[2]);
    order_cart[index].quantity = row_data[2];
    order_cart[index].price = parseFloat(default_cart[index][0].replace(/\,/g,''), 10);
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


    $('#cart_table tbody').on( 'change', '#edit_price', function () {
    var row_data =  cart_table.row( $(this).parents('tr') ).data();
    var index =  cart_table.row( $(this).parents('tr') ).index();
    row_data[2]= document.getElementById("edit_quantity").value;
    row_data[1]= document.getElementById("edit_price").value;


    // row_data[1]=formatMoney(parseFloat(default_cart[index][0].replace(/\,/g,''), 10));
    //row_data[3]=formatMoney(parseFloat(default_cart[index][1].replace(/\,/g,''), 10)*row_data[2]);
    row_data[4]=formatMoney(parseFloat(default_cart[index][2].replace(/\,/g,''), 10)*row_data[2]);
    

    //order_cart[index].price = formatMoney(row_data[1]);   
    order_cart[index].quantity = row_data[2];
    default_cart[index][0]= formatMoney(row_data[1]);
    order_cart[index].price = parseFloat(default_cart[index][0].replace(/\,/g,''), 10);
    order_cart[index].vat = parseFloat(default_cart[index][1].replace(/\,/g,''), 10)*row_data[2];
    order_cart[index].amount = parseFloat(default_cart[index][2].replace(/\,/g,''), 10)*row_data[2];
    stringified_cart=JSON.stringify( order_cart );
    cart[index] = row_data;

    console.log(default_cart);
    console.log(order_cart);
    console.log(cart);

    document.getElementById("order_cart").value =  stringified_cart;
    discount();
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    
    });


    $('#cart_table tbody').on( 'click', '#delete_btn', function () {
    var index =  cart_table.row( $(this).parents('tr') ).index();
    var price = parseFloat(cart[index][1].replace(/\,/g,''), 10);
    var unit_total = parseFloat(cart[index][4].replace(/\,/g,''), 10);
    order_cart.splice(index, 1); 
    stringified_cart=JSON.stringify( order_cart );
    document.getElementById("order_cart").value =  stringified_cart;
    default_cart.splice(index, 1); 
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
        document.getElementById("id_vat").value = formatMoney(total_vat);
        document.getElementById("sale_discount").value = sale_dicount;
        document.getElementById("total_price").value =total;
        document.getElementById("vat").value = total_vat;
        document.getElementById("sub_total_price").value = formatMoney(sub_total);
        document.getElementById("total").value = formatMoney(total);
        document.getElementById("sub_total").value =  formatMoney(sub_total);
    }
   

    function val() {
    var item=[];
    var cart_data=[];
    var sale_product={};
    product = document.getElementById("select_id").value;
    var selected_fields = product.split(',');
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
    sale_product.product_id = selected_fields[2];
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
    //document.getElementById("vat").value = formatMoney(total_vat);
    document.getElementById("total").value = formatMoney(total);
    document.getElementById("sub_total").value =  formatMoney(sub_total);
    document.getElementById("order_cart").value =  stringified_cart;
    cart_table.clear();
    cart_table.rows.add(cart);
    cart_table.draw();
    } 
     function showQuoteDetails(details){
        details_table.clear();
        details_table.rows.add(details);
        details_table.draw();

         }

    function saleReturn(items){
        var return_cart = [];
        document.getElementById('sales').style.display='none';
        sale_items = [];
        returned=" <h4><span class='badge badge-secondary'>Partial Returned</span></h4>"
        pending=" <h4><span class='badge badge-warning'>Pending Return</span></h4>"
        rejected=" <h4><span class='badge badge-danger'>Rejected Return</span></h4>"
        items.forEach(function(item){
        var item_data=[];
        if(item.status !== 3){
        item_data.push(item.id);
        item_data.push(item.name);
        item_data.push(item.quantity);
        item_data.push(item.price);
        item_data.push(item.vat);
        item_data.push(item.discount);
        item_data.push(item.amount);
        if (item.status ==2) {
        item_data.push(pending)
        }
        if (item.status ==4) {
        item_data.push(rejected)
        }
        if (item.status ==5) {
        item_data.push(returned)
        }
        sale_items.push(item_data);  
        }
       
       
      })
        items_table.clear();
        items_table.rows.add(sale_items);
        items_table.column(0).visible(false);
        items_table.draw();
        document.getElementById('items').style.display='block';
        
        $('#cancel').on('click', function() {
         return_cart = [];
         document.getElementById('sales').style.display='block';
         document.getElementById('items').style.display='none';
        }); 

     }

      function saleDetails(items){
        document.getElementById('sales').style.display='none';
        sold=" <h4><span class='badge badge-success'>Sold</span></h4>"
        pending=" <h4><span class='badge badge-warning'>Pending</span></h4>"
        returned=" <h4><span class='badge badge-danger'>Returned</span></h4>"
        sale_items = [];
        items.forEach(function(item){
        var item_data=[];
        item_data.push(item.id);
        item_data.push(item.name);
        item_data.push(item.quantity);
        item_data.push(item.price);
        item_data.push(item.vat);
        item_data.push(item.discount);
        item_data.push(item.amount);
         if (item.status ==2) {
         item_data.push(pending)
        }
        else if (item.status ==3) {
        item_data.push(returned)
        }
        else{
        item_data.push(sold)  
        }
        sale_items.push(item_data);  

        console.log(item);

      })


        items_table.clear();
        items_table.rows.add(sale_items);
        items_table.column(0).visible(false);
        items_table.draw();
        document.getElementById('items').style.display='block';
        $('#cancel').on('click', function() {
         return_cart = [];
         document.getElementById('sales').style.display='block';
         document.getElementById('items').style.display='none';
        }); 

     }


    $('#items_table tbody').on( 'click', '#receive_btn', function () {
    var index =  items_table.row( $(this).parents('tr') ).index();
    var data =  items_table.row( $(this).parents('tr') ).data();
    console.log(data);
    $('#receive').modal('show');
    
    $('#receive').find('.modal-body #quantity_id').val(data[2]);
    $('#receive').find('.modal-body #name_of_item').val(data[1]);
    $('#receive').find('.modal-body #unit_price_id').val(data[3]);
    $('#receive').find('.modal-body #id_of_item').val(data[0]);
     document.getElementById('save_btn').style.display = 'block';
    $('#receive').on( 'change', '#rtn_qty', function (){
    var quantity = document.getElementById('rtn_qty').value;
    if(quantity > data[2]){
    document.getElementById('save_btn').disabled ='true';
    document.getElementById('qty_error').style.display = 'block';
    $('#receive').find('.modal-body #qty_error').text('The maximum quantity is '+data[2]);
    }
    else{
     document.getElementById('qty_error').style.display = 'none';
    $('#save_btn').prop('disabled',false);
       }
    });
     });

    