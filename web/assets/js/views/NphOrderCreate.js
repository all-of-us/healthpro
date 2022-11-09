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
                                    }
                                });
                                if (stoolKitSamples) {
                                    addTimePointSamples(timePoints[timePoint], 'Stool: KIT ID ' + stoolKitSelector.val() + stoolKitSamples + '');
                                }
                            }
                        } else if (!nailSamples.includes(sample)) {
                            addTimePointSamples(timePoints[timePoint], samples[sample]);
                        }
                    }
                });
            } else {
                let bloodSamples = [];
                $(this).find('input:checkbox').each(function () {
                    if ($(this).prop('checked') === true) {
                        bloodSamples.push(samples[$(this).val()]);
                    }
                });
                if (bloodSamples.length > 0) {
                    bloodSamples = bloodSamples.join(', ');
                    addTimePointSamples(timePoints[timePoint], 'Blood: ' + bloodSamples);
                }
            }
        });
    });

    $('#order_review_back_btn').on('click', function () {
        orderCreateSelector.show();
        orderReviewSelector.hide();
    });

    $('#order_generate_btn').on('click', function () {
        $('#order_create_form').submit();
    });
});
