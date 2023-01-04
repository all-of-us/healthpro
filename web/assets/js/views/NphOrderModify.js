$(document).ready(function () {
    let showHideOtherField = function () {
        if ($("#nph_order_modify_reason option:selected").val() === 'OTHER') {
            $('#nph_order_modify_otherText').show();
        } else {
            $('#nph_order_modify_otherText').hide();
        }
    }

    showHideOtherField();

    $('#nph_order_modify_reason').on('change', function () {
        showHideOtherField();
    });
});
