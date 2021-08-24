#!/bin/bash

./tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run --format=junit > /tmp/circleci-test-results/phpunit.xml
