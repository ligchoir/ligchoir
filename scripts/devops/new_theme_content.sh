#!/bin/bash
# New theme (feat/new-theme): every content step that goes with the config,
# in the order the deploy notes list them. Idempotent: each script only
# creates what is missing, so the whole thing can be re-run after a fresh
# database import (the review site is rebuilt from the live database before
# go-live, and again at go-live).
#
#   scripts/devops/new_theme_content.sh live|staging|local [hero media dir]
#
# Run after post_deploy.sh (config imported). The hero clip and poster are
# not in git; pass the directory that holds hero-1280.mp4 and
# hero-poster.webp as the second argument, or set LIGC_HERO_MEDIA_DIR, or
# copy them into scripts/content/front-page/ first (see that README).
# "local" runs inside ddev with ddev's drush.

set -euo pipefail

ENVIRONMENT="${1:-}"
case "${ENVIRONMENT}" in
  live|staging|local) ;;
  *) echo "Usage: $0 live|staging|local [hero media dir]" >&2; exit 1 ;;
esac
if [ -n "${2:-}" ]; then
  export LIGC_HERO_MEDIA_DIR="$2"
fi

CODE="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${CODE}"
DRUSH="${CODE}/drush"
[ -x "${DRUSH}" ] || DRUSH="${CODE}/vendor/bin/drush"
[ -x "${DRUSH}" ] || { echo "no drush in ${CODE} (run composer install first)" >&2; exit 1; }

HERO_DIR="${LIGC_HERO_MEDIA_DIR:-${CODE}/scripts/content/front-page}"
for f in hero-1280.mp4 hero-poster.webp; do
  [ -f "${HERO_DIR}/${f}" ] || { echo "missing ${HERO_DIR}/${f}: copy the hero media in first (scripts/content/front-page/README.md)" >&2; exit 1; }
done

run() {
  echo; echo "[new-theme] $1"
  "${DRUSH}" php:script "scripts/devops/$1"
}

run front_page_2026.php            # hero media + front page paragraphs
run event_colour_schemes.php       # field_colour_scheme on every event
run geocode_venues.php             # venue locations (Nominatim, ~75 s first time)
run menu_2026.php                  # main menu
run projects_2026.php              # Projects landing page, L•I•V as a project
run menu_2026.php                  # again: Projects menu item
run landing_heroes_2026.php        # hero paragraphs on the landing pages
run review_round_1_2026.php
run review_round_2_2026.php
run review_round_3_2026.php
run collaboration_roles_2026.php   # collaboration roles vocabulary (#51)
run projects_batch_2026_11.php     # 23 projects + 23 collaborators (#50)
run projects_batch_2_2026_11.php   # tier 2 one-offs and recurring strands (#50)

echo; echo "[new-theme] cache rebuild"
"${DRUSH}" cr
echo "[new-theme] done. Check: / (hero loop, Events & News cards, three cards), /events-news, /projects, /about, /join, /book."
