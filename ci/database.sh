#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."
FILES="${APP_DIR}/sql/healthpro/*"
for FILE in $FILES
do
  mysql -u ubuntu circle_test < $FILE
done

