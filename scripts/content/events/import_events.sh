#!/bin/bash
# Import events from a YAML data file into this site.
#
# Usage (from anywhere; paths resolve relative to the repo root):
#   scripts/content/events/import_events.sh <data.yml> [--dry-run] [--update]
#
# Local (ddev):
#   ddev exec scripts/content/events/import_events.sh scripts/content/events/data/2026-09.yml --dry-run
#
# Live/staging: see import_events.sh in the infrastructure repo (provision-site/toolkit/),
# or run this directly from the release's code directory.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <data.yml> [--dry-run] [--update]" >&2
  exit 1
fi

DATA_FILE="$1"
shift
case "${DATA_FILE}" in
  /*) ;;
  *) DATA_FILE="${REPO_ROOT}/${DATA_FILE}" ;;
esac

if [ ! -f "${DATA_FILE}" ]; then
  echo "Data file not found: ${DATA_FILE}" >&2
  exit 1
fi

if [ ! -x "${REPO_ROOT}/drush" ]; then
  echo "drush launcher not found at ${REPO_ROOT}/drush" >&2
  exit 1
fi

cd "${REPO_ROOT}"
./drush php:script "${SCRIPT_DIR}/import_events.php" -- --file="${DATA_FILE}" "$@"
