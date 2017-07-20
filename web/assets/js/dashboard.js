/*
 * Dashboard scripts to run on every page in /dashboard
 */

var PLOTLY_OPTS = {
    modeBarButtonsToRemove: ['sendDataToCloud']
};

var PLOTS_SHOWN = {
    'total-progress-nav': false,
    'participants-by-region-nav': false,
    'participants-by-lifecycle-nav': false
};

var GEO_OPTS = {
    scope: 'usa',
    projection: {
        type: 'albers usa'
    },
    showlakes: true,
    showland: true,
    lakecolor: 'rgb(255,255,255',
    landcolor: 'rgb(217, 217, 217)',
    subunitwidth: 1,
    countrywidth: 1,
    subunitcolor: 'rgb(255,255,255)',
    countrycolor: 'rgb(255,255,255)'
};

var PLOTLY_LABEL_FONT = {
    family: 'Helvetica Neue'
};

// toggle chevron glyphs on clicks
function toggleGlyph(el) {
    el.toggleClass('fa-plus fa-minus');
}

// custom event to trigger resize event only after user has stopped resizing the window
$(window).resize(function() {
    if(this.resizeTO) clearTimeout(this.resizeTO);
    this.resizeTO = setTimeout(function() {
        $(this).trigger('resizeEnd');
        console.log('resizeEnd');
    }, 100);
});

// options for Spin.js
var opts = {
    lines: 13 // The ~number of lines to draw
    , length: 56 // The length of each line
    , width: 14 // The line thickness
    , radius: 42 // The radius of the inner circle
    , scale: 1 // Scales overall size of the spinner
    , corners: 1 // Corner roundness (0..1)
    , color: '#000' // #rgb or #rrggbb or array of colors
    , opacity: 0.25 // Opacity of the lines
    , rotate: 0 // The rotation offset
    , direction: 1 // 1: clockwise, -1: counterclockwise
    , speed: 1 // Rounds per second
    , trail: 60 // Afterglow percentage
    , fps: 20 // Frames per second when using setTimeout() as a fallback for CSS
    , zIndex: 2e9 // The z-index (defaults to 2000000000)
    , className: 'spinner' // The CSS class to assign to the spinner
    , top: '50%' // Top position relative to parent
    , left: '50%' // Left position relative to parent
    , shadow: false // Whether to render a shadow
    , hwaccel: false // Whether to use hardware acceleration
    , position: 'absolute' // Element positioning
};

function launchSpinner(divId) {
    var target = $('#' + divId)[0];
    var spinner = new Spinner(opts).spin(target);
    $(target).data('spinner', spinner);
}

function stopSpinner(divId) {
    $('#' + divId).data('spinner').stop();
}

function removePlotlyLink(divId) {
    $('#' + divId + ' .plotlyjsicon').remove();
}

// function to transform plotly data object into array of annotations showing total
// value of stacked bar columns
function loadBarChartAnnotations(plotlyData, annotationsArray, interval) {
    var fontSize = 12;
    if (interval == 'weeks') {
        fontSize = 10;
    }
    for (var i = 0; i < plotlyData[0]['x'].length ; i++){
        var total = 0;
        plotlyData.map(function(el) {
            var c = parseInt(el['y'][i]);
            if (isNaN(c)) {
                c = 0;
            }
            total += c;
        });
        var annot = {
            x: plotlyData[0]['x'][i],
            y: total,
            text: total,
            xanchor: 'center',
            yanchor: 'bottom',
            showarrow: false,
            font: {
                size: fontSize
            }
        };
        annotationsArray.push(annot);
    }
    return annotationsArray;
}

// collect all checked recruitment centers filters
function loadRecruitmentFilters(id) {
    var centers = [];
    $('#' + id).find('.center-filter:checked').each(function() {
        centers.push($(this).val());
    });
    return centers;
}

// generic error handler for when metrics API doesn't respond with valid results
function setMetricsError(div) {
    stopSpinner(div);
    $("#" + div).html("<p class='lead text-danger text-center'>Metrics currently unavailable - either there is an error retrieving data or you requested dates/centers for which no data exists.<br/><br/>Please try again later.</p>");
}

