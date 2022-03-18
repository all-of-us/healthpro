$(document).ready(function () {
    $('#incentive .panel-collapse').on('show.bs.collapse', function () {
        $(this).siblings('.panel-heading').addClass('active');
    });

    $('#incentive .panel-collapse').on('hide.bs.collapse', function () {
        $(this).siblings('.panel-heading').removeClass('active');
    });

    $(".incentive-modify").on('click', function (e) {
        e.preventDefault();
        var url = $(e.currentTarget).data('href');
        var type = $(e.currentTarget).data('type');
        $('#incentiveModifyModel .modal-body').html('Are you sure you want to ' + type + ' this incentive occurance?');
        $('#incentiveModifyModel .incentive-ok').attr('href', url);
        $('#incentiveModifyModel').modal('show');
    });
});
