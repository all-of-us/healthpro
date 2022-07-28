$(document).ready(function () {
    // Form error display/UX
    $('form[name="deceased_report"]').parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsContainer: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsWrapper: '<div class="deceased-report-errors help-block"></div>',
        errorTemplate: '<div></div>',
        trigger: "keyup change"
    });

    $('#deceased_report_dateOfDeath').pmiDateTimePicker({
        format: 'MM/DD/YYYY',
        maxDate: new Date().setHours(23, 59, 59, 999),
        useCurrent: false
    });

    var reportMechanism = $('input[name="deceased_report[reportMechanism]"]');
    var reportMechanismChecked = $('input[name="deceased_report[reportMechanism]"]:checked');
    var nextOfKinName = $('input[name="deceased_report[nextOfKinName]"]');
    var nextOfKinRelationship = $('select[name="deceased_report[nextOfKinRelationship]"]');
    var reportMechanismOtherDescription = $('textarea[name="deceased_report[reportMechanismOtherDescription]"]');

    // Set initial state of form on load
    if (reportMechanismChecked.length == 0
        || reportMechanismChecked.val() == 'EHR'
    ) {
        $('.next_of_kin_details').addClass('collapse');
        $('.other_details').addClass('collapse');
    } else if (reportMechanismChecked.val() == 'OTHER') {
        $('.next_of_kin_details').addClass('collapse');
        $('.other_details').removeClass('collapse');
    }

    // Handle onChange event for Report Mechanism
    $(reportMechanism).on('change', function (e) {
        switch ($(e.target).val()) {
            case 'EHR':
                $('.next_of_kin_details').addClass('collapse');
                $('.other_details').addClass('collapse');
                nextOfKinName.attr('required', false);
                nextOfKinRelationship.attr('required', false);
                reportMechanismOtherDescription.attr('required', false);
                break;
            case 'OTHER':
                $('.next_of_kin_details').addClass('collapse');
                $('.other_details').removeClass('collapse');
                nextOfKinName.attr('required', false);
                nextOfKinRelationship.attr('required', false);
                reportMechanismOtherDescription.attr('required', true);
                break;
            default:
                $('.next_of_kin_details').removeClass('collapse');
                $('.other_details').addClass('collapse');
                nextOfKinName.attr('required', true);
                nextOfKinRelationship.attr('required', true);
                reportMechanismOtherDescription.attr('required', false);
        }
    });
});
