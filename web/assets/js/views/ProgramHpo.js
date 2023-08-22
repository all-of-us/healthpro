$(document).ready(function () {
    // Add badge for count of Deceased Participant Reports for review
    if ($("#deceased_report_block").length) {
        var deceasedReportCount = 0;
        $.ajax({
            url: "/deceased-reports/stats"
        }).done(function (data) {
            deceasedReportCount = data.pending;
            if (deceasedReportCount > 0) {
                $("#deceased_report_block > a").prepend(
                    $('<div class="welcome-page-badge">' + deceasedReportCount + "</div>")
                );
            }
        });
    }
});
