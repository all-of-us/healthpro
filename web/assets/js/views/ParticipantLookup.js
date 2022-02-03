$(document).ready(function () {
    $('.loginPhone').inputmask("(999) 999-9999", {
        "removeMaskOnSubmit": true
    });

    $('form').parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsContainer: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsWrapper: '<div class="help-block"></div>',
        errorTemplate: '<div></div>'
    });
});
