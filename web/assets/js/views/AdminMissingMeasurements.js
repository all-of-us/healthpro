$(document).ready(function () {
    $("table").DataTable({
        order: [[0, "desc"]],
        pageLength: 25
    });
});
