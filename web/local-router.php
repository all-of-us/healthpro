<?php
// output syslog to standard error to match previous behavior of dev_appserver.py
openlog('HEALTHPRO', LOG_PERROR, LOG_USER);

if (preg_match('/^\\/(?:assets\\/|favicon\\.ico$|robots\\.txt$)/', $_SERVER["REQUEST_URI"])) {
    return false; // serve the requested resource as-is.
} else { 
    require 'index.php';
}
