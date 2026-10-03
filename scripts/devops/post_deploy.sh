#!/bin/bash
# Finish a deploy: repair, import config, migrate, verify. Idempotent.
#
#   scripts/devops/post_deploy.sh live|staging
#
# Run from anywhere; it works on the release that contains it. Run it after
# the code is in place (deploy.sh, or git pull + composer install) and the
# environment's docroot symlink points at this release. It:
#   1. checks the files and settings symlinks of this release
#   2. creates the private files directory for the environment (needs sudo)
#   3. repairs config that would make the import fail: modules still listed
#      in core.extension whose code is gone, stale system.schema entries,
#      mismatched field definitions (scripts/devops/fix_definitions.php)
#   4. captures the musical team terms and deletes them, so the import can
#      remove the vocabulary (scripts/devops/musical_team_to_nodes.php)
#   5. drush updb, drush cim (twice if the first pass leaves differences)
#   6. recreates the team as team_member nodes and points the About page at
#      the new view, then re-runs the repair script
#   7. reloads php-fpm, rebuilds caches, prints cst, version and status errors
# Every step is safe to repeat, so re-run the script after fixing a failure.
# Use "local" as the environment inside ddev (ddev exec scripts/devops/post_deploy.sh local);
# that skips the server-only steps.

set -euo pipefail

ENVIRONMENT="${1:-}"
case "${ENVIRONMENT}" in
  live|staging|local) ;;
  *) echo "Usage: $0 live|staging|local" >&2; exit 1 ;;
esac

CODE="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${CODE}"
DRUSH="${CODE}/drush"
[ -x "${DRUSH}" ] || DRUSH="${CODE}/vendor/bin/drush"
[ -x "${DRUSH}" ] || { echo "[post-deploy] no drush in ${CODE} (run composer install first)" >&2; exit 1; }

log() { echo; echo "[post-deploy] $*"; }

if [ "${ENVIRONMENT}" != "local" ]; then
  BASE="$(cd "${CODE}/../../.." && pwd)"           # /var/www/<category>/<domain>
  ENV_PATH="${BASE}/deployment_environments/${ENVIRONMENT}"
  [ -d "${ENV_PATH}" ] || { echo "[post-deploy] no such environment dir: ${ENV_PATH}" >&2; exit 1; }

  log "1. Symlink checks for ${ENVIRONMENT} (${CODE})"
  DOCROOT_TARGET="$(readlink -f "${ENV_PATH}/docroot" || true)"
  if [ "${DOCROOT_TARGET}" != "${CODE}/web" ]; then
    echo "[post-deploy] ${ENV_PATH}/docroot -> ${DOCROOT_TARGET}, not this release. Run deploy.sh / update_symlink.sh first." >&2
    exit 1
  fi
  FILES_TARGET="$(readlink -f "${CODE}/web/sites/default/files" || true)"
  if [ "${FILES_TARGET}" != "${ENV_PATH}/assets/public/files" ]; then
    echo "[post-deploy] web/sites/default/files -> ${FILES_TARGET:-<missing>}; expected ${ENV_PATH}/assets/public/files" >&2
    echo "  fix: cd ${CODE}/web/sites/default && ln -sfn ../../../../../../deployment_environments/${ENVIRONMENT}/assets/public/files files" >&2
    exit 1
  fi
  SETTINGS_TARGET="$(readlink "${CODE}/web/sites/default/settings.local.php" || true)"
  echo "  docroot ok, files ok, settings.local.php -> ${SETTINGS_TARGET:-<missing>}"

  log "2. Private files directory"
  PRIVATE="${ENV_PATH}/assets/private/files"
  if [ ! -d "${PRIVATE}" ]; then
    sudo mkdir -p "${PRIVATE}"
    sudo chown "$(id -un)":www-data "${ENV_PATH}/assets/private" "${PRIVATE}"
    sudo chmod 770 "${ENV_PATH}/assets/private" "${PRIVATE}"
    echo "  created ${PRIVATE}"
  else
    echo "  exists: ${PRIVATE}"
  fi
fi

log "3. Repair before import (missing modules, stale schema entries, field definitions)"
"${DRUSH}" php:script scripts/devops/fix_definitions.php

log "4. Capture the musical team terms (deleted so the import can drop the vocabulary)"
"${DRUSH}" php:script scripts/devops/musical_team_to_nodes.php -- capture

log "5. Database updates and config import"
"${DRUSH}" updb -y
"${DRUSH}" cim -y
if ! "${DRUSH}" cst 2>&1 | grep -q 'No differences'; then
  echo "  differences remain after the first import; importing again"
  "${DRUSH}" cim -y
fi

log "6. Team member nodes from the captured terms, About page re-pointed"
"${DRUSH}" php:script scripts/devops/musical_team_to_nodes.php -- apply
"${DRUSH}" php:script scripts/devops/fix_definitions.php

log "7. Caches, php-fpm, verification"
if [ "${ENVIRONMENT}" != "local" ]; then
  PHP_FPM_SERVICE="$(systemctl list-units --type=service --state=running --no-legend 'php*-fpm*' 2>/dev/null | awk '{print $1}' | head -1)"
  if [ -n "${PHP_FPM_SERVICE}" ]; then
    sudo systemctl reload "${PHP_FPM_SERVICE}"
    echo "  reloaded ${PHP_FPM_SERVICE}"
  fi
fi
"${DRUSH}" cr
echo "--- config status (expect: No differences)"
"${DRUSH}" cst
echo "--- version"
"${DRUSH}" status --fields=drupal-version,db-name
echo "--- status report errors (expect: none, or only SMTP on local)"
"${DRUSH}" core:requirements --severity=2 || true
echo
echo "[post-deploy] done. Check in a browser: / (initials, hero), /about (team grid, social icons), /events, a webform, admin/reports/status."
