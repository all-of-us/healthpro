$(document).ready(function() {
    // Ignore non-workqeue pages.
    if (!$('#workqueue').length) {
      return;
    }

    var buttonGroups = {
        'Default': 'default',
        'Consent': 'consent',
        'PPI Surveys': 'surveys',
        'In-Person': 'enrollment',
        'Demographics': 'demographics',
        'Patient Status': 'status',
        'Contact': 'contact',
        'Metrics': 'metrics',
        'Show all': 'all'
    };

    var exportLimit = $('#workqueue').data('export-limit');

    var workQueueExportWarningModel = function (location) {
        var exportLimitFormatted = exportLimit;
        if (window.Intl && typeof window.Intl === 'object') {
            exportLimitFormatted = new Intl.NumberFormat().format(exportLimit);
        }
        new PmiConfirmModal({
            title: 'Warning',
            msg: 'Note that the export reaches the limit of ' + exportLimitFormatted + ' participants. If your intent was to capture all participants, you may need to apply filters to ensure each export is less than ' + exportLimitFormatted + ' or utilize the Ops Data API. Please contact <em>sysadmin@pmi-ops.org</em> for more information.',
            isHTML: true,
            onTrue: function () {
                window.location = location;
            },
            btnTextTrue: 'Ok'
        });
    };

    $('button.export').on('click', function () {
        var location = $(this).data('href');
        var count = parseInt($('.count').html());
        new PmiConfirmModal({
            title: 'Attention',
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function () {
                if (count > exportLimit) {
                    workQueueExportWarningModel(location);
                } else {
                    window.location = location;
                }
            },
            btnTextTrue: 'Accept'
        });
    });

    var url = window.location.href;

    var wQColumns = $('#workqueue').data('wq-columns');
    var columnsDef = $('#workqueue').data('columns-def');
    var isDvType = $('#workqueue').data('dv-type');

    var tableColumns = [];

    var generateTableRow = function (field, columnDef) {
        var row = {};
        row.name = field;
        row.data = field;
        if (columnDef.hasOwnProperty('htmlClass')) {
            row.class = columnDef['htmlClass'];
        }
        if (columnDef.hasOwnProperty('orderable')) {
            row.class = columnDef['orderable'];
        }
        if (columnDef.hasOwnProperty('visible')) {
            row.visible = columnDef['visible'];
        }
        if (columnDef.hasOwnProperty('checkDvVisibility')) {
            row.visible = !!isDvType;
        }
        tableColumns.push(row);
    };

    wQColumns.forEach(function (field) {
        var columnDef = columnsDef[field];
        if (columnDef.hasOwnProperty('names')) {
            Object.keys(columnDef['names']).forEach(function (key) {
                generateTableRow(key + 'Consent', columnDef);
            });
        } else {
            generateTableRow(field, columnDef);
        }
    });

    var table = $('#workqueue').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[12, 'desc']],
        dom: 'lBrtip',
        columns: tableColumns,
        pageLength: 25,
        drawCallback: function () {
            var pageInfo = table.page.info();
            $('.total-pages').text(pageInfo.pages);
            var dropDownHtml = '';
            for (var count = 1; count <= pageInfo.pages; count++) {
                var pageNumber = count - 1;
                dropDownHtml += '<option value="' + pageNumber + '">' + count + '</option>';
            }
            var pageDropDown = $('.page-drop-down select');
            pageDropDown.html(dropDownHtml);
            pageDropDown.val(pageInfo.page);
        },
        createdRow: function (row, data) {
            if (data.isWithdrawn === true) {
                $(row).addClass('tr-withdrawn');
            }
        },
        buttons: [
            {
                extend: 'colvisGroup',
                text: 'Default',
                show: [
                    '.col-group-default'
                ],
                hide: [
                    '.col-group-info:not(.col-group-default)',
                    '.col-group-ppi:not(.col-group-default)',
                    '.col-group-inperson:not(.col-group-default)',
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status',
                    '.col-group-metrics'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Consent',
                show: [
                    '.col-group-consent'
                ],
                hide: [
                    '.col-group-info:not(.col-group-consent)',
                    '.col-group-default:not(.col-group-consent)',
                    '.col-group-ppi',
                    '.col-group-inperson:not(.col-group-default)',
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status',
                    '.col-group-metrics'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'PPI Surveys',
                show: [
                    'dateOfBirth:name',
                    '.col-group-ppi'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-inperson',
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status',
                    '.col-group-metrics'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'In-Person',
                show: [
                    'dateOfBirth:name',
                    '.col-group-inperson'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-ppi',
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status',
                    '.col-group-metrics'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Demographics',
                show: [
                    'dateOfBirth:name',
                    '.col-group-demographics'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-inperson',
                    '.col-group-ppi',
                    '.col-group-contact',
                    '.col-group-patient-status',
                    '.col-group-metrics'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Patient Status',
                show: [
                    'dateOfBirth:name',
                    '.col-group-default',
                    '.col-group-patient-status',
                    '.col-group-ehr-expire-status',
                    '.col-group-metrics-ehr'
                ],
                hide: [
                    '.col-group-demographics',
                    '.col-group-info:not(.col-group-default, .col-group-ehr-expire-status)',
                    '.col-group-inperson',
                    '.col-group-ppi',
                    '.col-group-contact',
                    '.col-group-metrics:not(.col-group-metrics-ehr)'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Contact',
                show: [
                    'dateOfBirth:name',
                    '.col-group-default',
                    '.col-group-contact',
                    '.col-group-retention'
                ],
                hide: [
                    '.col-group-demographics',
                    '.col-group-info:not(.col-group-default)',
                    '.col-group-inperson',
                    '.col-group-ppi',
                    '.col-group-patient-status',
                    '.col-group-metrics:not(.col-group-retention)',
                    '.col-group-consent-cohort',
                    '.col-group-program-update',
                    '.col-group-language-primary-consent',
                    '.col-group-ehr-expire-status',
                    '.col-group-consent'

                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Metrics',
                show: [
                    'dateOfBirth:name',
                    '.col-group-default',
                    '.col-group-ehr-expire-status',
                    '.col-group-metrics'
                ],
                hide: [
                    '.col-group-demographics',
                    '.col-group-info:not(.col-group-default, .col-group-ehr-expire-status)',
                    '.col-group-inperson',
                    '.col-group-ppi',
                    '.col-group-patient-status',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Show all',
                show: ':hidden'
            }
        ]
    });

    $('.page-drop-down select').change(function () {
        table.page(parseInt($(this).val())).draw('page');
    });

    // Populate count in header
    $('#workqueue').on('init.dt', function (e, settings, json) {
        var count = json.recordsFiltered;
        $('#heading-count .count').text(count);
        if (count == 1) {
            $('#heading-count .plural').hide();
        } else {
            $('#heading-count .plural').show();
        }
        $('#heading-count').show();
    });

    table.buttons().container().find('.btn').addClass('btn-sm');
    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue').on('error.dt', function (e) {
        alert('An error occurred please reload the page and try again');
    });

    // Scroll to top when performing pagination
    $('#workqueue').on('page.dt', function () {
        //Took reference from https://stackoverflow.com/a/21627503
        $('html').animate({
            scrollTop: $('#filters').offset().top
        }, 'slow');
        $('thead tr th:first-child').trigger('focus').trigger('blur');
    });

    var columnsUrl = $('#columns_group').data('columns-url');

    $('.toggle-vis').on('click', function () {
        var column = table.column($(this).attr('data-column'));
        column.visible(!column.visible());
        var columnName = $(this).attr('name');
        // Set column names in session
        $.get(columnsUrl, {columnName: columnName, checked: $(this).prop('checked')});
    });

    var toggleColumns = function () {
        $('#columns_group input[type=checkbox]').each(function () {
            var column = table.column($(this).attr('data-column'));
            column.visible($(this).prop('checked'));
        });
    };

    toggleColumns();

    var showColumns = function () {
        var columns = table.columns();
        columns.visible(true);
    };

    var hideColumns = function () {
        for (let i = 3; i <= 80; i++) {
            var column = table.column(i);
            column.visible(false);
        }
    };

    $('#columns_select_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', true);
        showColumns();
        $.get(columnsUrl, {select: true});
    });

    $('#columns_deselect_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', false);
        hideColumns();
        $.get(columnsUrl, {deselect: true});
    });

    // Check/uncheck columns when clicked on group buttons
    $('#workqueue').on('column-visibility.dt', function (e, settings, column, state) {
        if (column >= 3) {
            $('#toggle_column_' + column).prop('checked', state);
        }
    });

    // Handle button groups
    table.on('buttons-action', function (e, buttonApi) {
        var groupName = buttonGroups[buttonApi.text()];
        var params;
        if (groupName === 'all') {
            params = {select: true};
        } else {
            params = {groupName: groupName};
        }
        $.get(columnsUrl, params);
    });
});
