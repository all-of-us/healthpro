$(document).ready(function () {
    const todayTable = $('#table-today');
    const nameLookupUrl = todayTable.data('name-lookup-url');
    const missingName = todayTable.data('missing-name');

    $('.load-name').each(function () {
        const td = $(this);
        $.getJSON(nameLookupUrl + td.data('participant-id'), function (data) {
            td.empty();
            if (data && data.lastName && data.firstName) {
                const a = $('<a>')
                    .attr('href', td.data('href'))
                    .text(data.lastName + ', ' + data.firstName);
                td.append(a);
            } else {
                td.text(missingName);
            }
        })
            .fail(function () {
                td.html('<em>Error loading name</em>');
            });
    });

    const dateTypes = ['evaluation_created_ts', 'evaluation_finalized_ts', 'order_created_ts', 'order_collected_ts', 'order_processed_ts', 'order_finalized_ts'];

    // Display total count for each step in the date column headers
    for (const dateType of dateTypes) {
        $('#' + dateType).html($('[data-date-type=' + dateType + ']').length);
    }

    $('#form_start_date, #form_end_date').pmiDateTimePicker('MM/DD/YYYY');
});
