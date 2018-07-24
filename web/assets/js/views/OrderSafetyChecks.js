$(document).ready(function() {
    // Ignore non order check pages.
    if (!$('#safety-checks').length) {
      return;
    }

    var hideFields = function(fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).hide();
            $('#' + fields[i]).find('input:radio, input:checkbox').prop('checked', false);
        }
    };

    var showFields = function(fields) {
        for (var i = 0; i < fields.length; i++) {
            $('#' + fields[i]).show();
        }
    };

    var applyBranchingLogic = function() {
        if (!$('input:radio[name=donate]').is(':checked')) {
            return;
        }
        if ($('input:radio[name=donate]:checked').val() === 'yes') {
            hideFields(['transfusion', 'blood-info-text-2', 'saliva-info-text']);
            showFields(['order-info-text', 'blood-info-text', 'urine-info-text', 'continue']);
        } else {
            var isTransfusionChecked = $('input:radio[name=transfusion]:checked').val();
            var isTransfusionWholeBloodChecked = $('input:checkbox[name=transfusion_whole_blood]').is(':checked');
            var isTransfusionRedBloodChecked = $('input:checkbox[name=transfusion_red_blood]').is(':checked');
            var isRedBloodQnChecked = $('input:radio[name=red_blood_qn]').is(':checked');
            showFields(['transfusion']);
            var hideFieldNames = ['order-info-text', 'continue'];
            if (!isTransfusionWholeBloodChecked && !isTransfusionRedBloodChecked) {
                hideFieldNames.push('transfusion-qn');
            }
            if (!isRedBloodQnChecked) {
                hideFieldNames.push('red-blood-qn');
            }
            hideFields(hideFieldNames);
            if (isTransfusionChecked) {
                if ($('input:radio[name=transfusion]:checked').val() === 'yes') {
                    showFields(['transfusion-qn']);
                    hideFields(['continue']);
                    if (isTransfusionWholeBloodChecked || isTransfusionRedBloodChecked) {
                        var transfusionWholeBlood = $('input:checkbox[name=transfusion_whole_blood]:checked').val();
                        var transfusionRedBlood = $('input:checkbox[name=transfusion_red_blood]:checked').val();
                        if ((transfusionWholeBlood === 'whole') || (transfusionWholeBlood === 'whole' && transfusionRedBlood === 'red')) {
                            hideFields(['red-blood-qn', 'blood-info-text-2', 'saliva-info-text']);
                            showFields(['order-info-text', 'blood-info-text', 'urine-info-text', 'continue']);
                        } else {
                            showFields(['red-blood-qn']);
                            hideFields(['order-info-text', 'blood-info-text', 'blood-info-text-2', 'urine-info-text', 'continue']);
                            if (isRedBloodQnChecked) {
                                if ($('input:radio[name=red_blood_qn]:checked').val() === 'yes') {
                                    hideFields(['order-info-text']);
                                    showFields(['continue']);
                                } else {
                                    showFields(['order-info-text', 'blood-info-text-2', 'saliva-info-text', 'continue']);
                                    hideFields(['blood-info-text', 'urine-info-text']);
                                }
                            }
                        }
                    } else {
                        hideFields(['red-blood-qn', 'order-info-text', 'continue']);
                    }
                } else {
                    hideFields(['transfusion-qn', 'red-blood-qn', 'order-info-text']);
                    showFields(['continue']);
                }
            }
        }
    };

    // Display current branching logic state for broswer back clicks
    applyBranchingLogic();

    $('input[type=radio], input[type=checkbox]').on('change', function() {
        applyBranchingLogic();
    });
});
