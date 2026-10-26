
//Create Modal
$('#create').on('show.bs.modal', function () {   
var input = document.querySelector("#phone-number");
var errorMsg = document.querySelector("#error-msg");
var validMsg = document.querySelector("#valid-msg");
validateMobile(input,errorMsg,validMsg);
});


//Edit Modal
$('#edit').on('show.bs.modal', function (event) {
var button = $(event.relatedTarget);
var modal = $(this);
var no=document.getElementById ("phone_edit").value;
var input = document.querySelector("#phone_edit");
var errorMsg = document.querySelector("#error-msg-edit");
var validMsg = document.querySelector("#valid-msg-edit");
var action = 'Edit';
modal.find('.modal-body #id_edit').val(button.data('id'));
modal.find('.modal-body #name_edit').val(button.data('name'));
modal.find('.modal-body #address_edit').val(button.data('address'));
modal.find('.modal-body #credit_input_edit').val(button.data('credit_limit'));
modal.find('.modal-body #phone_edit').val(button.data('phone'));
modal.find('.modal-body #email_edit').val(button.data('email'));
validateMobile(input,errorMsg,validMsg,action);
});



$('#delete').on('show.bs.modal', function (event) {
var button = $(event.relatedTarget)
var message = "Are you sure you want to delete '".concat(button.data('name'), "'?");
var modal = $(this)
modal.find('.modal-body #message').text(message);
modal.find('.modal-body #id').val(button.data('id'))
})


//Change the input into money format with fixed 2 decimal places
$("#credit_input").on('change', function(evt){
if (evt.which != 110 ){//not a fullstop
var n = Math.abs((parseFloat($(this).val().replace(/\,/g,''),10)||0));
$(this).val(n.toLocaleString("en", {
minimumFractionDigits: 2,
maximumFractionDigits: 2,
}));
}
var credit_input = (document.getElementById("credit_input").value);
credit_limit_amount=(parseFloat(credit_input.replace(/\,/g,''), 10)||0);
$('#create').find('.modal-body #credit_limit_amount').val(credit_limit_amount);
});

//Change the input into money format with fixed 2 decimal places
$("#credit_input_edit").on('change', function(evt){
if (evt.which != 110 ){//not a fullstop
var n = Math.abs((parseFloat($(this).val().replace(/\,/g,''),10)||0));
$(this).val(n.toLocaleString("en", {
minimumFractionDigits: 2,
maximumFractionDigits: 2,
}));
}
var credit_input = (document.getElementById("credit_input_edit").value);
credit_limit_amount=(parseFloat(credit_input.replace(/\,/g,''), 10)||0);
$('#edit').find('.modal-body #credit_limit_amount_edit').val(credit_limit_amount);
});

//intenational Phone Number Validation
function validateMobile(input,errorMsg,validMsg,action){
// here, the index maps to the error code returned from getValidationError
var errorMap = ["Invalid Phone Number", "Invalid Country Code", "Too Short", "Too Long", "Invalid Phone Number"];
// initialise plugin
input.addEventListener('keyup', reset);
// Check if its edit or create ie: on edit nationalMode is dissabled
if(action)
{
var iti = window.intlTelInput(input,{
customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
return "e.g. " + selectedCountryPlaceholder;
},
initialCountry: "auto",
geoIpLookup: function(callback) {
$.get('https://ipinfo.io', function() {}, "jsonp").always(function(resp) {
var countryCode = (resp && resp.country) ? resp.country : "";
callback(countryCode);
});
},
utilsScript: "../assets/plugins/intl-tel-input/js/utils.js?1562189064761",
onlyCountries: ["tz","ug","ke","rw","bi","sd"],
nationalMode:false,
});
}
else
{
var iti = window.intlTelInput(input,{
customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
return "e.g. " + selectedCountryPlaceholder;
},
initialCountry: "auto",
geoIpLookup: function(callback) {
$.get('https://ipinfo.io', function() {}, "jsonp").always(function(resp) {
var countryCode = (resp && resp.country) ? resp.country : "";
callback(countryCode);
});
},
utilsScript: "../assets/plugins/intl-tel-input/js/utils.js?1562189064761",
onlyCountries: ["tz","ug","ke","rw","bi","sd"],
});
}
var reset = function() {
input.classList.remove("error");
errorMsg.innerHTML = "";
errorMsg.classList.add("hide");
validMsg.classList.add("hide");
};

// on blur: validate
input.addEventListener('blur', function() {
reset();
if (input.value.trim()) {
if (iti.isValidNumber()) {
$('#save_btn').prop('disabled',false);
$('#edit_btn').prop('disabled',false);
validMsg.classList.remove("hide");
document.getElementById('phone-number').value = iti.getNumber();
if(action){//On edit there is action variable
document.getElementById('phone_edit').value = iti.getNumber();
}
} 
else
{
input.classList.add("error");
$('#save_btn').prop('disabled',true);
$('#edit_btn').prop('disabled',true);
var errorCode = iti.getValidationError();
errorMsg.innerHTML = errorMap[errorCode];
errorMsg.classList.remove("hide");
}
}
});

// on keyup / change flag: reset
input.addEventListener('change', reset);
}
