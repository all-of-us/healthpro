$(document).ready(function () {
    $('input:radio').on('change', function () {
        if ($('input:radio[name=full-withdrawn]').is(':checked')) {
            if ($('input:radio[name=full-withdrawn]:checked').val() === 'no') {
                $('#deceased_check_continue').show();
                $('#deceased_check_warning').hide();
            } else {
                $('#deceased_check_warning').show();
                $('#deceased_check_continue').hide();
            }
        }
    });
});
