$(document).ready(function () {
    $("#participant_lookup_telephone_phone").inputmask("(999) 999-9999", {
        removeMaskOnSubmit: true
    });
    $("#participant_lookup_search_dob").inputmask("99/99/9999");

    $("form").parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsContainer: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsWrapper: '<div class="help-block"></div>',
        errorTemplate: "<div></div>"
    });
});
