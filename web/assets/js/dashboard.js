/*
 * Dashboard scripts to run on every page in /dashboard
 */

var COLORBREWER_SET = ['rgb(166,206,227)','rgb(31,120,180)','rgb(178,223,138)','rgb(51,160,44)','rgb(251,154,153)',
    'rgb(227,26,28)', 'rgb(253,191,111)','rgb(255,127,0)','rgb(202,178,214)','rgb(106,61,154)','rgb(255,255,153)',
    'rgb(177,89,40)'];

var PLOTLY_OPTS = {
    modeBarButtonsToRemove: ['sendDataToCloud']
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
};

function stopSpinner(divId) {
    $('#' + divId).data('spinner').stop();
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
    alert('Error: cannot retrieve metrics');
    $("#" + div).html("<p class='lead text-danger text-center'>Metrics currently unavailable; please try again later.</p>");
}