$(document).ready(function () {
    $("input").on("change", function () {
        if ($(this).is("input[type='checkbox']")) {
            $(this)
                .closest("div.question")
                .find("input")
                .each(function () {
                    $($(this).data("vis-toggle")).hide();
                });
            hideAllCheckedQuestions(this);
            showHighestPriorityChecked(this);
        } else {
            hideAllCheckedQuestions(this);
            $(".sampleTypes").val("no");
            if ($(this).data("show-target")) {
                let showTarget = $(this).data("show-target");
                $(showTarget).show();
                if (showTarget.includes("continue")) {
                    let samplesString = $(this).data("samples");
                    let samples = samplesString.split(",");
                    samples.forEach(function (sample) {
                        if (sample !== "urine") {
                            $(`#${sample}`).val("yes");
                        }
                    });
                }
            }
        }
    });

    function showHighestPriorityChecked(element) {
        let highestPriorityChecked = $(element).closest("div.question").find("input:checked")[0];
        hideAllWarnings(element);
        $($(highestPriorityChecked).data("vis-toggle")).show();
    }

    function hideAllCheckedQuestions(element) {
        $(element).closest("div.question").nextAll().hide().find("input:checked").prop("checked", false);
        $("#order_check_cancel").show();
        hideAllWarnings(element);
    }
    function hideAllWarnings(element) {
        $(element).closest("div.question").parent().find("span.warning").hide();
    }

    $("#weightConfirm").on("click", function () {
        $("#first-qn").show();
        $("#weightConfirmButtons").hide();
        $("#weightConfirmText").show();
    });

    $("#peds_urine_only").on("click", () => {
        $('input[name="show-blood-tubes"], input[name="show-saliva-tubes"]').val("no");
        $("#safety-checks").submit();
    });
});
