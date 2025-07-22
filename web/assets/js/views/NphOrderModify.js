$(document).ready(function () {
    let showHideOtherField = function () {
        if ($(".modify-reason option:selected").val() === "OTHER") {
            $(".modify-other-text").show();
        } else {
            $(".modify-other-text").hide();
        }
    };

    showHideOtherField();

    $(".modify-reason").on("change", function () {
        showHideOtherField();
    });

    $(document).on("change", ".modify-check-all", function () {
        const $accordion = $(this).closest(".accordion-item");
        const isChecked = $(this).is(":checked");
        $accordion.find(".modify-samples input[type='checkbox']:enabled").prop("checked", isChecked);
    });

    $(document).on("change", ".modify-samples input[type='checkbox']", function () {
        const $accordionItem = $(this).closest(".accordion-item");
        const $checkAll = $accordionItem.find(".modify-check-all");
        const total = $accordionItem.find(".modify-samples input[type='checkbox']:enabled").length;
        const checked = $accordionItem.find(".modify-samples input[type='checkbox']:enabled:checked").length;
        $checkAll.prop("checked", total > 0 && total === checked);
    });

    $("#order_modify_check_all").on("change", function () {
        const isChecked = $(this).is(":checked");
        $('.modify-samples input[type="checkbox"]:not(:disabled)').prop("checked", isChecked);
    });

    $('.modify-samples input[type="checkbox"]:not(:disabled)').on("change", function () {
        const total = $('.modify-samples input[type="checkbox"]:not(:disabled)').length;
        const checked = $('.modify-samples input[type="checkbox"]:not(:disabled):checked').length;
        $("#order_modify_check_all").prop("checked", total === checked);
    });
});
