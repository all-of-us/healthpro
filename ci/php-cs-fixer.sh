#!/bin/bash

./tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run -v --log-junit /tmp/circleci-test-results/phpunit.xml
