$(document).ready(function () {
    $('#order_next_btn').on('click', function () {
        $('#order_create').hide();
        $('#order_review').show();

        $('.timepoint-samples').each(function () {
            let timePoint = $(this).data('timepoint');
            if (timePoint === 'preLMT' || timePoint === 'postLMT') {
                $(this).find('input:checkbox').each(function () {
                    if ($(this).prop('checked') === true) {
                        let sample = $(this).val();
                        $('#order_review_table tbody').append('<tr><td>' + timePoint + '</td><td>' + sample + '</td></tr>');
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
        $('#order_create').show();
        $('#order_review').hide();
    });

    $('#order_generate_btn').on('click', function () {
        $('#order_create_form').submit();
    });
});
