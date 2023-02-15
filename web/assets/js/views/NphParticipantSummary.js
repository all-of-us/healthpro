$(document).ready(function () {
    JsBarcode("#participantBarcode", $("#participantBarcode").data("id"), {
        width: 2,
        height: 50,
        displayValue: true
    });

    $(".nav-tabs > li").click(function (e) {
        const oldModuleNumber = $(this).siblings(".active").data("modulenumber");
        $(this).addClass("active").siblings().removeClass("active");
        const newModuleNumber = $(this).data("modulenumber");
        $(`#ModuleGroup${newModuleNumber}`)
            .removeClass("hidden")
            .siblings()
            .addClass("hidden");
        $('.label.nph-module-badge').removeClass(`nph-module-${oldModuleNumber}`)
            .addClass(`nph-module-${newModuleNumber}`).text(`NPH Module ${newModuleNumber}`);
    });
});
