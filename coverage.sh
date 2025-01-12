#!/bin/bash

# run tests with (coverage) reports

# Run the cms container as root (needed because the volume is also mounted as root)
# Mount local './phpunit_reports/' to container '/var/www/html/reports/'
# Run the phpunit tests, output to ./reports
# Then finally, make your current user the owner of all the files in ./report (chown)

docker run --rm \
    --name makeitstatic-phpunit \
    --pull always \
    -u 0:0 \
    -e UID=$(id -u) \
    -e GID=$(id -g) \
    -v $(pwd):/var/www/html \
    mysticeragames/makeitstatic-cms:dev-main sh -c "mkdir -p ./reports/ && chown -R $UID:$GID ./reports && rm -rf ./reports/* && XDEBUG_MODE=coverage php bin/phpunit --testdox-html ./reports/testdox.html --log-junit ./reports/junit.xml --log-events-text ./reports/log-events.txt --log-teamcity ./reports/log-teamcity.txt --log-events-verbose-text ./reports/log-events-verbose.txt --coverage-html ./reports/coverage && chown -R $UID:$GID ./reports"

# can also use other tags (by default: use cms:dev-main to test against the latest main branch)
# mysticeragames/makeitstatic-cms:latest
# mysticeragames/makeitstatic-cms:dev-main
# mysticeragames/makeitstatic-cms:base-dev-main
# mysticeragames/makeitstatic-cms:base-0.1.2

# Show reports (WSL2)
powershell.exe -c reports/coverage/index.html
powershell.exe -c reports/testdox.html
