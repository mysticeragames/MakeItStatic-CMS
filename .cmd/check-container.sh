#!/bin/bash

# FROM ROOT FOLDER:   .cmd/check-container.sh cms:prod 8299

# no platform = default (it uses the current available platform), to specify a platform:
# FROM ROOT FOLDER:   .cmd/check-container.sh cms:prod 8299 linux/amd64
# FROM ROOT FOLDER:   .cmd/check-container.sh cms:prod 8299 linux/arm64

# This will check if the container is acually serving the CMS.
# Because the Functional tests are not catching this (for example, try to remove php-fpm from the Dockerfile and run .cmd/build-and-test.sh, the phpunit tests succeed, but it fails on this one )

if [ -z "$2" ]; then
    echo "Not enough arguments (exiting...)   usage: ./check-container.sh {my-image:my-tag} {port} {?optional-platform:linux/amd64|linux/arm64}  ->  ./check-container.sh cms:test 8299    or    ./check-container.sh cms:prod 8299"
    exit 1
fi

IMAGE="${1}"
PORT="${2}"
PLATFORM_ARGUMENT="--platform ${3}"
if [ -z "$3" ]; then
    PLATFORM_ARGUMENT=""
fi

CONTAINER_NAME=makeitstatic-testrun-$( echo "${IMAGE}" | sed 's|[^a-zA-Z0-9\\.\\-\\_]|-|g' | awk '{print tolower($0)}' )

echo -e "\n-> run '${IMAGE}' as '${CONTAINER_NAME}' on port ${PORT} (PLATFORM_ARGUMENT='${PLATFORM_ARGUMENT}')"

# Remove any container under the same name if that's still running
docker ps -a -q --filter name=${CONTAINER_NAME} | grep -q . && docker rm -f ${CONTAINER_NAME}

# run a detached container (max 15 seconds)
docker run --rm ${PLATFORM_ARGUMENT} -d --name ${CONTAINER_NAME} -p ${PORT}:8250 $IMAGE || exit 1

# wait until container is healthy
timeout 15s sh -c "until docker ps | grep -w ${CONTAINER_NAME} | grep -q healthy; do echo 'Waiting for container to be healthy (${IMAGE} -> ${CONTAINER_NAME})...'; sleep 1; done"

# try to see if the container is really serving the CMS (otherwise try to remove the container, and exit with errorcode 1)
RESPONSE=$( curl --silent http://localhost:${PORT} )

# Stop/remove the container
docker rm ${CONTAINER_NAME} --force

# Validate
IS_SUCCESS=false
echo $RESPONSE | grep --quiet "<!-- MakeItStatic-CMS - https://makeitstatic.com -->" && IS_SUCCESS=true

if [ "$IS_SUCCESS" = false ] ; then
    echo "ERROR: It seems the container ${CONTAINER_NAME} is not serving any webapp???"
    exit 1 # Error
fi

echo -e "-> Success ${CONTAINER_NAME}: the container is serving the webapp\n"
exit 0 # Success
