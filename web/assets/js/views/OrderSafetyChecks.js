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

    $('input:radio[name=donate]').on('change', function() {
        if ($(this).val() === 'yes') {
            hideFields(['transfusion', 'blood-info-text-2', 'saliva-info-text']);
            showFields(['order-info-text', 'blood-info-text', 'urine-info-text', 'continue']);
        } else {
            showFields(['transfusion']);
            hideFields(['transfusion-qn', 'red-blood-qn', 'order-info-text', 'continue']);
        }
    });

    $('input:radio[name=transfusion]').on('change', function() {
        if ($(this).val() === 'yes') {
            showFields(['transfusion-qn']);
            hideFields(['continue']);
        } else {
            hideFields(['transfusion-qn', 'red-blood-qn', 'order-info-text']);
            showFields(['continue']);
        }
    });

    $('input:radio[name=red_blood_qn]').on('change', function() {
        if ($(this).val() === 'yes') {
            hideFields(['order-info-text']);
            showFields(['continue']);
        } else {
            showFields(['order-info-text', 'blood-info-text-2', 'saliva-info-text', 'continue']);
            hideFields(['blood-info-text', 'urine-info-text']);
        }
    });

    $('input[type=checkbox]').on('change', function() {
        var transfusionWholeBlood = $('input:checkbox[name=transfusion_whole_blood]:checked').val();
        var transfusionRedBlood = $('input:checkbox[name=transfusion_red_blood]:checked').val();
        if (typeof(transfusionWholeBlood) !== 'undefined' || typeof(transfusionRedBlood) !== 'undefined') {
            if ((transfusionWholeBlood === 'whole') || (transfusionWholeBlood === 'whole' && transfusionRedBlood === 'red')) {
                hideFields(['red-blood-qn', 'blood-info-text-2', 'saliva-info-text']);
                showFields(['order-info-text', 'blood-info-text', 'urine-info-text', 'continue']);
            } else {
                showFields(['red-blood-qn']);
                hideFields(['order-info-text', 'blood-info-text', 'blood-info-text-2', 'urine-info-text', 'continue']);
            }
        } else {
            hideFields(['red-blood-qn', 'order-info-text', 'continue']);
        }
    });
});
