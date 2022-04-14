$(document).ready(function () {
    $("#incentive_create form").parsley();

    var setIncentiveDateGiven = function () {
        $('.incentive-date-given').pmiDateTimePicker({
            format: 'MM/DD/YYYY',
            maxDate: new Date().setHours(23, 59, 59, 999),
            useCurrent: false
        });
    };

    var incentivePrefix = 'incentive_';

    var handleIncentiveFormFields = function (that, idPrefix = '#incentive_create ') {
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
                $(idPrefix + ' #gift_card').show();
                $(giftCardFieldSelector).attr('required', 'required');
            } else {
                $(idPrefix + ' #gift_card').hide();
                $(giftCardFieldSelector).val('');
                $(giftCardFieldSelector).removeAttr('required');
            }
            var incentiveAmountSelector = idPrefix + '#' + incentivePrefix + 'incentive_amount';
            var otherIncentiveAmountSelector = idPrefix + '#' + incentivePrefix + 'other_incentive_amount';
            if ($(that).val() === 'promotional') {
                $(incentiveAmountSelector).val('');
                $(incentiveAmountSelector).attr('disabled', 'disabled');
                $(incentiveAmountSelector).removeAttr('required');
                $(otherIncentiveAmountSelector).parent().hide();
                $(otherIncentiveAmountSelector).val('');
                $(otherIncentiveAmountSelector).removeAttr('required');
            } else {
                $(incentiveAmountSelector).removeAttr('disabled');
                $(incentiveAmountSelector).attr('required', 'required');
            }
        }
    };

    var showHideIncentiveFormFields = function (idPrefix = '#incentive_create ') {
        var incentiveFormSelect = $(idPrefix + ' select');

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

    $('#incentive_cancel').on('click', function () {
        $('#incentive_create .incentive-form')[0].reset();
        showHideIncentiveFormFields();
    });

    $(".incentive-amend").on('click', function () {
        var url = $(this).data('href');
        $('#incentive_amend_ok').data('href', url);
        $('#incentive_amend_modal').modal('show');
    });

    $(".incentive-remove").on('click', function () {
        var incentiveId = $(this).data('id');
        $('#incentive_remove_id').val(incentiveId);
        $('#incentive_remove_modal').modal('show');
    });

    $("#incentive_amend_ok").on('click', function () {
        var amendButton = $(this).button('loading');
        var incentiveEditFormModal = $('#incentive_edit_form_modal');
        var modelContent = $("#incentive_edit_form_modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load($(this).data('href'), function () {
            $('#incentive_amend_modal').modal('hide');
            amendButton.button('reset');
            incentiveEditFormModal.modal('show');
        });
    });

    /* Gift card search */
    var getGiftCards = new Bloodhound({
        name: 'giftCardType',
        datumTokenizer: Bloodhound.tokenizers.whitespace,
        queryTokenizer: Bloodhound.tokenizers.whitespace,
        limit: 10,
        prefetch: '/ajax/search/giftcard-prefill',
        remote: {
            url: '/ajax/search/giftcard/%QUERY',
            wildcard: '%QUERY'
        }
    });

    var handleGiftCardAutoPopulate = function (idPrefix = '#incentive_create ') {
        $(idPrefix + ' .gift-card').typeahead({
                highlight: true
            },
            {
                source: getGiftCards
            });
    };

    var incentiveEditModal = '#incentive_edit_form_modal';

    $(incentiveEditModal).on('shown.bs.modal', function () {
        showHideIncentiveFormFields('#incentive_edit ');
        $("#incentive_edit form").parsley();
        setIncentiveDateGiven();
    });

    $(incentiveEditModal).on('hidden.bs.modal', function () {
        $("#patient-status-details-modal .modal-content").html('');
    });

    setIncentiveDateGiven();
    handleGiftCardAutoPopulate();
});
