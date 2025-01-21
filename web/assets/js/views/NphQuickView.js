$(document).ready(function () {
    // Display samples quick view in modal window
    let quickViewModal = $("#quick_view_modal");

    $(".quick-view-btn").on("click", function (e) {
        e.preventDefault();
        $(quickViewModal).removeData("bs.modal");

        // Show loading spinner in modal
        let modalContent = $("#quick_view_modal .modal-content");
        modalContent.html(
            '<div class="text-center" style="padding: 50px;"><i class="fa fa-spinner fa-spin fa-3x fa-fw text-primary"></i><span class="sr-only">Loading...</span></div>'
        );

        // Load data from url
        modalContent.load($(this).attr("data-href"), function () {
            // Hide the loading spinner after the content is loaded
            $(this).find(".fa-spinner").remove();

            // Initialize DataTable
            initializeDataTable();
        });

        // Show modal
        $(quickViewModal).modal("show");
    });

    const initializeDataTable = () => {
        $("table.quick-view-table").DataTable({
            order: [[8, "desc"]],
            pageLength: 1000,
            lengthMenu: [[1000], [1000]], // Disable the entries dropdown
            searching: false,
            paging: false,
            info: false,
            columnDefs: [
                {
                    targets: [0, 1, 2, 3, 4, 5, 6, 7],
                    orderable: false
                }
            ]
        });
    };
});
