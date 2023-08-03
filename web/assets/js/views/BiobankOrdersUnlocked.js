$(document).ready(function () {
    $("table").DataTable({
        order: [[$(".col-created").index(), "desc"]],
        pageLength: 25
    });
});
