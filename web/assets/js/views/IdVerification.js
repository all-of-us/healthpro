$(document).ready(function () {
    var verificationFormSelector = '#id_verification_form';
    $(verificationFormSelector).parsley();
    $(verificationFormSelector + ' :input:not(:checkbox)').prop("disabled", true);

    $('#id_verification_confirmation_0').change(function () {
        if (this.checked) {
            $(verificationFormSelector + ' :input:not(:checkbox)').prop("disabled", false);
        } else {
            $(verificationFormSelector + ' :input:not(:checkbox)').prop("disabled", true);
            $(verificationFormSelector + ' :input:not(:checkbox)').val('');
        }
    });

    $('#id_verification_cancel').on('click', function () {
        $(verificationFormSelector)[0].reset();
        $(verificationFormSelector + ' :input:not(:checkbox)').prop("disabled", true);
    });
});
