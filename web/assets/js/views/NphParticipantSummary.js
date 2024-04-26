$(document).ready(function () {
    JsBarcode("#participant-barcode", $("#participant-info").data("id"), {
        width: 2,
        height: 50,
        displayValue: true
    });

    $(".nav-tabs > li").click(function (e) {
        if ($(this).find("button").length === 0) {
            $(this).addClass("active").siblings().removeClass("active");
            $("#ModuleGroup" + $(this).data("modulenumber"))
                .removeClass("hidden")
                .siblings()
                .addClass("hidden");
        }
    });

    $(".sample-process-complete-check").on("change", function () {
        let moduleNumber = $(this).attr("data-module-number");
        let visitType = $(this).attr("data-visit-type");
        let dietStatus = $(this).attr("data-diet-status");
        let processingComplete = 0;
        let modelTitleText = "Confirm";
        let modelBodyText = "<p>Are you sure you want to unmark the samples as processing complete?</p>";
        $("#nph_sample_process_complete_module").val(moduleNumber);
        $("#nph_sample_process_complete_period").val(visitType);
        if ($(this).is(":checked")) {
            processingComplete = 1;
            modelBodyText = "<p>Are you sure you want mark the samples as processing complete?</p>";
            if (dietStatus === "in_progress_unfinalized") {
                modelTitleText = "<span class='text-danger'>Warning!</span>";
                let moduleId =
                    parseInt(moduleNumber) === 1
                        ? "sample_process_complete_unfinalized_message_1"
                        : "sample_process_complete_unfinalized_message";
                modelBodyText = $("#" + moduleId).html();
            }
        }
        $("#nph_sample_process_complete_status").val(processingComplete);
        let modelSel = $("#sample_process_complete_modal");
        modelSel.find(".modal-title").html(modelTitleText);
        modelSel.find(".modal-body").html(modelBodyText);
        let modal = new bootstrap.Modal(modelSel);
        modal.show();
    });

    $("#sample_process_complete_continue").on("click", function () {
        $('form[name="nph_sample_process_complete"]').submit();
    });

    $("#sample_process_go_back").on("click", function () {
        let moduleNumber = $(".nav-item.participant-module.active").data("modulenumber");
        let moduleSel = $("#ModuleGroup" + moduleNumber);
        let period = moduleSel.find(".nav-link.participant-diet-period.active").data("period");
        let checkBoxSel = moduleSel.find("#sample_process_complete_check_" + period);
        checkBoxSel.prop("checked", !checkBoxSel.prop("checked"));
    });
});
