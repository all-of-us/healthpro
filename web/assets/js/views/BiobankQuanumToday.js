$(document).ready(function () {
    $("table").DataTable({
        order: [[$(".col-created").index(), "desc"]],
        searching: false,
        pageLength: 25
    });
});
