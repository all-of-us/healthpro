const $ = require("jquery");
// Global jQuery since we still have page scripts that rely on jQuery being available as $
window.$ = $;

window.bootstrap = require("bootstrap5");
require("backbone/backbone.js");
window.tempusDominus = require("@eonasdan/tempus-dominus/dist/js/tempus-dominus.min");
require("parsleyjs/dist/parsley.js");
require("./parsley-validator.js"); // customized parsley validator
require("datatables.net/js/jquery.dataTables.js");
require("datatables.net-bs5/js/dataTables.bootstrap5.js");
require("datatables.net-responsive/js/dataTables.responsive.js");
require("datatables.net-responsive-bs/js/responsive.bootstrap.js");
require("jsbarcode/dist/barcodes/JsBarcode.code128.min.js");
require("inputmask/dist/jquery.inputmask.bundle.js");
require("bootstrap-toggle/js/bootstrap-toggle.js");
require("./bootstrap-session-timeout-bs5.js");
require("corejs-typeahead");
window.Bloodhound = require("corejs-typeahead/dist/bloodhound.js");

// Most views are separate webpack entries except for:
// Modals is needed on every page
require("./views/Modals.js");
// ModifyReasons is used on both order and PM modification forms. Could be included separately on both, but leaving as part of app for now
require("./views/ModifyReasons.js");

require("bootstrap5/dist/css/bootstrap.css");
require("@fortawesome/fontawesome-free/css/all.css");
require("@fortawesome/fontawesome-free/css/v4-shims.css");
require("@eonasdan/tempus-dominus/dist/css/tempus-dominus.min.css");
require("datatables.net-bs5/css/dataTables.bootstrap5.css");
require("datatables.net-responsive-bs/css/responsive.bootstrap.css");
require("bootstrap-toggle/css/bootstrap-toggle.css");
require("../css/app.css");
require("../css/bs5.css");

require("./global.js");
require("./bs5.js");
