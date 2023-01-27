$(document).ready(function () {
    let html;
    if (PMI.isTimeZoneDiff) {
        html =
            '<div class="alert alert-warning tz-info">Your computer\'s time zone does not appear to match your HealthPro time zone preference. ';
        html +=
            '<button type="button" class="btn btn-primary tz-change-btn">Change to ' +
            PMI.timeZones[PMI.browserTimeZone] +
            "</button>";
        html += "</div>";
        $(".page-header").after(html);
    } else if (!PMI.userTimeZone && PMI.browserTimeZone && PMI.browserTimeZone in PMI.timeZones) {
        html =
            '<div class="alert alert-info tz-info"><p>It looks like you are in ' +
            PMI.timeZones[PMI.browserTimeZone] +
            ".</p>";
        html +=
            '<p><button type="button" class="btn btn-primary tz-change-btn">Set to ' +
            PMI.timeZones[PMI.browserTimeZone] +
            "</button>";
        html += " or select time zone below</p>";
        html += "</div>";
        $(".page-header").after(html);
    }
    $(".tz-change-btn").on("click", function () {
        $("#settings_timezone").val(PMI.browserTimeZone);
        $("form[name=settings]").submit();
    });
});
