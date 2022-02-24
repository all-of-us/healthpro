$(document).ready(function () {
    var columnsDef = $('#workqueue').data('columns-def');
    var columns = $('#workqueue').data('wq-columns');

    var clearInvalidFields = function (type = null) {
        if (type !== 'participantIdSearch') {
            var dateOfBirthField = $('#dateOfBirth');
            if (dateOfBirthField.parsley().validate() !== true) {
                dateOfBirthField.val('');
            }
        }
        columns.forEach(function (field) {
            var columnDef = columnsDef[field];
            if (columnDef['toggleColumn'] && columnDef.hasOwnProperty('rdrDateField')) {
                var starDateField = $('#' + columnDef['rdrDateField'] + 'StartDate');
                if (starDateField.length !== 0) {
                    var endDateField = $('#' + columnDef['rdrDateField'] + 'EndDate');
                    if (starDateField.parsley().validate() !== true) {
                        starDateField.val('');
                    }
                    if (endDateField.parsley().validate() !== true) {
                        endDateField.val('');
                    }
                }
            }
        });
    };

    var checkFilters = function () {
        if ($('#filters select[name=activityStatus]').val() == 'withdrawn') {
            $('#filters select').not('[name=activityStatus], [name=organization]').val('');
            $('#filters select').not('[name=activityStatus], [name=organization]').prop('disabled', true);
        } else {
            $('#filters select').prop('disabled', false);
        }
    };

    checkFilters();
    $('#filters select, #filters input[type=radio]').not('.page-drop-down select').on('change', function () {
        checkFilters();
        $('#filters').submit();
    });


    $('#filters #participant_search').on('click', function () {
        var isValidLastName = $('#lastName').parsley().validate();
        var isValidDateOfBirth = $('#dateOfBirth').parsley().validate();
        if (isValidLastName === true && isValidDateOfBirth === true) {
            $('input[name=participantId]').val('');
            checkFilters();
            clearInvalidFields();
            $('#filters').submit();
        }
    });

    $('#filters #participant_id_search').on('click', function () {
        var isValidParticipantId = $('#participantId').parsley().validate();
        if (isValidParticipantId === true) {
            $('input[name=lastName], input[name=dateOfBirth]').val('');
            checkFilters();
            clearInvalidFields('participantIdSearch');
            $('#filters').submit();
        }
    });

    var isValidStartEndDate = function (dateFieldName) {
        var isValidStartDate = $('#' + dateFieldName + 'StartDate').parsley().validate();
        var isValidEndDate = $('#' + dateFieldName + 'EndDate').parsley().validate();
        return isValidStartDate === true && isValidEndDate === true;
    };

    $('#filters .apply-date-filter').on('click', function () {
        var dateFieldName = $(this).data('consent-date-field-name');
        if (isValidStartEndDate(dateFieldName) && ($('input[name=' + dateFieldName + 'StartDate]').val() !== '' || $('input[name=' + dateFieldName + 'EndDate]').val() !== '')) {
            checkFilters();
            clearInvalidFields();
            $('#filters').submit();
        }
    });

    $('#participant_lookup_reset').on('click', function () {
        $('#participant_lookup_group input[type=text]').val('');
        checkFilters();
        $('#filters').submit();
    });

    $('#filter_status_reset').on('click', function () {
        $('#filter_status_group input[type=text]').val('');
        $('#filter_status_group input[type=radio][value=""]').prop('checked', true);
        checkFilters();
        $('#filters').submit();
    });

    $('.filter-sub-group-reset').on('click', function () {
        var groupId = $(this).data('group-id');
        $('#' + groupId + ' input[type=text]').val('');
        $('#' + groupId + ' input[type=radio][value=""]').prop('checked', true);
        checkFilters();
        $('#filters').submit();
    });

    $('.date-filter-reset').on('click', function () {
        var groupId = $(this).data('group-id');
        $('#' + groupId + ' input[type=text]').val('');
        checkFilters();
        $('#filters').submit();
    });

    var validateDateFormat = function (value) {
        var parts = value.split('/');
        if (parts.length < 3) {
            return false;
        }
        var dt = new Date(parts[2], parts[0] - 1, parts[1]);
        return (dt && dt.getMonth() === parseInt(parts[0], 10) - 1 && dt.getFullYear() === parseInt(parts[2]));
    };

    window.Parsley.addValidator('dateMdy', {
        validateString: function (value) {
            return validateDateFormat(value);
        },
        messages: {
            en: 'Invalid date format.'
        },
        priority: 32
    });

    $('ul.dropdown-menu [data-toggle=dropdown]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $(this).parent().siblings().removeClass('open');
        $(this).parent().toggleClass('open');
    });

    $('#columns_group ul, #participant_lookup_group ul, #filter-sub-group').on('click', function () {
        event.stopPropagation();
    });

    $('.date-filter').pmiDateTimePicker({format: 'MM/DD/YYYY', useCurrent: false});

    $('#dateOfBirth').inputmask("99/99/9999");
});
