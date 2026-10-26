var table_ledger_filter = $('#fixed-header-ledger').DataTable({
    searching: true,
    bPaginate: true,
    bInfo: true,
    'columns': [
        {'data': 'date'},
        {'data': 'name'},
        {'data': 'quantity'},
        {'data': 'balance'},
        {'data': 'movement'}
    ]
});

// date
$('#d_auto_7').datepicker({
    todayHighlight: true,
    format: 'yyyy-mm-dd',
    changeYear: true,

}).on('change', function () {
    //check if product is selected
    var product = document.getElementById("select_id");
    var product_id = product.options[product.selectedIndex].value;

    console.log(product_id);

    if (Number(product_id) !== 0) {
        productLedgerFilter();
    }

    $('.datepicker').hide();
}).attr('readonly', 'readonly');

// product ledger filter ajax call
function productLedgerFilter() {

    var product = document.getElementById("select_id");
    var product_id = product.options[product.selectedIndex].value;

    var date = document.getElementById("d_auto_7").value;


    var ajaxurl = config.routes.ledgerShow;
    $('#loading').show();
    $.ajax({
        url: ajaxurl,
        type: "get",
        dataType: "json",
        data: {
            date: date,
            product_id: product_id
        },
        success: function (data) {
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

// table_ledger_filter.on('search.dt', function(){

//     console.log(table_ledger_filter.rows({ filter : 'applied'}).nodes().length);
//     // console.log(table_ledger_filter.rows({ filter : 'removed'}).nodes().length);
//     var nodes = table_ledger_filter.rows({ filter : 'removed'}).nodes().length;

//     // console.log(table_ledger_filter.rows({ filter : 'applied'}).data());

//     console.log('results');

//     table_ledger_filter.rows({ filter : 'applied'}).every(function(){
//         var row = this.data();
//         console.log(row);
//     });

//     console.log('removed');

//     table_ledger_filter.rows({ filter : 'removed'}).every(function(){
//         var row = this.data();
//         console.log(row);

//         // for (var i = nodes - 1; i >= 0; i--) {
//         //     console.log(row[i]);
//         // }

//     });


// });
