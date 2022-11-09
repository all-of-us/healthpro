$(document).ready(function () {
    let orderCreateSelector = $('#order_create');
    let orderReviewSelector = $('#order_review');
    $('#order_next_btn').on('click', function () {
        orderCreateSelector.hide();
        orderReviewSelector.show();
        $('#order_review_table tbody').html('');
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
                            $('#nail_sub_samples').find('input:checkbox').each(function () {
                                if ($(this).prop('checked') === true) {
                                    nailSubSamples.push($(this).val());
                                }
                            });
                            if (nailSubSamples.length > 0) {
                                $('#order_review_table tbody').append(
                                    '<tr><td>' + timePoint + '</td><td>Nail: ' + nailSubSamples.join(',') + '</td></tr>'
                                );
                            }
                        } else if (sample === 'stool') {
                            let stoolKitSelector = $('#nph_order_stoolKit');
                            if (stoolKitSelector.val()) {
                                let stoolKitSamples = '';
                                stoolSamples.forEach(function (stoolSample) {
                                    let stoolInputSelector = $('#nph_order_' + stoolSample);
                                    if (stoolInputSelector.val()) {
                                        stoolKitSamples += ', ' + stoolSample + ': ' + stoolInputSelector.val();
                                    }
                                });
                                if (stoolKitSamples) {
                                    $('#order_review_table tbody').append(
                                        '<tr><td>' + timePoint + '</td><td>Stool: KIT ID ' + stoolKitSelector.val() + stoolKitSamples + '</td></tr>'
                                    );
                                }
                            }
                        } else if (!nailSamples.includes(sample)) {
                            $('#order_review_table tbody').append(
                                '<tr><td>' + timePoint + '</td><td>' + sample + '</td></tr>'
                            );
                        }
                    }
                });
            } else {
                let samples = [];
                $(this).find('input:checkbox').each(function () {
                    if ($(this).prop('checked') === true) {
                        samples.push($(this).val());
                    }
                });
                if (samples.length > 0) {
                    samples = samples.join(',');
                    $('#order_review_table tbody').append('<tr><td>' + timePoint + '</td><td>' + samples + '</td></tr>');
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
