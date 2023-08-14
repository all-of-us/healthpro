$(document).ready(function () {
    let verificationFormSelector = "#id_verification_form";
    $(verificationFormSelector).parsley();
    $(verificationFormSelector + " :input:not(:checkbox, #id_verification_cancel)").prop("disabled", true);

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
        $(verificationFormSelector).parsley().reset();
        if (hasIdVerifications) {
            $("#id-verification-data-box").show();
            $("#id-verification-form-box").hide();
        }
    });

    $(".toggle-id-verification-help-text").on("click", function () {
        let id = $(this).data("id");
        let html = $("#" + id).html();
        $("#helpModalBs5 .modal-body").html(html);
        let modal = new bootstrap.Modal(document.getElementById('helpModalBs5'));
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
