$(document).ready(function () {
    const dateTypes = [
        'measurement_created_ts',
        'measurement_finalized_ts',
        'order_created_ts',
        'order_collected_ts',
        'order_processed_ts',
        'order_finalized_ts',
        'incentive_created_ts',
        'idVerification_created_ts'
    ];

    // Display total count for each step in the date column headers
    for (const dateType of dateTypes) {
        $('#' + dateType).html($('[data-date-type=' + dateType + ']').length);
    }

    $('#form_start_date, #form_end_date, #review_today_filter_start_date, #review_today_filter_end_date')
        .pmiDateTimePicker({format: 'MM/DD/YYYY'});
});
