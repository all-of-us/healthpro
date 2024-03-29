$(document).ready(function () {
    let verificationFormSelector = "#id_verification_form";
    $(verificationFormSelector).parsley();
    $(verificationFormSelector + " :input:not(:checkbox, #id_verification_cancel)").prop("disabled", true);
    if (!$("#participant_info").data("pediatric")) {
        $("#id_verification_visit_type>option[value='PEDIATRIC_VISIT']").remove();
    }

    $("#id_verification_visit_type").on("change", function () {
        if ($(this).val() === "PEDIATRIC_VISIT") {
            $("#id_verification_guardian_verified_0").attr("checked", true);
            $("#id_verification_guardian_verified").attr("hidden", false);
        } else {
            $("#id_verification_guardian_verified_0").attr("checked", false);
            $("#id_verification_guardian_verified").attr("hidden", true);
        }
    });

    $("#id_verification_confirmation_0").change(function () {
        if (this.checked) {
            $(verificationFormSelector + " :input:not(:checkbox, #id_verification_cancel)").prop("disabled", false);
        } else {
            $(verificationFormSelector + " :input:not(:checkbox, #id_verification_cancel)").prop("disabled", true);
            $(verificationFormSelector + " :input:not(:checkbox)").val("");
            $(verificationFormSelector).parsley().reset();
        }
    });

    let hasIdVerifications = $("#id_verification").data("has-id-verifications");

    $("#id_verification_cancel").on("click", function () {
        $(verificationFormSelector)[0].reset();
        $(verificationFormSelector + " :input:not(:checkbox, #id_verification_cancel)").prop("disabled", true);
        $("#id_verification_guardian_verified_0").attr("checked", false);
        $("#id_verification_guardian_verified").attr("hidden", true);
        $(verificationFormSelector).parsley().reset();
        if (hasIdVerifications) {
            $("#id-verification-data-box").show();
            $("#id-verification-form-box").hide();
        }
    });

    $(".toggle-id-verification-help-text").on("click", function (e) {
        e.preventDefault();
        let id = $(this).data("id");
        let html = $("#" + id).html();
        $("#helpModalBs5 .modal-body").html(html);
        let modal = new bootstrap.Modal(document.getElementById("helpModalBs5"));
        modal.show();
    });

    if (hasIdVerifications) {
        $("#id-verification-data-box").show();
        $("#id-verification-form-box").hide();
    }

    $(".btn-id-verification-add-new").on("click", function () {
        $("#id-verification-data-box").hide();
        $("#id-verification-form-box").show();
    });
});
