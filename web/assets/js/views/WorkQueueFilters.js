$(document).ready(function () {
    $('.toggle-vis').on('click', function () {
        var column = workQueueTable.column($(this).attr('data-column'));
        column.visible(!column.visible());
        var columnName = $(this).attr('name');
        // Set column names in session
        var consentColumnsUrl = $('#columns_group').data('consent-columns-url');
        $.get(consentColumnsUrl, {columnName: columnName, checked: $(this).prop('checked')});
    });

    var toggleColumns = function () {
        $('#columns_group input[type=checkbox]').each(function () {
            var column = workQueueTable.column($(this).attr('data-column'));
            column.visible($(this).prop('checked'));
        });
    };

    toggleColumns();

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
