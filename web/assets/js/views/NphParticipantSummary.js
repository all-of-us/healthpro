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
        let modelBodyText = "<p>Are you sure you want to unmark the sample processing as complete?</p>";
        $("#nph_sample_process_complete_module").val(moduleNumber);
        $("#nph_sample_process_complete_period").val(visitType);
        let modifyType = "unfinalized";
        if (dietStatus === "in_progress_finalized" || dietStatus === "error_next_diet_started_finalized") {
            modifyType = "finalized";
        }
        $("#nph_sample_process_complete_modifyType").val(modifyType);
        if ($(this).is(":checked")) {
            processingComplete = 1;
            modelBodyText = "<p>Are you sure you want to mark the sample processing as complete?</p>";
            if (dietStatus !== "in_progress_finalized" && dietStatus !== "error_next_diet_started_finalized") {
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
        let period = moduleSel.find(".nav-link.diet-period-label.active").data("period");
        let checkBoxSel = moduleSel.find("#sample_process_complete_check_" + period);
        checkBoxSel.prop("checked", !checkBoxSel.prop("checked"));
    });

    $(".generate-orders-button").on("click", function (e) {
        e.preventDefault();
        let moduleNumber = parseInt($(this).data("module"));
        let period = $(this).data("period");
        let periodNumber = parseInt(period[period.length - 1]);
        let warningDisplayed = $(this).data("warning-displayed");
        let generateOrderLink = $(this).attr("href");
        let modelBodyText = $("#generate_order_in_complete_diet").html();
        let currentDietStatus = $("#diet_period_status_" + moduleNumber + "_" + period).data("diet-period-status");
        let showWarning = false;
        if (moduleNumber === 1) {
            window.location.href = generateOrderLink;
            return;
        }
        if (periodNumber === 1) {
            if (currentDietStatus === "not_started") {
                let module1Status = $("#diet_period_status_1_LMT").data("diet-period-status");
                if (
                    module1Status !== "error_in_progress_unfinalized_complete" &&
                    module1Status !== "in_progress_finalized_complete"
                ) {
                    modelBodyText = $("#generate_order_in_complete_module").html();
                    showWarning = true;
                } else {
                    window.location.href = generateOrderLink;
                    return;
                }
            } else {
                window.location.href = generateOrderLink;
                return;
            }
        }
        if (periodNumber > 1) {
            periodNumber = periodNumber - 1;
            let previousDietPeriod = "Period" + periodNumber;
            let dietStatus = $("#diet_period_status_" + moduleNumber + "_" + previousDietPeriod).data(
                "diet-period-status"
            );
            if (
                !warningDisplayed &&
                (dietStatus === "in_progress_finalized" || dietStatus === "in_progress_unfinalized")
            ) {
                showWarning = true;
            } else {
                window.location.href = generateOrderLink;
                return;
            }
        }
        if (showWarning) {
            $("#nph_generate_order_warning_log_module").val(moduleNumber);
            $("#nph_generate_order_warning_log_period").val(period);
            $("#nph_generate_order_warning_log_redirectLink").val(generateOrderLink);
            let modelSel = $("#generate_order_warning_message");
            modelSel.find(".modal-body").html(modelBodyText);
            let modal = new bootstrap.Modal(modelSel);
            modal.show();
        }
    });

    $("#orders_generate_continue").on("click", function () {
        $('form[name="nph_generate_order_warning_log"]').submit();
    });

    $(".period-diet-visits").each(function () {
        let $this = $(this);
        let dietVisitStatusText = $this.find(".diet-visit-status-text");
        let dietVisitStatusIcon = $this.find(".diet-visit-status-icon");
        let dietVisitStatuses = $this.find(".diet-visit-status");
        let dietPeriodStatus = dietVisitStatusText.data("diet-period-status");
        let displayErrorIcon = false;
        if (dietPeriodStatus !== "in_progress_unfinalized") {
            dietVisitStatuses.each(function () {
                if (
                    $(this).data("diet-visit-status") !== "Finalized" &&
                    $(this).data("diet-visit-status") !== "Canceled"
                ) {
                    displayErrorIcon = true;
                    return false;
                }
            });
        }
        dietVisitStatusIcon.toggle(displayErrorIcon);
    });

    if ($("#1VisitLMT").find(".no-orders-generate").length > 0) {
        $("#sample_process_box_LMT").hide();
    }
});
