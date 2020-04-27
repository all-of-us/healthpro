<?php

$url = @parse_url($_SERVER['REQUEST_URI'])['path'];

if (preg_match("/^\/s(\/|$)/", $url)) {
    require '../symfony/public/index.php';
} else {
    require 'silex-index.php';
}
