
<script>
$(document).ready(function() {

    function notify(message,from, align, type) {
        $.growl({
            message: message,
            url: ''
        }, {
            element: 'body',
            type: type,
            allow_dismiss: true,
            placement: {
                from: from,
                align: align
            },
            offset: {
                x: 30,
                y: 30
            },
            spacing: 10,
            z_index: 999999,
            delay: 2500,
            timer: 1000,
            url_target: '_blank',
            mouse_over: false,

            icon_type: 'class',
            template: '<div data-growl="container" class="alert" role="alert">' +
                '<button type="button" class="btn-close float-end" data-growl="dismiss" aria-label="Close"></button>' +
                '<span data-growl="icon"></span>' +
                '<span data-growl="title"></span>' +
                '<span data-growl="message"></span>' +
                '<a href="#!" data-growl="url"></a>' +
                '</div>'
        });
    }
    @if($flash = session("alert-success"))
        notify('{{session("alert-success")}}', 'top','right','success');
    @endif

    @if($flash = session("alert-danger"))
        notify('{{session("alert-danger")}}', 'top','right','danger');
    @endif

    @if($errors->any())
        var delay = 5000;
        @foreach($errors->all() as $error)
            notify('{{$error}}', 'top','right','danger');

            delay=delay+1000;
        @endforeach
    @endif


});


</script>
