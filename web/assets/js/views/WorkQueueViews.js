$(document).ready(function () {
    let saveViewNameCheck = function () {
        $(".save-view-btn").on("click", function () {
            let $this = $(this);
            let url = $(this).data("url");
            let formId = $(this).data("form-id");
            let inputNameSelector = $("#" + formId + ' input[name="work_queue_view[name]"]');
            let isValidName = inputNameSelector.parsley().validate();
            if (isValidName !== true) {
                return false;
            }
            let viewName = inputNameSelector.val();
            $this.prop("disabled", true);
            $.ajax({
                url: url,
                data: {
                    name: viewName
                }
            })
                .done(function (data) {
                    let viewNameErrorSelector = $(".view-name-error");
                    viewNameErrorSelector.remove();
                    if (data.status) {
                        inputNameSelector.after(
                            '<p class="view-name-error text-danger">Name has already been used.</p>'
                        );
                        $this.prop("disabled", false);
                    } else {
                        viewNameErrorSelector.remove();
                        $("#" + formId).submit();
                    }
                })
                .fail(function () {
                    $this.prop("disabled", false);
                });
        });
    };

    $("#save_view").on("click", function () {
        $("#save_view_modal").modal("show");
    });

    $("#manage_views").on("click", function () {
        $("#manage_views_modal").modal("show");
    });

    $(".wq-view-edit").on("click", function () {
        let editViewFormModal = $("#edit_view_modal");
        let modelContent = $("#edit_view_modal .modal-content");
        modelContent.html("");
        // Load data from url
        modelContent.load($(this).data("href"), function () {
            editViewFormModal.modal("show");
            saveViewNameCheck();
        });
    });

    $(".wq-view-delete").on("click", function () {
        let viewId = $(this).data("id");
        $("#work_queue_view_delete_id").val(viewId);
        $("#view_delete_modal").modal("show");
    });

    if ($(".more-views ul li").hasClass("active")) {
        $(".more-views").addClass("active");
    }

    let triggerChangeEvent = true;

    $(".default-view-status").change(function () {
        let defaultViewStatus = $(".default-view-status");
        if (triggerChangeEvent) {
            defaultViewStatus.bootstrapToggle("disable");
            let url = $(this).data("url");
            let viewId = $(this).data("id");
            let isChecked = $(this).prop("checked");
            $.ajax({
                url: url,
                data: {
                    checked: isChecked
                }
            })
                .done(function () {
                    defaultViewStatus.bootstrapToggle("enable");
                    triggerChangeEvent = false;
                    if (isChecked) {
                        defaultViewStatus.not("#default_view_status_" + viewId).bootstrapToggle("off");
                    }
                    triggerChangeEvent = true;
                })
                .fail(function () {
                    defaultViewStatus.bootstrapToggle("enable");
                });
        }
    });

    saveViewNameCheck();
});
