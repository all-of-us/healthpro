$(document).ready(function () {
    // Form error display/UX
    $('form[name="deceased_report_review"]').parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsContainer: function (el) {
            return el.$element.closest(".form-group");
        },
        errorsWrapper: '<div class="deceased-report-review-errors help-block"></div>',
        errorTemplate: '<div></div>',
        trigger: "keyup change"
    });

    var reportStatus = $('input[name="deceased_report_review[reportStatus]"]')
    var reportStatusChecked = $('input[name="deceased_report_review[reportStatus]"]:checked')
    var denialReason = $('select[name="deceased_report_review[denialReason]"]')
    var denialReasonChecked = $('select[name="deceased_report_review[denialReason]"]:checked')
    var denialReasonOtherDescription = $('textarea[name="deceased_report_review[denialReasonOtherDescription]"]')

    // Set initial state of form on load
    if (reportStatusChecked.length == 0
        || reportStatusChecked.val() == 'final'
    ) {
        $('.denial_reason').addClass('collapse');
        $('.denial_reason_other').addClass('collapse')
    } else if (reportMechanismChecked.val() == 'OTHER') {
        $('.denial_reason_other').removeClass('collapse')
    }

    // Handle onChange event for Report Mechanism
    $(reportStatus).on('change', function (e) {
        switch ($(e.target).val()) {
            case 'cancelled':
                $('.denial_reason').removeClass('collapse');
                denialReason.attr('required', true);
                break;
            default:
                $('.denial_reason').addClass('collapse');
                $('.denial_reason_other').addClass('collapse');
                denialReason.attr('required', false);
                denialReasonOtherDescription.attr('required', false);
        }
    })

    // Handle onChange event for Denial Reason
    $(denialReason).on('change', function (e) {
        switch ($(e.target).val()) {
            case 'OTHER':
                console.log('OTHER DENIAL REASON')
                $('.denial_reason_other').removeClass('collapse');
                denialReasonOtherDescription.attr('required', true);
                break;
            default:
                $('.denial_reason_other').addClass('collapse');
                denialReasonOtherDescription.attr('required', false);
        }
    })
});
