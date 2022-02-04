$(document).ready(function () {
    // Ignore non order check pages.
    if (!$('#safety-checks').length) {
        return;
    }

    var isChecked = function (fieldName) {
        return $('input:radio[name='+fieldName+']').is(':checked')
    };

    var hideFields = function (fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).hide();
            $('#' + fields[i]).find('input:radio, input:checkbox').prop('checked', false);
            if (fields[i] === 'order-info-text') {
                $('#order-info-text').find('span').hide();
            }
        }
    };

    var showFields = function (fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).show();
        }
    };

    var allowCollection = function (type) {
        if (type === 'urine') {
            $('input[name=show-blood-tubes]').val('no');
            $('input[name=show-saliva-tubes]').val('no');
        } else if (type === 'saliva') {
            $('input[name=show-blood-tubes]').val('no');
            $('input[name=show-saliva-tubes]').val('yes');
        } else {
            $('input[name=show-blood-tubes]').val('yes');
            $('input[name=show-saliva-tubes]').val('yes');
        }
    };

    var handleStep5 = function (donate) {
        // #5 Show Question 5
        showFields(['ppc-qn']);
        hideFields(['continue', 'order-info-text']);
        var ppc = $('input:radio[name=ppc_qn]:checked').val();
        if (ppc === 'yes') {
            if (donate === 'no') {
                // #5 Answer syncope question and continue
                handleSyncopeQuestion();
            } else {
                // #5 Display info text 1 and continue
                showFields(['order-info-text', 'info-text-1', 'continue']);
                hideFields(['syncope-qn', 'info-text-5']);
                allowCollection('saliva');
            }
        } else if (ppc === 'no') {
            // #5 Display info text 3 and continue
            hideFields(['order-info-text', 'syncope-qn', 'info-text-5']);
            showFields(['continue', 'order-info-text', 'info-text-3']);
            allowCollection('saliva');
        }
    };

    var handleSyncopeQuestion = function () {
        showFields(['syncope-qn']);
        if (!isChecked('syncope')) {
            hideFields(['syncope-sub-qn']);
            return;
        }
        var syncope = $('input:radio[name=syncope]:checked').val();
        if (syncope === 'yes') {
            showFields(['syncope-sub-qn', 'order-info-text', 'info-text-5']);
            if (!isChecked('syncope_sub')) {
                return;
            }
            var syncopeSubQn = $('input:radio[name=syncope_sub]:checked').val();
            if (syncopeSubQn === 'yes') {
                allowCollection('all');
            } else {
                allowCollection('saliva');
            }
            showFields(['continue']);
        } else {
            hideFields(['syncope-sub-qn']);
            showFields(['continue']);
            allowCollection('all');
        }
    };

    var applyBranchingLogic = function () {
        // Continue only when both Q1 and Q2 are checked
        if (!isChecked('donate') || !isChecked('transfusion')) {
            return;
        }

        // Hide fields on initialization
        var hideFieldNames = ['order-info-text', 'continue'];
        if (!isChecked('rbc_qn')) {
            hideFieldNames.push('rbc-qn');
        }
        if (!isChecked('ppc_qn')) {
            hideFieldNames.push('ppc-qn');
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
                // #3 Display info text 4 and continue
                hideFields(['rbc-qn', 'ppc-qn', 'order-info-text', 'syncope-qn']);
                showFields(['continue', 'order-info-text', 'info-text-4']);
                allowCollection('urine');
            } else if (isTransfusionRBCChecked) {
                // #4 Show Question 4
                showFields(['rbc-qn']);
                hideFields(['continue', 'order-info-text']);
                var rbc = $('input:radio[name=rbc_qn]:checked').val();
                if (rbc === 'yes') {
                    if (!isTransfusionPPCChecked) {
                        hideFields(['order-info-text', 'ppc-qn']);
                        if (donate === 'no') {
                            // #4 Answer syncope question and continue
                            handleSyncopeQuestion();
                        } else {
                            // #4 Display info text 1 and continue
                            showFields(['order-info-text', 'info-text-1', 'continue']);
                            hideFields(['syncope-qn', 'info-text-5']);
                            allowCollection('saliva');
                        }
                    } else {
                        // #5 Handle step 5
                        handleStep5(donate);
                    }
                } else if (rbc === 'no') {
                    // #4 Display info text 2 and continue
                    hideFields(['ppc-qn', 'order-info-text', 'syncope-qn']);
                    showFields(['continue', 'order-info-text', 'info-text-2']);
                    allowCollection('saliva');
                } else {
                    // Hide PPC question if RBC question is not checked
                    hideFields(['ppc-qn']);
                }
            } else if (isTransfusionPPCChecked) {
                // #5 Handle step 5
                hideFields(['rbc-qn', 'syncope-qn']);
                handleStep5(donate);
            } else {
                // Hide both RBC and PPC questions if no transfusion type is checked
                hideFields(['rbc-qn', 'ppc-qn', 'syncope-qn']);
            }
        } else {
            hideFields(['transfusion-qn', 'rbc-qn', 'ppc-qn', 'order-info-text']);
            if (donate === 'no') {
                // #3 Answer syncope question and continue
                handleSyncopeQuestion();
            } else {
                // #3 Display info text 1 and continue
                hideFields(['syncope-qn', 'syncope-sub-qn', 'info-text-5']);
                showFields(['order-info-text', 'info-text-1', 'continue']);
                allowCollection('saliva');
            }
        }
    };

    // Display current branching logic state for browser back clicks
    applyBranchingLogic();

    $('input[type=radio], input[type=checkbox]').on('change', function () {
        applyBranchingLogic();
    });
});
