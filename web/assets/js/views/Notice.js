$(document).ready(function () {
    $("table").DataTable({
        order: [[0, "asc"]],
        pageLength: 25
    });
});
