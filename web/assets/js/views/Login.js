const bootstrap = require("bootstrap5");
require("../../css/login.css");

$(document).ready(function () {
    document.querySelectorAll(".carousel").forEach((carouselEl) => {
        new bootstrap.Carousel(carouselEl, {
            interval: 5000
        });
    });
});
