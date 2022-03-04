$(document).ready(function () {

    let participantInfo = $('#participant_info');

    $('#order-overflow-show').on('click', function (e) {
        $(this).hide();
        $('#order-overflow').show();
        e.preventDefault();
    });

    $('#evaluation-overflow-show').on('click', function (e) {
        $(this).hide();
        $('#evaluation-overflow').show();
        e.preventDefault();
    });

    $('#problem-overflow-show').on('click', function (e) {
        $(this).hide();
        $('#problem-overflow').show();
        e.preventDefault();
    });

    if ($("#participant-barcode").length === 1) {
        JsBarcode("#participant-barcode", participantInfo.data('participant-id'), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }

    if (participantInfo.data('can-view-patient-status')) {

        let hasOrgPatientStatusData = participantInfo.data('has-org-patient-status');

        // Switch to default tab if empty
        if (!hasOrgPatientStatusData) {
            $('[href="#patientstatus"]').tab('show');
        }

        // Switch to patient status tab if there is a form error
        if ($('.patient-status-form').find('div').hasClass('alert-danger')) {
            $('[href="#patientstatus"]').tab('show');
            // Display form
            setTimeout(function () {
                $('.btn-patient-status-update').trigger('click');
            }, 100);
        }

        $('.patient-status-block').on('click', function () {
            $('[href="#patientstatus"]').tab('show');
        });

        // Hide form by default if not empty
        if (hasOrgPatientStatusData) {
            $('#patient-status-data-box').show();
            $('#patient-status-form-box').hide();
        }

        $('.btn-patient-status-update').on('click', function () {
            $('#patient-status-data-box').hide();
            $('#patient-status-form-box').show();
        });

        $('.btn-patient-status-cancel').on('click', function () {
            $('#patient-status-data-box').show();
            $('#patient-status-form-box').hide();
        });

        // Display history in modal window
        let psDetailsModal = $('#patient-status-details-modal');

        $(".patient-status-details").on('click', function (e) {
            e.preventDefault();
            $(psDetailsModal).removeData('bs.modal');
            // Load data from url
            $("#patient-status-details-modal .modal-content").load(
                $(this).attr('data-href')
            );
            $(psDetailsModal).modal('show');
        });

        $(psDetailsModal).on('hidden.bs.modal', function () {
            $("#patient-status-details-modal .modal-body").html('');
        });

        $('#incentive_incentive_date_given').pmiDateTimePicker({format: 'MM/DD/YYYY', maxDate: new Date().setHours(23,59,59,999), useCurrent: false});
    }
});
