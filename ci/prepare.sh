#!/bin/bash

composer install

cd ~
wget https://storage.googleapis.com/appengine-sdks/featured/google_appengine_1.9.40.zip
unzip -q google_appengine_1.9.40.zip
