/*
 * Dashboard scripts to run on every page in /dashboard
 */

var PLOTLY_OPTS = {
    modeBarButtonsToRemove: ['sendDataToCloud']
};

var GEO_OPTS = {
    scope: 'usa',
    showlakes: true,
    lakecolor: 'rgb(255,255,255)'
};

// custom event to trigger resize event only after user has stopped resizing the window
$(window).resize(function() {
    if(this.resizeTO) clearTimeout(this.resizeTO);
    this.resizeTO = setTimeout(function() {
        $(this).trigger('resizeEnd');
        console.log('resizeEnd');
    }, 100);
});