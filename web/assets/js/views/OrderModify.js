$(document).ready(function () {
    $('#order-overflow-show').on('click', function (e) {
        $(this).hide();
        $('#order-overflow').show();
        e.preventDefault();
    });
});
