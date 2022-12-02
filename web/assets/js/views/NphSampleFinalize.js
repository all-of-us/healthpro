$(document).ready(function () {
    $('#sample_finalize_btn').on('click', function () {
        let confirmMessage = 'Are you sure you want to finalize this sample?';
        return confirm(confirmMessage);
    });
});
