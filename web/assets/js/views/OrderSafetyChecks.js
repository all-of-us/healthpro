$(document).ready(function() {
    // Ignore non order check pages.
    if (!$('#safety-checks').length) {
      return;
    }

    var hideFields = function(fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).hide();
            $('#' + fields[i]).find('input:radio, input:checkbox').prop('checked', false);
            if (fields[i] === 'order-info-text') {
                $('#order-info-text').find('span').hide();
            }
        }
    };

    var showFields = function(fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).show();
        }
    };

    var handleStep5 = function() {
        // #5 Show Question 5
        showFields(['ppc-qn']);
        hideFields(['continue', 'order-info-text']);
        var ppc = $('input:radio[name=ppc_qn]:checked').val();
        if (ppc === 'yes') {
            // #5 Continue with not restriction
            showFields(['continue']);
        } else if (ppc === 'no') {
            // #5 Display info text 3 and continue
            hideFields(['order-info-text']);
            showFields(['continue', 'order-info-text', 'info-text-3']);
        }
    };

    var applyBranchingLogic = function() {
        var isDonateChecked = $('input:radio[name=donate]').is(':checked');
        var isTransfusionChecked = $('input:radio[name=transfusion]').is(':checked');

        // Continue only when both Q1 and Q2 are checked
        if (!isDonateChecked || !isTransfusionChecked) {
            return;
        }

        // Hide fields on initialization
        var rbcChecked = $('input:radio[name=rbc_qn]').is(':checked');
        var pccChecked = $('input:radio[name=pcc_qn]').is(':checked');
        var hideFieldNames = ['order-info-text', 'continue'];
        if (!rbcChecked) {
            hideFieldNames.push('rbc-qn');
        }
        if (!pccChecked) {
            hideFieldNames.push('pcc-qn');
        }
        hideFields(hideFieldNames);

        var donate = $('input:radio[name=donate]:checked').val();
        var transfusion = $('input:radio[name=transfusion]:checked').val();
        if (transfusion === 'yes') {
            var isTransfusionWBChecked = $('input:checkbox[name=transfusion_wb]').is(':checked');
            var isTransfusionRBCChecked = $('input:checkbox[name=transfusion_rbc]').is(':checked');
            var isTransfusionPPCChecked = $('input:checkbox[name=transfusion_ppc]').is(':checked');
            // #3 Show Question 3
            showFields(['transfusion-qn']);
            hideFields(['continue']);
            if (isTransfusionWBChecked) {
                // #3 Display info text 1 and continue
                hideFields(['rbc-qn', 'ppc-qn', 'order-info-text']);
                showFields(['continue', 'order-info-text', 'info-text-4']);
            } else if (isTransfusionRBCChecked) {
                // #4 Show Question 4
                showFields(['rbc-qn']);
                hideFields(['continue', 'order-info-text']);
                var rbc = $('input:radio[name=rbc_qn]:checked').val();
                if (rbc === 'yes') {
                    if (!isTransfusionPPCChecked) {
                        // #4 Continue with not restriction
                        showFields(['continue']);
                        hideFields(['order-info-text', 'ppc-qn']);
                    } else {
                        // #5 Handle step 5
                        handleStep5();
                    }
                } else if (rbc === 'no') {
                    // #4 Display info text 2 and continue
                    hideFields(['ppc-qn', 'order-info-text']);
                    showFields(['continue', 'order-info-text', 'info-text-2']);
                }
            } else if (isTransfusionPPCChecked){
                // #5 Handle step 5
                handleStep5();
            }
        } else {
            hideFields(['transfusion-qn', 'red-blood-qn', 'order-info-text']);
            showFields(['continue']);
            if (donate === 'yes') {
                // #3 Display info text 1 and continue
                showFields(['order-info-text', 'info-text-1']);
            }
        }
    };

    // Display current branching logic state for browser back clicks
    applyBranchingLogic();

    $('input[type=radio], input[type=checkbox]').on('change', function() {
        applyBranchingLogic();
    });
});
