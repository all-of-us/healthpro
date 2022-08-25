$(document).ready(function () {
    $('#save_view').on( 'click', function () {
        $('#save_view_modal').modal('show');
    });

    $('#manage_views').on( 'click', function () {
        $('#manage_views_modal').modal('show');
    });

    $('.wq-view-edit').on( 'click', function () {
        let saveViewFormModal = $('#save_view_modal');
        let modelContent = $("#save_view_modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load($(this).data('href'), function () {
            saveViewFormModal.modal('show');
            $('#manage_views_modal').modal('hide');
        });
    });
});
