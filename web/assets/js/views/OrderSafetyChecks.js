$(document).ready(function () {
    // Ignore non order check pages.
    if (!$('#safety-checks').length) {
        return;
    }

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

    var handleStep5 = function (donate, syncope, infoText1, infoText3) {
        // #5 Show Question 5
        showFields(['ppc-qn']);
        hideFields(['continue', 'order-info-text']);
        var ppc = $('input:radio[name=ppc_qn]:checked').val();
        if (ppc === 'yes') {
            if (donate === 'no' && syncope !== 'yes') {
                // #5 Continue with no restriction
                showFields(['continue']);
                allowCollection('all');
            } else {
                // #5 Display info text 1 and continue
                showFields(['order-info-text', infoText1, 'continue']);
                allowCollection('saliva');
            }
        } else if (ppc === 'no') {
            // #5 Display info text 3 and continue
            hideFields(['order-info-text']);
            showFields(['continue', 'order-info-text', infoText3]);
            allowCollection('saliva');
        }
    };

    var applyBranchingLogic = function () {
        var isSyncopeChecked = $('input:radio[name=syncope]').is(':checked');
        var isDonateChecked = $('input:radio[name=donate]').is(':checked');
        var isTransfusionChecked = $('input:radio[name=transfusion]').is(':checked');

        // Continue only when both Q1, Q2, and Q3 are checked
        if (!isSyncopeChecked || !isDonateChecked || !isTransfusionChecked) {
            return;
        }

        // Hide fields on initialization
        var isRBCChecked = $('input:radio[name=rbc_qn]').is(':checked');
        var isPPCChecked = $('input:radio[name=ppc_qn]').is(':checked');
        var hideFieldNames = ['order-info-text', 'continue'];
        if (!isRBCChecked) {
            hideFieldNames.push('rbc-qn');
        }
        if (!isPPCChecked) {
            hideFieldNames.push('ppc-qn');
        }
        hideFields(hideFieldNames);

        var syncope = $('input:radio[name=syncope]:checked').val();
        // Takes precedent over other warning messages expect blood transfusion (info-text-4)
        var infoText1 = syncope === 'yes' ? 'info-text-0' : 'info-text-1';
        var infoText2 = syncope === 'yes' ? 'info-text-0' : 'info-text-2';
        var infoText3 = syncope === 'yes' ? 'info-text-0' : 'info-text-3';

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
                hideFields(['rbc-qn', 'ppc-qn', 'order-info-text']);
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
                        if (donate === 'no' && syncope !== 'yes') {
                            // #4 Continue with no restriction
                            showFields(['continue']);
                            allowCollection('all');
                        } else {
                            // #4 Display info text 1 and continue
                            showFields(['order-info-text', infoText1, 'continue']);
                            allowCollection('saliva');
                        }
                    } else {
                        // #5 Handle step 5
                        handleStep5(donate, syncope, infoText1, infoText3);
                    }
                } else if (rbc === 'no') {
                    // #4 Display info text 2 and continue
                    hideFields(['ppc-qn', 'order-info-text']);
                    showFields(['continue', 'order-info-text', infoText2]);
                    allowCollection('saliva');
                } else {
                    // Hide PPC question if RBC question is not checked
                    hideFields(['ppc-qn']);
                }
            } else if (isTransfusionPPCChecked) {
                // #5 Handle step 5
                hideFields(['rbc-qn']);
                handleStep5(donate, syncope, infoText1, infoText3);
            } else {
                // Hide both RBC and PPC questions if no transfusion type is checked
                hideFields(['rbc-qn', 'ppc-qn']);
            }
        } else {
            if (syncope === 'yes') {
                hideFields(['transfusion-qn', 'rbc-qn', 'ppc-qn']);
                showFields(['order-info-text', 'continue', 'info-text-0']);
                allowCollection('saliva');
            } else {
                // #3 Continue with no restriction
                hideFields(['transfusion-qn', 'rbc-qn', 'ppc-qn', 'order-info-text']);
                showFields(['continue']);
                allowCollection('all');
            }
            if (donate === 'yes') {
                // #3 Display info text 1 and continue
                showFields(['order-info-text', infoText1]);
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
