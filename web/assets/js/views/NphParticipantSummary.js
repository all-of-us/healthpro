$(document).ready(function () {
    JsBarcode("#participantBarcode", $('#participantBarcode').data('id'), {
        width: 2,
        height: 50,
        displayValue: true
    });

    $('.nav-tabs > li').click(function (e) {
        $(this).addClass('active').siblings().removeClass('active');
        $('#ModuleGroup' + $(this).data('modulenumber')).removeClass('hidden').siblings().addClass('hidden');
    });
});
