$(document).ready(function () {
    $('#feature_notification_start_ts, #feature_notification_end_ts').pmiDateTimePicker();
    $('#feature_notification_url').dropdownOther({
        'All': '/*',
        'Home Page': '/',
        'Work Queue': '/workqueue/',
        'Biobank Order Pages': '/participant/*/order/*',
        'Physical Measurements': '/participant/*/measurements*',
        'Management Tools': '/access/manage/dashboard',
        'User Management': '/access/manage/user/groups'
    });
    $('.confirm').on('click', function () {
        return confirm('Are you sure you want to delete this notification?');
    });
    $('table').DataTable({
        order: [[0, 'asc']],
        pageLength: 25
    });
});
