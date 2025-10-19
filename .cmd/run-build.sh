#!/bin/bash

# FROM ROOT FOLDER:   .cmd/run-build.sh

# Start the CMS
docker run --rm \
    -p 8250:8250 \
    -v ~/.ssh:/home/appuser/.ssh:ro \
    --pull never \
    --name makeitstatic-cms-temp \
    cms:prod
