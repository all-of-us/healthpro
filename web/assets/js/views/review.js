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
            if (data && data.lastName && data.firstName) {
                const a = $("<a>")
                    .attr("href", td.data("href"))
                    .text(data.lastName + ", " + data.firstName);
                td.append(a);
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
});
