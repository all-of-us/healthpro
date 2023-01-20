$(document).ready(function () {
    $("#problem_problem_date").pmiDateTimePicker();
    $("#problem_provider_aware_date").pmiDateTimePicker();
    $("#comment-overflow-show").on("click", function (e) {
        $(this).hide();
        $("#comment-overflow").show();
        e.preventDefault();
    });
    $("#problem_reports").DataTable({
        order: [],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: -1 }]
    });
});
