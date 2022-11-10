$(document).ready(function () {
    let orderCreateSelector = $('#order_create');
    let orderReviewSelector = $('#order_review');

    let addTimePointSamples = function (timePoint, samples) {
        $('#order_review_table tbody').append(
            '<tr><td>' + timePoint + '</td><td>' + samples + '</td></tr>'
        );
    };

    $('#order_next_btn').on('click', function () {
        orderCreateSelector.hide();
        orderReviewSelector.show();
        $('#order_review_table tbody').html('');
        let samples = orderCreateSelector.data('samples');
        let timePoints = orderCreateSelector.data('time-points');
        let nailSamples = orderCreateSelector.data('nail-samples');
        let stoolSamples = orderCreateSelector.data('stool-samples');
        let samplesCount = 0;
        $('.timepoint-samples').each(function () {
            let timePoint = $(this).data('timepoint');
            if (timePoint === 'preLMT' || timePoint === 'postLMT') {
                $(this).find('input:checkbox').each(function () {
                    if ($(this).prop('checked') === true) {
                        let sample = $(this).val();
                        if (sample === 'nail') {
                            let nailSubSamples = [];
                            $('.nail-sub-samples').find('input:checkbox').each(function () {
                                if ($(this).prop('checked') === true) {
                                    nailSubSamples.push(samples[$(this).val()]);
                                    samplesCount++;
                                }
                            });
                            if (nailSubSamples.length > 0) {
                                addTimePointSamples(timePoints[timePoint], 'Nail: ' + nailSubSamples.join(', ') + '');
                            }
                        } else if (sample === 'stool') {
                            let stoolKitSelector = $('#nph_order_stoolKit');
                            if (stoolKitSelector.val()) {
                                let stoolKitSamples = '';
                                stoolSamples.forEach(function (stoolSample) {
                                    let stoolInputSelector = $('#nph_order_' + stoolSample);
                                    if (stoolInputSelector.val()) {
                                        stoolKitSamples += ', ' + samples[stoolSample] + ': ' + stoolInputSelector.val();
                                        samplesCount++;
                                    }
                                });
                                if (stoolKitSamples) {
                                    addTimePointSamples(timePoints[timePoint], 'Stool: KIT ID ' + stoolKitSelector.val() + stoolKitSamples + '');
                                }
                            }
                        } else if (!nailSamples.includes(sample)) {
                            addTimePointSamples(timePoints[timePoint], samples[sample]);
                            samplesCount++;
                        }
                    }
                });
            } else {
                let bloodSamples = [];
                $(this).find('input:checkbox').each(function () {
                    if ($(this).prop('checked') === true) {
                        bloodSamples.push(samples[$(this).val()]);
                        samplesCount++;
                    }
                });
                if (bloodSamples.length > 0) {
                    bloodSamples = bloodSamples.join(', ');
                    addTimePointSamples(timePoints[timePoint], 'Blood: ' + bloodSamples);
                }
            }
        });
        $('#samples_count').html(samplesCount);
    });

    $('#order_review_back_btn').on('click', function () {
        orderCreateSelector.show();
        orderReviewSelector.hide();
    });

    $('#order_generate_btn').on('click', function () {
        let confirmMessage = 'Are you sure you want to generate orders and print labels? ' +
            'This action will officially create the order and sample IDs. ' +
            'Click cancel to go back and edit timepoints/samples.' +
            'Click OK to create order(s) and print labels.';
        if (confirm(confirmMessage)) {
            $('#order_create_form').submit();
        }
    });
});
