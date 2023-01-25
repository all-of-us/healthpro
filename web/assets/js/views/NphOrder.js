$(document).ready(function () {
    if ($("#order-barcode").length === 1) {
        JsBarcode("#order-barcode", $('#order_info').data('order-id'), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }

    $('.order-ts').pmiDateTimePicker();

    $('.toggle-help-image').on('click', function (e){
        displayHelpModal(e);
    });

    let displayHelpModal = function(e) {
        let image = $(e.currentTarget).data('img');
        let caption = $(e.currentTarget).data('caption');
        let html = '';
        if (image) {
            html += '<img src="' + image + '" class="img-responsive" />';
        }
        if (caption) {
            html += caption;
        }
        this.$('#helpModal .modal-body').html(html);
        this.$('#helpModal').modal();
    };

    $('#scan_barcode').keyup(function () {
        let barcode = $(this).val();
        $('.row-samples').each(function () {
            let sampleId = $(this).find('input:checkbox').data('sample-id').toString();
            if (barcode === sampleId) {
                $(this).find('input:checkbox').prop('checked', true);
                $(this).find('input:text').focus();
                $(this).find('input:text').blur();
            }
        });
    });
});
