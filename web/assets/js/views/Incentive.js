$(document).ready(function () {
    $("#incentive form").parsley();

    $('#incentive_incentive_date_given').pmiDateTimePicker({
        format: 'MM/DD/YYYY',
        maxDate: new Date().setHours(23, 59, 59, 999),
        useCurrent: false
    });

    var incentivePrefix = 'incentive_';

    var handleIncentiveFormFields = function (that, idPrefix = '') {
        var selectFieldId = $(that).attr('id').replace(incentivePrefix, '');
        var otherFieldSelector = idPrefix + '#' + incentivePrefix + 'other_' + selectFieldId;
        if ($(that).val() === 'other') {
            $(otherFieldSelector).parent().show();
            $(otherFieldSelector).attr('required', 'required');
        } else {
            $(otherFieldSelector).parent().hide();
            $(otherFieldSelector).val('');
            $(otherFieldSelector).removeAttr('required');
        }
        if (selectFieldId === 'incentive_type') {
            var giftCardFieldSelector = idPrefix + '#' + incentivePrefix + 'gift_card_type';
            if ($(that).val() === 'gift_card') {
                $(giftCardFieldSelector).parent().show();
                $(giftCardFieldSelector).attr('required', 'required');
            } else {
                $(giftCardFieldSelector).parent().hide();
                $(giftCardFieldSelector).val('');
                $(giftCardFieldSelector).removeAttr('required');
            }
        }
    };

    var showHideIncentiveFormFields = function (idPrefix = '') {
        var incentiveFormSelect = $(idPrefix + '#incentive select');

        incentiveFormSelect.each(function () {
            handleIncentiveFormFields(this, idPrefix);
        });

        incentiveFormSelect.change(function () {
            handleIncentiveFormFields(this, idPrefix);
        });
    };

    showHideIncentiveFormFields();

    if ($('.incentive-form').find('div').hasClass('alert-danger')) {
        $('[href="#on_site_details"]').tab('show');
    }

    var incentivePanelCollapse = $('#incentive .panel-collapse');

    incentivePanelCollapse.on('show.bs.collapse', function () {
        $(this).siblings('.panel-heading').addClass('active');
    });

    incentivePanelCollapse.on('hide.bs.collapse', function () {
        $(this).siblings('.panel-heading').removeClass('active');
    });

    $('#incentive_cancel').on('click', function () {
       $('.incentive-form')[0].reset();
       showHideIncentiveFormFields();
    });

    $(".incentive-modify").on('click', function () {
        var url = $(this).data('href');
        var type = $(this).data('type');
        $('#incentive_modify_model .modal-body').html('Are you sure you want to ' + type + ' this incentive occurrence?');
        $('#incentive_modify_model #incentive-ok').data('href', url);
        $('#incentive_modify_model').modal('show');
    });

    $("#incentive-ok").on('click', function () {
        var incentiveEditModal = $('#incentive_edit_modal');
        var modelContent = $("#incentive_edit_modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load($(this).data('href'), function () {
            incentiveEditModal.modal('show');
        });
    });

    $('#incentive_edit_modal').on('shown.bs.modal', function () {
        showHideIncentiveFormFields('#incentive_edit_modal ');
        $("#incentive_edit_modal form").parsley();
    });
});
