#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# install composer dependencies
composer install

# chmod to allow gcloud to install components
# gcloud lives at /opt and wants to make a .staging directory
# when upgrading or installing new components.
sudo chmod o+w /opt

# install App Engine SDK
gcloud components install app-engine-php --quiet

# patch php_cli.py to make our CI environment variables available
patch /opt/google-cloud-sdk/platform/google_appengine/google/appengine/tools/php_cli.py $DIR/php_cli.patch

# make php_cli.py executable
chmod a+x /opt/google-cloud-sdk/platform/google_appengine/php_cli.py
