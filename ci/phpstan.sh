#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

cp $APP_DIR/phpstan.neon.dist $APP_DIR/phpstan.neon

sed -i 's/var\/cache\/dev/var\/cache\/test/gI' $APP_DIR/phpstan.neon
sed -i 's/App_KernelDevDebugContainer/App_KernelTestDebugContainer/gI' $APP_DIR/phpstan.neon
APP_ENV=test ./vendor/bin/phpstan analyse --memory-limit 2G
