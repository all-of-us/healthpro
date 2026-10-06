$(document).ready(function () {
    // Switch tab to active step
    bootstrap.Tab.getOrCreateInstance($(".nav-tabs a[href='#{{ currentStep }}']")[0]).show();
});
