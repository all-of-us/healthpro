$(document).ready(function () {
    $("input").on("change", function () {
        if ($(this).is("input[type='checkbox']")) {
            $(this)
                .closest("div.question")
                .find("input")
                .each(function () {
                    $($(this).data("vis-toggle")).hide();
                });
            showHighestPriorityChecked(this);
        } else {
            // Hide all questions after the one that was just answered
            $(this).closest("div.question").nextAll().hide().find("input:checked").prop("checked", false);
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
        $($(highestPriorityChecked).data("vis-toggle")).show();
    }
});
