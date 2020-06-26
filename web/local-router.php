<?php
if (preg_match('#^/assets/#', $_SERVER["REQUEST_URI"])) {
    // always pass through for static assets
    return false;
} elseif (preg_match('/\\.pdf|csv|json$/', $_SERVER["REQUEST_URI"])) {
    // for non-asset PDF, CSV and JSON URLs, explicitly send to index.php controller to avoid
    // the dev server from assuming these are static assets
    require 'silex-index.php';
} else {
    // everything else can pass through, since index.php is the default controller
    return false;
}
