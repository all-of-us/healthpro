#!/bin/bash
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

FILES="${APP_DIR}/sql/healthpro/*"
for FILE in $FILES
do
  mysql -h healthpro-mysql -u root hpo < $FILE
done

FILES="${APP_DIR}/sql/dashboards/*"
for FILE in $FILES
do
  mysql -h healthpro-mysql -u root hpo < $FILE
done