// function to toggle all traces in a Plotly div
function togglePlotlyTraces(div) {
    var plotlyData = document.getElementById(div).data;
    var visibility = plotlyData[0].visible;

    // if visibility is undefined or true, that means it is visible and we want to set this to 'legendonly'
    // when visibility == 'legendonly', we can set this back to true to show all traces
    if( visibility === undefined || visibility === true) {
        visibility = 'legendonly';
    } else {
        visibility = true
    }

    Plotly.restyle(div, 'visible', visibility);
    // toggle class of toggle glyph
    $('#toggle-traces .toggle-switch').toggleClass('fa-toggle-on fa-toggle-off');

}

// function to assemble and html table of raw data from bar charts
function loadTableData(tableTarget, sourceData, colTitle) {
    tableTarget.empty();
    var tableId = tableTarget.attr('id');
    tableTarget.html("<table class='table table-striped table-condensed raw-data-table'><thead><tr id='" + tableId + "-headers'><th>" + colTitle + "</th></tr></thead><tbody id='" + tableId + "-body'></tbody></table>");
    var headerRow = $('#' + tableId + '-headers');
    $(sourceData[0]['x']).each(function(i, date) {
        headerRow.append("<th>" + date + "</th>");
    });
    headerRow.append("</tr>");

    // load scores
    var tableBody = $('#' + tableId + '-body');
    $($(sourceData).get().reverse()).each(function(index, row) {
        var newRow = "<tr><td>" + row['name'] + "</td>";
        $(row['y']).each(function(i, count) {
            newRow += "<td>" + count + "</td>";
        });
        newRow += "</tr>";
        tableBody.append(newRow);
    });
}

// function to assemble and html table of raw data from geo data
function loadRegionTableData(tableTarget, sourceData, plotType, rowLabel) {
    tableTarget.empty();
    var tableId = tableTarget.attr('id');
    tableTarget.html("<table class='table table-striped table-condensed raw-data-table'><thead><tr id='" + tableId + "-headers'><th></th></tr></thead><tbody id='" + tableId + "-body'></tbody></table>");
    if (plotType === 'FullParticipant.state') {
        var headerRow = $('#' + tableId + '-headers');
        $(sourceData[0]['locations']).each(function (i, state) {
            headerRow.append("<th>" + state + "</th>");
        });
        headerRow.append("</tr>");

        // load scores
        var tableBody = $('#' + tableId + '-body');
        $($(sourceData).get().reverse()).each(function (index, row) {
            var newRow = "<tr><td>" + rowLabel + " totals</td>";
            $(row['z']).each(function (i, count) {
                newRow += "<td>" + count + "</td>";
            });
            newRow += "</tr>";
            tableBody.append(newRow);
        });
    } else if (plotType === 'FullParticipant.censusRegion') {
        var headerRow = $('#' + tableId + '-headers');
        $(sourceData[0]['regions']).each(function (i, region) {
            headerRow.append("<th>" + region + "</th>");
        });
        headerRow.append("</tr>");
        // load scores
        var tableBody = $('#' + tableId + '-body');
        var countRow = "<tr><td>" + rowLabel + " totals</td>";
        $(sourceData[0]['counts']).each(function (i, count) {
            countRow += "<td>" + count + "</td>";
        });
        countRow += "</tr>";
        tableBody.append(countRow);
    } else if (plotType === 'FullParticipant.hpoId') {
        var headerRow = $('#' + tableId + '-headers');
        $($(sourceData).get().reverse()).each(function(index, row) {
            var loc = row['name'];
            headerRow.append("<th>" + loc + "</th>");
        });
        headerRow.append("</tr>");

        // load scores
        var tableBody = $('#' + tableId + '-body');
        var countRow = "<tr><td>" + rowLabel + " totals</td>";
        $($(sourceData).get().reverse()).each(function(index, row) {
            countRow += "<td>" + row['count'] + "</td>";
        });
        countRow += "</tr>";
        tableBody.append(countRow);
    }

}