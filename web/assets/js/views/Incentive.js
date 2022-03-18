$(document).ready(function () {
    $('#incentive .panel-collapse').on('show.bs.collapse', function () {
        $(this).siblings('.panel-heading').addClass('active');
    });

    $('#incentive .panel-collapse').on('hide.bs.collapse', function () {
        $(this).siblings('.panel-heading').removeClass('active');
    });

    $(".incentive-modify").on('click', function (e) {
        e.preventDefault();
        var url = $(this).data('href');
        var type = $(this).data('type');
        $('#incentiveModifyModel .modal-body').html('Are you sure you want to ' + type + ' this incentive occurrence?');
        $('#incentiveModifyModel #incentive-ok').data('href', url);
        $('#incentiveModifyModel').modal('show');
    });

    $("#incentive-ok").on('click', function (e) {
        e.preventDefault();
        var incentiveEditModal = $('#incentive-edit-modal');
        var modelContent = $("#incentive-edit-modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load(
            $(this).data('href')
        );
        incentiveEditModal.modal('show');
    });
});
