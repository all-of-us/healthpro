$(document).ready(function () {
    $("table").DataTable({
        order: [[0, "asc"]],
        pageLength: 25,
        columnDefs: [{ targets: 2, orderable: false }]
    });
});
