$(document).ready(function () {
    $("table.datatable").each(function () {
        let pageLength = $(this).data("table-page-length") ? $(this).data("table-page-length") : 25;
        let order = $(this).data("table-order") ? $(this).data("table-order") : "desc";
        let orderColumn = $(this).data("table-order-column") ? $(this).data("table-order-column") : 0;
        let scrollX = $(this).data("table-scroll-x") ? $(this).data("table-scroll-x") : false;
        let columnDefs = $(this).data("table-column-defs") || {};
        $(this).DataTable({
            order: [[orderColumn, order]],
            scrollX: scrollX,
            pageLength: pageLength,
            columnDefs: [columnDefs]
        });
    });
});
