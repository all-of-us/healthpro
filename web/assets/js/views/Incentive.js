$(document).ready(function () {
    let onSiteDetails = $("#on_site_details");
    let readOnlyView = onSiteDetails.data("read-only-view");

    $("#incentive_create form").parsley();

    var setIncentiveDateGiven = function () {
        const incentiveDatePickerElements = document.querySelectorAll(".incentive-date-given");
        incentiveDatePickerElements.forEach((element) => {
            const maxDate = new Date();
            maxDate.setHours(23, 59, 59, 999);
            bs5DateTimepicker(element, {
                format: "MM/dd/yyyy",
                maxDate: maxDate,
                clock: false
            });
        });
    };

    var incentivePrefix = "incentive_";

    var toggleFormFieldsRequired = function (idPrefix = "#incentive_create") {
        if ($(idPrefix + " .incentive-declined").is(":checked")) {
            $(idPrefix + " input, select, textarea")
                .not(".incentive-date-given")
                .removeAttr("required");
        } else {
            $(idPrefix + " .toggle-required").attr("required", "required");
            // For promotional item remove required attribute for amount field
            let incentiveTypeSelector = idPrefix + " #" + incentivePrefix + "incentive_type";
            if ($(incentiveTypeSelector).val() === "promotional") {
                let incentiveAmountSelector = idPrefix + " #" + incentivePrefix + "incentive_amount";
                $(incentiveAmountSelector).removeAttr("required");
            }
            if ($(incentiveTypeSelector).val() === "item_of_appreciation") {
                let incentiveAmountSelector = idPrefix + " #" + incentivePrefix + "incentive_amount";
                $(incentiveAmountSelector).removeAttr("required");
            }
        }
        $(idPrefix + " .incentive-form")
            .parsley()
            .reset();
    };

    var handleIncentiveFormFields = function (that, idPrefix = "#incentive_create") {
        let selectFieldId = $(that).attr("id").replace(incentivePrefix, "");
        let otherFieldSelector = idPrefix + " #" + incentivePrefix + "other_" + selectFieldId;
        if ($(that).val() === "other") {
            $(otherFieldSelector).parent().show();
            $(otherFieldSelector).attr("required", "required");
        } else {
            $(otherFieldSelector).parent().hide();
            $(otherFieldSelector).val("");
            $(otherFieldSelector).removeAttr("required");
        }
        if (selectFieldId === "incentive_type") {
            var giftCardFieldSelector = idPrefix + " #" + incentivePrefix + "gift_card_type";
            if ($(that).val() === "gift_card") {
                $(idPrefix + " #gift_card").show();
                $(giftCardFieldSelector).attr("required", "required");
            } else {
                $(idPrefix + " #gift_card").hide();
                $(giftCardFieldSelector).val("");
                $(giftCardFieldSelector).removeAttr("required");
            }
            var incentiveAmountSelector = idPrefix + " #" + incentivePrefix + "incentive_amount";
            var otherIncentiveAmountSelector = idPrefix + " #" + incentivePrefix + "other_incentive_amount";
            if ($(that).val() === "promotional") {
                $(incentiveAmountSelector).val("");
                $(incentiveAmountSelector).attr("disabled", "disabled");
                $(incentiveAmountSelector).removeAttr("required");
                $(otherIncentiveAmountSelector).parent().hide();
                $(otherIncentiveAmountSelector).val("");
                $(otherIncentiveAmountSelector).removeAttr("required");
            } else {
                if (!readOnlyView) {
                    $(incentiveAmountSelector).removeAttr("disabled");
                }
                $(incentiveAmountSelector).attr("required", "required");
            }
            if ($(that).val() === "item_of_appreciation") {
                $(idPrefix + " #type_of_item").show();
                $(idPrefix + " #number_of_items").show();
                $(incentiveAmountSelector).prop("disabled", true);
                $(incentiveAmountSelector).val("");
                $(incentiveAmountSelector).removeAttr("required");
                $("#incentive_type_of_item").attr("required", "required");
                $("#incentive_number_of_items").attr("required", "required");
                $(idPrefix + " #type_of_item .toggle-required").attr("required", "required");
            } else {
                $(idPrefix + " #type_of_item").hide();
                $("#incentive_type_of_item").removeAttr("required");
                $("#incentive_number_of_items").removeAttr("required");
                $(idPrefix + " #number_of_items").hide();
                $(incentiveAmountSelector).prop("disabled", false);
                $(incentiveAmountSelector).attr("required", "required");
                $(idPrefix + " #type_of_item").val("");
                $(idPrefix + " #type_of_item .toggle-required").removeAttr("required");
            }
        }
        let recipientSelector = $("#incentive_recipient");
        let recipientOtherSelector = $("#incentive_other_incentive_recipient");
        if (recipientSelector.val() === "other") {
            recipientOtherSelector.parent().show();
            recipientOtherSelector.attr("required", "required");
        } else {
            recipientOtherSelector.parent().hide();
            recipientOtherSelector.removeAttr("required");
            recipientOtherSelector.val("");
        }
        $(idPrefix + " .incentive-form")
            .parsley()
            .reset();
    };

    var showHideIncentiveFormFields = function (idPrefix = "#incentive_create") {
        var incentiveFormSelect = $(idPrefix + " select");
        var incentiveFormSelectDeclined = $(idPrefix + "_declined");

        incentiveFormSelect.each(function () {
            handleIncentiveFormFields(this, idPrefix);
        });

        incentiveFormSelect.change(function () {
            handleIncentiveFormFields(this, idPrefix);
        });

        incentiveFormSelectDeclined.change(function () {
            toggleFormFieldsRequired(idPrefix);
        });

        toggleFormFieldsRequired(idPrefix);
    };

    showHideIncentiveFormFields();

    if ($(".incentive-form").find("div").hasClass("alert-danger")) {
        $('[href="#on_site_details"]').tab("show");
    }

    let hasIncentives = $("#incentive_create").data("has-incentives");

    $("#incentive_cancel").on("click", function () {
        let incentiveFormSelector = $("#incentive_create .incentive-form");
        incentiveFormSelector[0].reset();
        showHideIncentiveFormFields();
        incentiveFormSelector.parsley().reset();
        if (hasIncentives) {
            $("#incentives-data-box").show();
            $("#incentives-form-box").hide();
        }
    });

    $(".incentive-amend").on("click", function () {
        var url = $(this).data("href");
        $("#incentive_amend_ok").data("href", url);
        $("#incentive_amend_modal").modal("show");
    });

    $(".incentive-remove").on("click", function () {
        var incentiveId = $(this).data("id");
        $("#incentive_remove_id").val(incentiveId);
        $("#incentive_remove_modal").modal("show");
    });

    $("#incentive_amend_ok").on("click", function () {
        var amendButton = $(this).button("loading");
        var incentiveEditFormModal = $("#incentive_edit_form_modal");
        var modelContent = $("#incentive_edit_form_modal .modal-content");
        modelContent.html("");
        // Load data from url
        modelContent.load($(this).data("href"), function () {
            $("#incentive_amend_modal").modal("hide");
            amendButton.button("reset");
            incentiveEditFormModal.modal("show");
        });
    });

    if (!readOnlyView) {
        /* Gift card search */
        var getGiftCards = new Bloodhound({
            name: "giftCardType",
            datumTokenizer: Bloodhound.tokenizers.whitespace,
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            limit: 10,
            prefetch: "/ajax/search/giftcard-prefill",
            remote: {
                url: "/ajax/search/giftcard/%QUERY",
                wildcard: "%QUERY"
            }
        });

        window.getTypeOfItem = new Bloodhound({
            name: "typeOfItem",
            datumTokenizer: Bloodhound.tokenizers.whitespace,
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            limit: 10,
            prefetch: "/ajax/search/type-of-item-prefill",
            remote: {
                url: "/ajax/search/type-of-item/%QUERY",
                wildcard: "%QUERY"
            }
        });

        var handleGiftCardAutoPopulate = function (idPrefix = "#incentive_create") {
            $(idPrefix + " .gift-card").typeahead(
                {
                    highlight: true
                },
                {
                    source: getGiftCards
                }
            );
        };
    }

    function handleTypeOfItemAutoPopulate(idPrefix = "#incentive_create") {
        $(idPrefix + " .item-type").typeahead(
            {
                highlight: true
            },
            {
                source: getTypeOfItem
            }
        );
    }

    var incentiveEditModal = "#incentive_edit_form_modal";

    $(incentiveEditModal).on("shown.bs.modal", function () {
        showHideIncentiveFormFields("#incentive_edit");
        $("#incentive_edit form").parsley();
        setIncentiveDateGiven();
    });

    $(incentiveEditModal).on("hidden.bs.modal", function () {
        $("#patient-status-details-modal .modal-content").html("");
    });

    setIncentiveDateGiven();

    if (!readOnlyView) {
        handleGiftCardAutoPopulate();
        handleTypeOfItemAutoPopulate();
    }

    if (hasIncentives) {
        $("#incentives-data-box").show();
        $("#incentives-form-box").hide();
    }

    $(".btn-incentive-add-new").on("click", function () {
        $("#incentives-data-box").hide();
        $("#incentives-form-box").show();
    });
});
