$(document).ready(function () {
    if ($("#order-barcode").length === 1) {
        JsBarcode("#order-barcode", $('#order_info').data('order-id'), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }

    $('.order-collection-ts').pmiDateTimePicker();
});
