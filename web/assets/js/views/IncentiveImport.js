$(document).ready(function () {
    $('.incentive-import-status').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        searching: false,
        lengthChange: false
    });
});
