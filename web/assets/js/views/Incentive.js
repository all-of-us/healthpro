$(document).ready(function () {
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
        } else {
            $(otherFieldSelector).parent().hide();
            $(otherFieldSelector).val('');
        }
        if (selectFieldId === 'incentive_type') {
            var giftCardFieldSelector = idPrefix + '#' + incentivePrefix + 'gift_card_type';
            if ($(that).val() === 'gift_card') {
                $(giftCardFieldSelector).parent().show();
            } else {
                $(giftCardFieldSelector).parent().hide();
                $(giftCardFieldSelector).val('');
            }
        }
    };

    var incentiveFormSelect = $('#incentive select');

    incentiveFormSelect.each(function () {
        handleIncentiveFormFields(this);
    });

    incentiveFormSelect.change(function () {
        handleIncentiveFormFields(this);
    });

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

    $(".incentive-modify").on('click', function () {
        var url = $(this).data('href');
        var type = $(this).data('type');
        $('#incentiveModifyModel .modal-body').html('Are you sure you want to ' + type + ' this incentive occurrence?');
        $('#incentiveModifyModel #incentive-ok').data('href', url);
        $('#incentiveModifyModel').modal('show');
    });

    $("#incentive-ok").on('click', function () {
        var incentiveEditModal = $('#incentive-edit-modal');
        var modelContent = $("#incentive-edit-modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load($(this).data('href'), function () {
            incentiveEditModal.modal('show');
        });
    });

    $('#incentive-edit-modal').on('shown.bs.modal', function () {
        var editIncentiveFormSelect = $('#incentive-edit-modal select');
        editIncentiveFormSelect.each(function () {
            handleIncentiveFormFields(this, '#incentive-edit-modal ');
        });
        editIncentiveFormSelect.change(function () {
            handleIncentiveFormFields(this, '#incentive-edit-modal ');
        });
    });
});
