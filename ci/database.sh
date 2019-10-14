#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

FILES="${APP_DIR}/sql/healthpro/*"
for FILE in $FILES
do
  mysql -h 127.0.0.1 -u root -ppassw0rd circle_test < $FILE
done
