$(document).ready(function () {
    // Ignore non-workqeue pages.
    if (!$('#workqueue_consents').length) {
        return;
    }

    var columnsDef = $('#workqueue_consents').data('columns-def');

    var checkFilters = function () {
        if ($('#filters select[name=activityStatus]').val() == 'withdrawn') {
            $('#filters select').not('[name=activityStatus], [name=organization]').val('');
            $('#filters select').not('[name=activityStatus], [name=organization]').prop('disabled', true);
        } else {
            $('#filters select').prop('disabled', false);
        }
    };

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
        tableColumns.push(row);
    };

    for (const [field, columnDef] of Object.entries(columnsDef)) {
        if (columnDef.hasOwnProperty('displayNames')) {
            Object.keys(columnDef['displayNames']).forEach(function (key, _i) {
                generateTableRow(key + 'Consent', columnDef);
            });
        } else {
            generateTableRow(field, columnDef);
        }
    }

    var url = window.location.href;

    var workQueueTable = $('#workqueue_consents').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        fixedHeader: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[5, 'desc']],
        dom: 'lrtip',
        columns: tableColumns,
        pageLength: 25,
        drawCallback: function () {
            var pageInfo = workQueueTable.page.info();
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
        }
    });

    $('.page-drop-down select').change(function (e) {
        workQueueTable.page(parseInt($(this).val())).draw('page');
    });

    var showColumns = function () {
        var columns = workQueueTable.columns();
        columns.visible(true);
    };

    var hideColumns = function () {
        for (let i = 5; i <= 16; i++) {
            var column = workQueueTable.column(i);
            column.visible(false);
        }
    };

    var showStatusIndicator = function () {
        $('.filter-group').each(function () {
            var id = $(this).attr('id');
            $('#' + id + ' input[type=radio]:checked').each(function () {
                if (this.value) {
                    $('#' + id).find('button').addClass('btn-primary');
                    return false;
                }
            });
            $('#' + id + ' input[type=text]').each(function () {
                if (this.value) {
                    $('#' + id).find('button').addClass('btn-primary');
                    return false;
                }
            });
            $('#' + id + ' .dropdown-submenu').each(function () {
                $(this).find('input[type=radio]:checked').each(function () {
                    if (this.value) {
                        $(this).closest('.dropdown-menu').siblings('a').addClass('active');
                        return false;
                    }
                });
                $(this).find('input[type=text]').each(function () {
                    if (this.value) {
                        $(this).closest('.dropdown-menu').siblings('a').addClass('active');
                        return false;
                    }
                });
            });
        });

        $('#participant_lookup_group input[type=text]').each(function () {
            if (this.value) {
                $('#participant_lookup_group').find('button').addClass('btn-primary');
                return false;
            }
        });
    };

    var clearInvalidFields = function (type = null) {
        if (type !== 'participantIdSearch') {
            var dateOfBirthField = $('#dateOfBirth');
            if (dateOfBirthField.parsley().validate() !== true) {
                dateOfBirthField.val('');
            }
        }
        for (const columnDef of Object.values(columnsDef)) {
            if (columnDef['toggleColumn'] && columnDef.hasOwnProperty('rdrDateField')) {
                var starDateField = $('#' + columnDef['rdrDateField'] + 'StartDate');
                if (starDateField.length !== 0) {
                    var endDateField = $('#' + columnDef['rdrDateField'] + 'EndDate');
                    if (starDateField.parsley().validate() !== true) {
                        starDateField.val('');
                    }
                    if (endDateField.parsley().validate() !== true) {
                        endDateField.val('');
                    }
                }
            }
        }
    };

    checkFilters();
    showStatusIndicator();
    $('#filters select, #filters input[type=radio]').not('.page-drop-down select').on('change', function () {
        checkFilters();
        $('#filters').submit();
    });


    $('#filters #participant_search').on('click', function () {
        var isValidLastName = $('#lastName').parsley().validate();
        var isValidDateOfBirth = $('#dateOfBirth').parsley().validate();
        if (isValidLastName === true && isValidDateOfBirth === true) {
            $('input[name=participantId]').val('');
            checkFilters();
            clearInvalidFields();
            $('#filters').submit();
        }
    });

    $('#filters #participant_id_search').on('click', function () {
        var isValidParticipantId = $('#participantId').parsley().validate();
        if (isValidParticipantId === true) {
            $('input[name=lastName], input[name=dateOfBirth]').val('');
            checkFilters();
            clearInvalidFields('participantIdSearch');
            $('#filters').submit();
        }
    });

    var isValidStartEndDate = function (dateFieldName) {
        var isValidStartDate = $('#' + dateFieldName + 'StartDate').parsley().validate();
        var isValidEndDate = $('#' + dateFieldName + 'EndDate').parsley().validate();
        return isValidStartDate === true && isValidEndDate === true;
    };

    $('#filters .apply-date-filter').on('click', function () {
        var dateFieldName = $(this).data('consent-date-field-name');
        if (isValidStartEndDate(dateFieldName) && ($('input[name=' + dateFieldName + 'StartDate]').val() !== '' || $('input[name=' + dateFieldName + 'EndDate]').val() !== '')) {
            checkFilters();
            clearInvalidFields();
            $('#filters').submit();
        }
    });

    $('#columns_select_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', true);
        showColumns();
        $.get("/workqueue/consent/columns", {select: true});
    });

    $('#columns_deselect_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', false);
        hideColumns();
        $.get("/workqueue/consent/columns", {deselect: true});
    });

    $('#participant_lookup_reset').on('click', function () {
        $('#participant_lookup_group input[type=text]').val('');
        checkFilters();
        $('#filters').submit();
    });

    $('#filter_status_reset').on('click', function () {
        $('#filter_status_group input[type=text]').val('');
        $('#filter_status_group input[type=radio][value=""]').prop('checked', true);
        checkFilters();
        $('#filters').submit();
    });

    $('.filter-sub-group-reset').on('click', function () {
        var groupId = $(this).data('group-id');
        $('#' + groupId + ' input[type=text]').val('');
        $('#' + groupId + ' input[type=radio][value=""]').prop('checked', true);
        checkFilters();
        $('#filters').submit();
    });

    $('.date-filter-reset').on('click', function () {
        var groupId = $(this).data('group-id');
        $('#' + groupId + ' input[type=text]').val('');
        checkFilters();
        $('#filters').submit();
    });

    var exportLimit = $('#workqueue_consents').data('export-limit');

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

    // Populate count in header
    $('#workqueue_consents').on('init.dt', function (e, settings, json) {
        var count = json.recordsFiltered;
        $('#heading-count .count').text(count);
        if (count == 1) {
            $('#heading-count .plural').hide();
        } else {
            $('#heading-count .plural').show();
        }
        $('#heading-count').show();
    });

    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue_consents').on('error.dt', function (e) {
        alert('An error occurred please reload the page and try again');
    });

    // Scroll to top when performing pagination
    $('#workqueue_consents').on('page.dt', function () {
        //Took reference from https://stackoverflow.com/a/21627503
        $('html').animate({
            scrollTop: $('#filters').offset().top
        }, 'slow');
        $('thead tr th:first-child').trigger('focus').trigger('blur');
    });

    $('.toggle-vis').on('click', function () {
        var column = workQueueTable.column($(this).attr('data-column'));
        column.visible(!column.visible());
        var columnName = $(this).attr('name');
        // Set column names in session
        var consentColumnsUrl = $('#columns_group').data('consent-columns-url');
        $.get(consentColumnsUrl, {columnName: columnName, checked: $(this).prop('checked')});
    });

    var toggleColumns = function () {
        $('#columns_group input[type=checkbox]').each(function () {
            var column = workQueueTable.column($(this).attr('data-column'));
            column.visible($(this).prop('checked'));
        });
    };

    toggleColumns();

    var validateDateFormat = function (value) {
        var parts = value.split('/');
        if (parts.length < 3) {
            return false;
        }
        var dt = new Date(parts[2], parts[0] - 1, parts[1]);
        return (dt && dt.getMonth() === parseInt(parts[0], 10) - 1 && dt.getFullYear() === parseInt(parts[2]));
    };

    window.Parsley.addValidator('dateMdy', {
        validateString: function (value) {
            return validateDateFormat(value);
        },
        messages: {
            en: 'Invalid date format.'
        },
        priority: 32
    });

    $('ul.dropdown-menu [data-toggle=dropdown]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $(this).parent().siblings().removeClass('open');
        $(this).parent().toggleClass('open');
    });

    $('#columns_group ul, #participant_lookup_group ul, #filter-sub-group').on('click', function () {
        event.stopPropagation();
    });

    $('.date-filter').pmiDateTimePicker({format: 'MM/DD/YYYY', useCurrent: false});
});
