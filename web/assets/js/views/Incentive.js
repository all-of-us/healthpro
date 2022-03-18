$(document).ready(function () {
    $('#incentive .panel-collapse').on('show.bs.collapse', function () {
        $(this).siblings('.panel-heading').addClass('active');
    });

    $('#incentive .panel-collapse').on('hide.bs.collapse', function () {
        $(this).siblings('.panel-heading').removeClass('active');
    });
});
