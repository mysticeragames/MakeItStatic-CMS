#!/bin/bash

# FROM ROOT FOLDER:   .cmd/only-build.sh


# You COULD use the external buildcache, but it's slower to lookup the remote every time, so it's faster using the local cache (which is used automatically after the first build)
#DOCKER_BUILDKIT=1 docker build --cache-from mysticeragames/makeitstatic-cms-buildcache:cache-linux-amd64 --target minimal -t cms:minimal .

#DOCKER_BUILDKIT=1 docker build --target minimal -t cms:minimal .
#DOCKER_BUILDKIT=1 docker build --target build_prod -t cms:build_prod .
#DOCKER_BUILDKIT=1 docker build --target build_test -t cms:build_test .
DOCKER_BUILDKIT=1 docker build --target final_test -t cms:test .
DOCKER_BUILDKIT=1 docker build --target final_prod -t cms:prod .

# DOCKER_BUILDKIT=1 docker build --no-cache --target final_test -t cms:test .
# DOCKER_BUILDKIT=1 docker build --no-cache --target final_prod -t cms:prod .

# display all images:
docker images cms:*

# run test image:
# docker run --rm -it -p 8250:8250 cms:test

# run production image:
# docker run --rm -it -p 8250:8250 cms:prod

# remove all local images:
# docker rmi $(docker images -q cms:*)

# run shell:        docker run --rm -it cms:minimal sh -c 'php -m && php -v && git -v'
