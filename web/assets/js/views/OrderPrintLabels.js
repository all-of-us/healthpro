$(document).ready(function () {
    $('#labels-loaded').hide();
    $('iframe[name=labels]').on('load', function () {
        try {
            if (typeof window.labels.print === 'function') {
                $('#labels-loading').hide();
                $('#labels-loaded').show();
            } else {
                $('#labels-loading-widget').hide();
            }
        } catch (e) {
            // catch firefox issue where pdf.js plugin makes the pdf frame cross-origin
            // https://github.com/mozilla/pdf.js/issues/5397
            $('#labels-loading-widget').hide();
        }
    });
});
