$(document).ready(function () {
    $("table").DataTable({
        scrollX: true,
        order: [[1, "asc"]],
        pageLength: 25
    });
});
