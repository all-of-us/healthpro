$(document).ready(function () {
    $("table").DataTable({
        order: [[$(".col-modified").index(), "desc"]],
        pageLength: 25
    });
});
