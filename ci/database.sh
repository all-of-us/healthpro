#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

APP_ENV=test bin/console doctrine:database:create --if-not-exists -n
APP_ENV=test bin/console doctrine:migrations:migrate -n
