$(document).ready(function () {
    let measurement = $("#physicalEvaluation");

    new PMI.views['PhysicalEvaluation-' + measurement.data('schema-template')]({
        el: $("#physicalEvaluation"),
        warnings: measurement.data('warnings'),
        conversions: measurement.data('conversions'),
        finalized: measurement.data('finalized')
    });
    $('#evaluationAffixSave')
        .affix({
            offset: {
                top: 100,
                bottom: $(window).height()
            }
        })
        .width($('#physicalEvaluation').width());
});
