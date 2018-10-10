$(document).ready(function() {
    // Ignore non-modify reasons pages.
    if (!$('#form_reason').length) {
        return;
    }

    $('#form_other_text').hide();

    var selected = $( "#form_reason option:selected").val();
    var other = $("#form_reason option:contains(Other)").val();
    if (other === selected) {
        $('#form_other_text').show();
    }

    $('#form_reason').on('change', function () {
        if ($(this).val() === other) {
            $('#form_other_text').show();
        } else {
            $('#form_other_text').hide();
        }
    });
});
