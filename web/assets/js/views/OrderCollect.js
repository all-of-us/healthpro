$(document).ready(function () {
    $('#checkall').on('change', function () {
        $('#order_collectedSamples input:checkbox:enabled').prop('checked', $(this).prop('checked'));
    });
    $('#order_collectedTs').pmiDateTimePicker();
    new PMI.views['OrderSubPage']({
        el: $("body")
    });
});
