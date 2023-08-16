$(document).ready(function () {
    let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    window.bs5DateTimepicker = (element, params = []) => {
        const options = {
            useCurrent: false,
            display: {
                icons: {
                    type: "icons",
                    time: "fa fa-clock",
                    date: "fa fa-calendar",
                    up: "fa fa-arrow-up",
                    down: "fa fa-arrow-down",
                    previous: "fa fa-chevron-left",
                    next: "fa fa-chevron-right",
                    today: "fa fa-calendar-check",
                    clear: "fa fa-trash",
                    close: "fa fa-times"
                }
            }
        };
        if (params.hasOwnProperty("format")) {
            options.localization = {
                format: params["format"]
            };
        }
        if (params.hasOwnProperty("maxDate")) {
            options.restrictions = {
                maxDate: params["maxDate"]
            };
        }
        if (params.hasOwnProperty("clock")) {
            options.display.components = {
                clock: params["clock"]
            };
        }
        new tempusDominus.TempusDominus(element, options);
    };
});
