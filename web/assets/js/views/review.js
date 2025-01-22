$(document).ready(function () {
    const reviewTable = $(".table-review");
    const nameLookupUrl = reviewTable.data("name-lookup-url");
    const missingName = reviewTable.data("missing-name");

    let namesLoaded = 0;

    let loadNameSelector = $(".load-name");

    if (loadNameSelector.length > 0) {
        $("#export_btn").prop("disabled", true);
    }

    loadNameSelector.each(function () {
        const td = $(this);
        $.getJSON(nameLookupUrl + td.data("participant-id"), function (data) {
            td.empty();
            if (data) {
                const { lastName, firstName, isPediatric } = data;
                if (lastName && firstName) {
                    const a = $("<span>").text(lastName + ", " + firstName);
                    td.append(a);
                }
                if (isPediatric) {
                    td.parent().find(".participant-id").append(' <i class="fa fa-child child-icon"></i>');
                }
            } else {
                td.text(missingName);
            }
        })
            .fail(function () {
                td.html("<em>Error loading name</em>");
            })
            .always(function () {
                namesLoaded++;
                if (namesLoaded === $(".load-name").length) {
                    $("#export_btn").prop("disabled", false);
                }
            });
    });

    $(
        "#form_start_date, #form_end_date, #review_today_filter_start_date, #review_today_filter_end_date"
    ).pmiDateTimePicker({ format: "MM/DD/YYYY" });
});
