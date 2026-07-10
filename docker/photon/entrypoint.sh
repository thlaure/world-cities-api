#!/bin/sh
set -e -o pipefail

# France+Monaco-scoped index — not the ~70GB planet-wide dump, since this API
# currently only imports France for city data too.
INDEX_URL="https://download1.graphhopper.com/public/europe/france-monacco/photon-db-france-monacco-1.0-latest.tar.bz2"

# Docker pre-creates the named-volume mount point as an empty directory before
# this script runs, so checking for existence alone would always be false —
# check for actual content instead.
if [ -z "$(ls -A /photon/photon_data 2>/dev/null)" ]; then
    echo "Photon index not found, downloading France+Monaco dataset (~5GB, first run only)..."
    curl -fL "$INDEX_URL" | bzip2 -cd | tar x -C /photon
fi

exec "$@"
