#!/bin/bash

# install composer dependencies
composer install

# install App Engine SDK
cd ~
wget https://storage.googleapis.com/appengine-sdks/featured/google_appengine_1.9.57.zip
unzip -q google_appengine_1.9.57.zip

# patch php_cli.py to make our CI environment variables available
patch google_appengine/google/appengine/tools/php_cli.py php_cli.patch
