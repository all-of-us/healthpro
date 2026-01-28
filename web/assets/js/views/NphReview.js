$(document).ready(function () {
    const reviewTable = $(".table-review");
    const nameLookupUrl = reviewTable.data("name-lookup-url");
    const missingName = reviewTable.data("missing-name");

    let participantIds = [];
    $(".load-name").each(function () {
        const td = $(this);
        if (participantIds.indexOf(td.data("participant-id")) === -1) {
            participantIds.push(td.data("participant-id"));
        }
    });

    $(participantIds).each(function (index, element) {
        const td = $(`.load-name[data-participant-id="${element}"]`);
        $.getJSON(nameLookupUrl + element, function (data) {
            td.empty();
            td.siblings(".load-biobankid").empty();
            if (data && data.lastName && data.firstName) {
                const a = $("<a>")
                    .attr("href", td.data("href"))
                    .text(data.lastName + ", " + data.firstName);
                const b = $("<p>").text(data.biobankid);
                td.append(a);
                td.siblings(".load-biobankid").append(b);
            } else {
                td.text(missingName);
                td.siblings(".load-biobankid").text(missingName);
            }
        }).fail(function () {
            td.html("<em>Error loading name</em>");
            td.siblings(".load-biobankid").html("<em>Error loading ID</em>");
        });
    });

    $("#search").on("keyup", function () {
        const value = $(this).val().toLowerCase();
        $("#table-today tbody tr").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
