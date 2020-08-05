require('bootstrap');
const $ = require('jquery');
const _ = require('underscore');
require('parsleyjs/dist/parsley.js');
require('./bootstrap-session-timeout.js');
const jstz = require('./jstz.min.js');

require('bootstrap/dist/css/bootstrap.css');
require('@fortawesome/fontawesome-free/css/all.css');
require('@fortawesome/fontawesome-free/css/v4-shims.css');
require('../css/app.css');

// merge these files and remove globals when migration from gulp is complete
window.$ = $;
window._ = _;
window.jstz = jstz;

require('./app.js');
