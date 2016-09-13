/*
 * Dashboard scripts to run on every page in /dashboard
 */

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