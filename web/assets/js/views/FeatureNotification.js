$(document).ready(function () {
    $('#feature_notification_start_ts, #feature_notification_end_ts').pmiDateTimePicker();
    $('#feature_notification_url').dropdownOther({
        'Home Page': '/',
        'Work Queue': '/workqueue/',
        'Management Tools': '/access/manage/dashboard',
        'User Management': '/access/manage/user/groups',
        'Participant Review': '/review',
        'On-Site Details Reporting': '/on-site/incentive-tracking',
        'Help and Training Resources': '/help'
    });
    $('.confirm').on('click', function () {
        return confirm('Are you sure you want to delete this notification?');
    });
    $('table').DataTable({
        order: [[0, 'asc']],
        pageLength: 25
    });
});
