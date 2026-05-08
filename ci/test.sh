#!/bin/bash

mkdir -p /tmp/circleci-test-results

./bin/phpunit --log-junit /tmp/circleci-test-results/phpunit.xml
