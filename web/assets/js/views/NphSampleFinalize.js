$(document).ready(function () {
    $('#sample_finalize_btn').on('click', function () {
        let confirmMessage = 'Are you sure you want to finalize this sample?';
        return confirm(confirmMessage);
    });

    $('.add-another-collection-widget').click(function (e) {
        let list = $($(this).attr('data-list-selector'));
        let aliquotId = list.data('aliquot-id');
        let aliquotUnits = list.data('aliquot-units');

        // Try to find the counter of the list or use the length of the list
        let counter = list.data('widget-counter') || list.children().length;

        // grab the prototype template and replace the "__name__" used in the id and name of the prototype
        let newCodeWidget = list.data('code-prototype').replace(/__name__/g, counter);
        let newTsWidget = list.data('ts-prototype').replace(/__name__/g, counter);
        let newVolumeWidget = list.data('volume-prototype').replace(/__name__/g, counter);

        // Increase the counter
        counter++;
        // And store it, the length cannot be used if deleting widgets is allowed
        list.data('widget-counter', counter);

        let newElem = $(list.attr('data-widget-tags')).html(
            '<td>' + newCodeWidget + '</td>' +
            '<td>' + newTsWidget + '</td>' +
            '<td>' + newVolumeWidget + '</td>' +
            '<td>' + aliquotUnits + '</td>' +
            '<td><i class="fa fa-eraser" role="button"></i> <i class="fa fa-trash" role="button"></i></td>'
        );

        $('.aliquots-row-' + aliquotId).last().after(newElem);
    });
});
