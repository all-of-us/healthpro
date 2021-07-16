#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

bin/console doctrine:database:create --if-not-exist -n
bin/console doctrine:migrations:migrate -n
