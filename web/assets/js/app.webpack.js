const $ = require('jquery');
require('bootstrap');
const _ = require('underscore');
require('backbone/backbone.js');
require('eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js');
require('parsleyjs/dist/parsley.js');
require('./parsley-comparison.js'); // customized parsley validator
require('datatables.net/js/jquery.dataTables.js');
require('datatables.net-bs/js/dataTables.bootstrap.js');
require('datatables.net-responsive/js/dataTables.responsive.js');
require('datatables.net-responsive-bs/js/responsive.bootstrap.js');
require('datatables.net-buttons/js/dataTables.buttons.js');
require('datatables.net-buttons/js/buttons.colVis.js');
require('datatables.net-buttons-bs/js/buttons.bootstrap.js');
require('jsbarcode/dist/barcodes/JsBarcode.code128.min.js');
require('inputmask/dist/jquery.inputmask.bundle.js');
require('bootstrap-toggle/js/bootstrap-toggle.js');
require('./bootstrap-session-timeout.js');
const jstz = require('./jstz.min.js');

// Moved most of the views to separate entries. Leaving Modals here since it is needed on every page.
require('./views/Modals.js');
// ModifyReasons is used on both order and PM modification forms. Could be included separately on both, but leaving as a global for now.
require('./views/ModifyReasons.js');

require('bootstrap/dist/css/bootstrap.css');
require('@fortawesome/fontawesome-free/css/all.css');
require('@fortawesome/fontawesome-free/css/v4-shims.css');
require('eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css');
require('datatables.net-bs/css/dataTables.bootstrap.css');
require('datatables.net-responsive-bs/css/responsive.bootstrap.css');
require('datatables.net-buttons-bs/css/buttons.bootstrap.css');
require('bootstrap-toggle/css/bootstrap-toggle.css');
require('../css/app.css');

// merge these files and possibly remove some globals when migration from gulp is complete
window.$ = $;
window._ = _;
window.jstz = jstz;

require('./app.js');
