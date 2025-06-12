#!/bin/bash

# FROM ROOT FOLDER:   .cmd/run-build.sh

# Start the CMS
docker run --rm \
    -p 8000:8000 \
    -v ~/.ssh:/home/appuser/.ssh:ro \
    --pull never \
    --name makeitstatic_cms_temp \
    cms:prod
