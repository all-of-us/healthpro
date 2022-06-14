$(document).ready(function () {
    $('.id-verification-import-status').DataTable({
        order: [[1, 'desc']],
        pageLength: 25,
        searching: false,
        lengthChange: false,
        columnDefs: [
            {orderable: false, targets: 3}
        ]
    });
});
