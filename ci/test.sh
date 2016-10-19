#!/bin/bash

export PATH=$(readlink -f ~/google_appengine):$PATH
./bin/phpunit
