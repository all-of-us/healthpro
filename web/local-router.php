<?php
if (preg_match('/^\\/(?:assets\\/|favicon\\.ico$|robots\\.txt$)/', $_SERVER["REQUEST_URI"])) {
    return false; // serve the requested resource as-is.
} else { 
    require 'index.php';
}
