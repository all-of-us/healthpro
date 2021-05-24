#!/bin/bash

mkdir -p /tmp/circleci-test-results

./bin/phpunit -v --log-junit /tmp/circleci-test-results/junit.xml
