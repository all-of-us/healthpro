# Running Tests

1. Install chromedriver from https://sites.google.com/a/chromium.org/chromedriver
2. Set configurations in config.yml file
3. Start chromedriver on the command line via `chromedriver --port=4444 --url-base=wd/hub`
4. Run tests using locally installed phpunit (`vendor/bin/phpunit`):  
`./vendor/bin/phpunit --testsuite ui` to fetch participant data from workqueue  
`data=json ./vendor/bin/phpunit --testsuite ui` to fetch participant data from ptsc json file