$(document).ready(function () {
    $('#save_view').on('click', function () {
        $('#save_view_modal').modal('show');
    });

    $('#manage_views').on('click', function () {
        $('#manage_views_modal').modal('show');
    });

    $('.wq-view-edit').on('click', function () {
        let editViewFormModal = $('#edit_view_modal');
        let modelContent = $("#edit_view_modal .modal-content");
        modelContent.html('');
        // Load data from url
        modelContent.load($(this).data('href'), function () {
            editViewFormModal.modal('show');
        });
    });

    $(".wq-view-delete").on('click', function () {
        let viewId = $(this).data('id');
        $('#work_queue_view_delete_id').val(viewId);
        $('#view_delete_modal').modal('show');
    });

    if ($('.more-views ul li').hasClass('active')) {
        $('.more-views').addClass('active');
    }

    let triggerChangeEvent = true;

    $('.default-view-status').change(function () {
        if (triggerChangeEvent) {
            let url = $(this).data('url');
            let viewId = $(this).data('id');
            let isChecked = $(this).prop('checked');
            $.ajax({
                url: url,
                data: {
                    checked: isChecked
                }
            }).done(function () {
                triggerChangeEvent = false;
                if (isChecked) {
                    $('.default-view-status').not('#default_view_status_' + viewId).bootstrapToggle('off');
                }
                triggerChangeEvent = true;
            });
        }
    });
});
