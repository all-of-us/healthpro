const $ = require("jquery");
// Global jQuery since we still have page scripts that rely on jQuery being available as $
window.$ = $;

require("bootstrap");
require("backbone/backbone.js");
require("eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js");
require("parsleyjs/dist/parsley.js");
require("./parsley-validator.js"); // customized parsley validator
require("datatables.net/js/jquery.dataTables.js");
require("datatables.net-bs/js/dataTables.bootstrap.js");
require("datatables.net-responsive/js/dataTables.responsive.js");
require("datatables.net-responsive-bs/js/responsive.bootstrap.js");
require("datatables.net-buttons/js/dataTables.buttons.js");
require("datatables.net-buttons/js/buttons.colVis.js");
require("datatables.net-buttons-bs/js/buttons.bootstrap.js");
require("jsbarcode/dist/barcodes/JsBarcode.code128.min.js");
require("inputmask/dist/jquery.inputmask.bundle.js");
require("bootstrap-toggle/js/bootstrap-toggle.js");
require("./bootstrap-session-timeout.js");
require("corejs-typeahead");
window.Bloodhound = require("corejs-typeahead/dist/bloodhound.js");

// Include JSZip (needed for CSV export)
require("jszip/dist/jszip.js");
// Include the required file for exporting to CSV
require("datatables.net-buttons/js/buttons.html5.js");

// Most views are separate webpack entries except for:
// Modals is needed on every page
require("./views/Modals.js");
// ModifyReasons is used on both order and PM modification forms. Could be included separately on both, but leaving as part of app for now
require("./views/ModifyReasons.js");

require("bootstrap/dist/css/bootstrap.css");
require("@fortawesome/fontawesome-free/css/all.css");
require("@fortawesome/fontawesome-free/css/v4-shims.css");
require("eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css");
require("datatables.net-bs/css/dataTables.bootstrap.css");
require("datatables.net-responsive-bs/css/responsive.bootstrap.css");
require("datatables.net-buttons-bs/css/buttons.bootstrap.css");
require("bootstrap-toggle/css/bootstrap-toggle.css");
require("../css/app.css");

require("./global.js");
