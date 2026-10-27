
https://github.com/ddev/ddev/issues/1865#issuecomment-538590008
ddev delete --omit-snapshot <project-name>

# https://github.com/ddev/ddev/issues/6068

limactl start docker

ddev start

fetch_and_import_env_db.sh
e.g.
fetch_and_import_env_db.sh server example.com live

create_database.sh
create_release.sh
create_site_structure.sh
create_vhost.sh
deploy.sh
drush_run.sh
fetch_and_import_env_db.sh
fetch_db_dump.sh
fetch_env_db_dump.sh
install.sh
restore_db.sh
site_status.sh
sync_files.sh
update_symlink.sh


# IMPORTANT - after ANY "git pull" into a release directory, install the code
# the lock file describes, before drush. deploy.sh does this for a new release;
# a pull into an existing release does not. Without it Drupal keeps running the
# old core/modules while importing new config (this happened Sep 2026: core
# stayed at 10.6.9 on live for a week after the 10.6.16 security update).

./composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.3-fpm            # ALWAYS after a code change: OPcache keeps old code otherwise
./drush updb -y
./drush cim -y
./drush cr
./drush status --fields=drupal-version     # confirm the version you expect

# Bulk event import on live (see scripts/content/events/README.md)
#
# Option A - from inside the release code dir, e.g.
#   cd /var/www/05/internationalgospelchoir.uk/releases/<version>/code
# (runs against that release's database; no backup taken, run backup_db.sh first if wanted)

scripts/content/events/import_events.sh scripts/content/events/data/<file>.yml --dry-run
scripts/content/events/import_events.sh scripts/content/events/data/<file>.yml

scripts/content/events/import_events.sh scripts/content/events/data/<file>-updates.yml --update --dry-run
scripts/content/events/import_events.sh scripts/content/events/data/<file>-updates.yml --update

# Option B - toolkit wrapper (import_events.sh from the infrastructure repo, provision-site/toolkit/,
# copied to the server scripts dir like the other toolkit scripts). Finds the live release,
# backs up the DB before a real run, clears cache after.

./import_events.sh internationalgospelchoir.uk live scripts/content/events/data/<file>.yml --dry-run
./import_events.sh internationalgospelchoir.uk live scripts/content/events/data/<file>.yml
./import_events.sh internationalgospelchoir.uk live scripts/content/events/data/<file>-updates.yml --update --dry-run
./import_events.sh internationalgospelchoir.uk live scripts/content/events/data/<file>-updates.yml --update



# Deploying a release that uninstalls modules (config import removes them)
# Run in the release code dir. Order matters: entities that belong to a module
# being removed must be deleted BEFORE cim, or the import validation fails.
#
# Release 2.0.7 (fontyourface removal) needed exactly this:

./drush entity:delete font -y
./drush entity:delete taxonomy_term --bundle=languages_supported -y
./drush entity:delete taxonomy_term --bundle=font_classification -y
./drush updb -y
./drush cim -y
./drush cr

# If cim still complains "Entities exist of type Taxonomy term and Vocabulary X",
# run: ./drush entity:delete taxonomy_term --bundle=<vocabulary machine name> -y

# Release removing the modifier paragraph types (image_bg_modifier, image_fx_modifier)
# and the field_modifiers field. Delete their paragraphs before cim, then purge
# the deleted field data (normally cron does this; running it now surfaces errors).

./drush entity:delete paragraph --bundle=image_bg_modifier -y
./drush entity:delete paragraph --bundle=image_fx_modifier -y
./drush cim -y
./drush cr
./drush php:eval 'field_purge_batch(500); print "purged\n";'

# If the purge reports "field storage does not exist" for field_mod_media_query
# (both removed types shared that field), remove the dangling entry and re-run:
./drush php:eval '$r=\Drupal::service("entity_field.deleted_fields_repository"); foreach ($r->getFieldDefinitions() as $f) { if ($f->getName()==="field_mod_media_query") { $r->removeFieldDefinition($f); } } field_purge_batch(500); print "purged\n";'

# ---------------------------------------------------------------------------
# LIVE catch-up after the September 2026 updates (code was never installed).
# Run from the live release code dir. Copy the whole block.
# ---------------------------------------------------------------------------
backup_db.sh internationalgospelchoir.uk 1
git checkout -- drush
git pull origin main
./composer install --no-dev --optimize-autoloader
./drush updb -y
./drush cim -y
./drush cr
./drush php:script scripts/devops/resave_config.php
./drush cim -y
./drush cr
./drush cst
./drush status --fields=drupal-version,drush-version
# Expected: cst "No differences", Drupal 10.6.16, Drush 13.8

# ---------------------------------------------------------------------------
# ORDER MATTERS when a release REMOVES a module (uninstalled + composer remove
# in the same release): run cim BEFORE composer install, so Drupal uninstalls
# the module while its code is still present. If the same release also ADDS
# a module, cim cannot run first either: instead "drush pmu <removed> -y",
# then composer install, then cim (seen Sep 2026, antibot out / honeypot in). Otherwise composer deletes the
# code first, the import can only edit core.extension, and the module's own
# uninstall (dropping its tables, system.schema entry) never runs.
#
#   git pull origin <branch>
#   ./drush cim -y            # uninstalls removed modules with code present
#   ./composer install --no-dev --optimize-autoloader
#   ./drush updb -y
#   ./drush cim -y
#   ./drush cr
#
# If it already happened the other way round (live, Sep 2026, content_sync):
./drush php:eval '\Drupal::keyValue("system.schema")->delete("content_sync"); print "schema entry removed\n";'
./drush sqlq "DROP TABLE IF EXISTS cs_db_snapshot, cs_logs"
./drush cr

# ---------------------------------------------------------------------------
# Local ddev: changing the database version (done 10.4 -> 10.11, Sep 2026).
# ddev cannot upgrade the data volume across major versions, so: dump, delete
# the volume, change the version, start, re-import. Run from the project root.
# ---------------------------------------------------------------------------
ddev export-db --file=databases/ligc-local-pre-bump-$(date +%Y%m%d_%H%M%S).sql.gz
ddev delete -O -y                      # removes containers + DB volume, keeps files and config
ddev config --database=mariadb:10.11
ddev start -y
ddev import-db --file=databases/<the dump above>
ddev drush sqlq "SELECT VERSION()"
ddev drush cst

# ---------------------------------------------------------------------------
# Drupal 11 release (feature/drupal-11). Prerequisite: PHP 8.3+ on the server
# (php -v). MariaDB 10.11 already confirmed. No modules removed in this release,
# so the normal order applies. Run from the release code dir.
# ---------------------------------------------------------------------------
backup_db.sh internationalgospelchoir.uk <db_number>
git pull origin <branch>
./composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.3-fpm
./drush updb -y
./drush cim -y
./drush cr
./drush cst
./drush status --fields=drupal-version,php-version
# Expected: no differences, Drupal 11.4.x. Then check /, /events, an event,
# admin login, admin/content, admin/reports/status (only SMTP-type warnings).
#
# After the Drupal 11 release the status report shows "Entity/field definitions:
# Mismatched" for taxonomy_term.field_role (stored definition metadata only; the
# table schema is identical, so no data changes). Re-save the definition, once
# per release/database (done locally 2026-09-11; needed on live and staging):
./drush php:eval '$m=\Drupal::entityDefinitionUpdateManager(); $i=\Drupal::service("entity_field.manager")->getFieldStorageDefinitions("taxonomy_term")["field_role"]; $m->updateFieldStorageDefinition($i); print json_encode($m->getChangeSummary())."\n";'
./drush cr
./drush core:requirements --severity=2       # Entity/field definitions should be gone
# "Trusted Host Settings: Not enabled" is pre-existing (no trusted_host_patterns
# in settings), not part of this release.

# ---------------------------------------------------------------------------
# FILES SYMLINK: check it after create_release, before deploy. Each release
# has web/sites/default/files -> deployment_environments/<env>/assets/public/files
# and it must match the environment the release is deployed to. Seen Sep 2026:
# live release 2.0.7 pointed at the STAGING files directory (live and staging
# are separate directories), so uploads on live would have landed in staging.
# Check and fix (from the release code dir):
readlink web/sites/default/files
cd web/sites/default && ln -sfn ../../../../../../deployment_environments/live/assets/public/files files && cd -
# If uploads happened while it was wrong, copy the new files across first:
# rsync -av --ignore-existing <staging files>/ <live files>/

# ---------------------------------------------------------------------------
# PHP-FPM RELOAD after every code deploy. Seen Sep 2026 on the Drupal 11
# release: the web PHP kept running cached Drupal 10 code after composer
# install (OPcache), so menu links lost their active class while drush (a
# fresh process) was fine. Reload, then drush cr:
sudo systemctl reload php8.3-fpm

# ---------------------------------------------------------------------------
# Webform retention release (feature/webform-retention, Sep 2026). See
# docs/dev/webform-submissions-plan.md. This release REMOVES antibot AND ADDS
# honeypot, so neither "cim first" nor "composer first" works on its own
# (cim first: cannot install honeypot, code missing; composer first: antibot
# code gone before Drupal uninstalls it). Uninstall the removed module by
# hand while its code is present, then install, then import. It also turns
# off automated cron (a real cron job replaces it), so run install_cron.sh
# straight after deploying. From the release code dir on the server:
# ---------------------------------------------------------------------------
backup_db.sh internationalgospelchoir.uk 1     # the last dump that holds all 7,500 old submissions
git pull origin <branch>
./drush pmu antibot -y                          # while its code is still present
./composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.3-fpm
./drush updb -y
./drush cim -y
./drush cr
./drush cst                                     # expect no differences
./drush pml --filter=honeypot                   # honeypot enabled; antibot gone

# Rob's steps after the deploy (package R in the plan):
#  1. Take superadmin@ off the four to_mail lines in settings/1/settings.local.php.
#  2. In Zoho Mail for superadmin@: enable IMAP access; create an app-specific
#     password (My Account, Security, App Passwords). Then on the server:
mkdir -p ~/.config/ligc && chmod 700 ~/.config/ligc
cat > ~/.config/ligc/zoho-imap.env <<'ENV'
ZOHO_IMAP_HOST=imappro.zoho.com
ZOHO_IMAP_USER=<superadmin address>
ZOHO_IMAP_PASSWORD=paste-the-app-password-here
ENV
chmod 600 ~/.config/ligc/zoho-imap.env
python3 scripts/devops/zoho-mailbox-purge.py --list-folders      # checks the login, lists folders
python3 scripts/devops/zoho-mailbox-purge.py --days 30            # dry run: what would go
python3 scripts/devops/zoho-mailbox-purge.py --days 30 --yes      # first real clean-out
#  3. Install the cron jobs (drush cron hourly, daily check, weekly digest,
#     nightly mailbox purge). alert_to is who gets the alerts and the Monday
#     digest. The toolkit copies on the server live in
#     ~/infrastructure/provision-site/toolkit (not on PATH); copy new scripts
#     there from the release, then run:
# (install_cron.sh comes from the infrastructure repo, provision-site/toolkit/; pull it there)
~/infrastructure/provision-site/toolkit/install_cron.sh internationalgospelchoir.uk live "<superadmin address>,<your gmail>"
#  4. Within a few days the hourly cron has purged every submission older
#     than 30 days (100 per run). To do it at once instead, and confirm:
./drush webform:purge --all -y
./drush sqlq "SELECT webform_id, COUNT(*), FROM_UNIXTIME(MIN(created)) FROM webform_submission GROUP BY webform_id"
#  5. Run the check by hand once to see the report:
./drush php:script scripts/devops/mail-health.php -- weekly --to=<your gmail>
#  6. Once happy, delete the pre-purge dump from backups/database (it holds
#     every old submission). backup_db.sh keeps the last 10 dumps, so it will
#     age out on its own within a few deploys anyway.

# Honeypot check on the live site: view source of /book and look for
# name="honeypot_time" and a hidden "url" text field. Submitting the form
# inside 5 seconds of loading it is rejected with a message asking to wait.


# ---------------------------------------------------------------------------
# EMAIL ADDRESSES are not kept in the repo (it may be open-sourced). The
# webform recipients (to_mail) are empty in config/sync and set per
# environment in settings/<n>/settings.local.php on the server as
# $config['webform.webform.<form>']['handlers']['<handler>']['settings']['to_mail'].
# Alert addresses for the cron jobs are passed to install_cron.sh, which
# only writes them into the crontab. Placeholders like <superadmin address>
# in these notes mean the real address. Git history before Sep 2026 still
# contains the addresses; rewrite or restart the history before publishing.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Klaro release (feature/klaro, Sep 2026): cookie/consent management moves
# from eu_cookie_compliance to Klaro. No banner: the site sets no cookies
# for visitors, so Klaro runs in "silent" mode and only gates the YouTube
# embed on the front page (a placeholder until the visitor accepts). The
# Klaro JS library is installed by composer into web/libraries/klaro (git
# ignored). This release REMOVES eu_cookie_compliance and ADDS klaro, so the
# same order as the retention release. From the release code dir:
# ---------------------------------------------------------------------------
git pull origin <branch>
./drush pmu eu_cookie_compliance -y             # while its code is still present
./composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.3-fpm
./drush updb -y
./drush cim -y                                  # installs klaro, its 3 apps and 2 purposes, grants "use klaro"
./drush cr
./drush cst
ls web/libraries/klaro/dist | head -3            # klaro-no-translations-no-css.js must be there
# Check: front page as a logged-out visitor shows "Load external content
# supplied by YouTube?" over the video; "Yes (this time)" loads it; "Manage
# privacy settings" opens the dialog. No cookie banner anywhere.
# To show a banner instead: admin/config/system/klaro, Dialog mode "Notice".


# ---------------------------------------------------------------------------
# October 2026 releases: status report clean-up, local icons (fontawesome
# removed), musical team as Team member content (musical_team vocabulary
# removed). One script does every post-deploy step for all of them, in the
# right order, and is safe to re-run. After the code is in place (deploy.sh,
# or git pull + ./composer install --no-dev --optimize-autoloader) and the
# environment's docroot points at the release:
# ---------------------------------------------------------------------------
bash scripts/devops/post_deploy.sh staging   # or: live (bash, as create_release sets files to 640)
# What it does (details at the top of the script):
#  - checks the docroot, files and settings.local.php symlinks of the release
#  - creates deployment_environments/<env>/assets/private/files (sudo; the
#    Webform "private files" item; settings.php picks the directory up)
#  - fix_definitions.php: drops modules whose code is gone from core.extension
#    and system.schema (antibot, eu_cookie_compliance on staging; fontawesome,
#    tour), re-saves the mismatched taxonomy_term.field_role definition
#  - musical_team_to_nodes.php capture: saves the team terms to state and
#    deletes them, since cim will not delete a vocabulary with terms
#  - drush updb, drush cim (twice if needed)
#  - musical_team_to_nodes.php apply: team_member nodes from the captured
#    terms, About page paragraph pointed at the team_members view
#  - php-fpm reload, drush cr, cst, version, status report errors
# Then check in a browser: / (initials and hero), /about (team grid, social
# icons), /events, submit a webform (HTML5 browser validation is now off, so
# "required" messages come from Drupal after submit), admin/reports/status.
# Expected status report: no Entity/field definitions, Trusted Host, Deprecated
# modules, Transaction isolation, HTML5 validation, Webform private files or
# Webform external libraries items. Still there and not fixable in the repo:
# "GD library: AVIF unsupported" (server PHP build).
#
# Settings now in settings.php for every environment: trusted_host_patterns
# (live, www, staging), READ COMMITTED isolation, enable_html5_validation
# FALSE, file_private_path auto-detected.
#
# Editors manage the team at admin/content, type "Team member" (Role, Photo,
# Biography, Order = position on /about, lowest first).

# ---------------------------------------------------------------------------
# Webform: no CDN library on /book (fix/webform-no-cdn, Oct 2026). Config only:
# jquery.timepicker added to webform.settings libraries.excluded_libraries.
# bash scripts/devops/post_deploy.sh <env>  (or just ./drush cim -y && ./drush cr)
# Check: view source of /book has no cdn.jsdelivr.net and the time field is
# still <input type="time">. Background: docs/dev/webform-libraries.md
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Release 2.0.14 (2026-10-27): events coordinator's replies. Content only: one
# --update data file plus the structure_sync export (new "London" venue term).
# No DB updates. On the server as rob.
# ---------------------------------------------------------------------------
cd ~/infrastructure/provision-site/toolkit && git pull --ff-only
D=internationalgospelchoir.uk; V=2.0.14; R=/var/www/05/$D/releases

./create_release.sh $D $V git@github.com:ligchoir/ligchoir.git
ln -sfn ../../../../../../deployment_environments/live/assets/public/files $R/$V/code/web/sites/default/files
ls -l $R/$V/code/web/sites/default/files              # -> .../deployment_environments/live/...
./deploy.sh $D live $V
bash $R/$V/code/scripts/devops/post_deploy.sh live     # cim: structure_sync.data Update (expected)
./import_events.sh $D live scripts/content/events/data/2026-10-coordinator-reply.yml --update --dry-run
./import_events.sh $D live scripts/content/events/data/2026-10-coordinator-reply.yml --update   # updated=5 venues_created=1
# Check: https://$D/events shows 23 events incl. the two "Private ... Booking (London)"
# entries, /event/young-leaders-light-wembley-way and
# /event/sharing-stories-hope-interfaith-event-st-albans-cathedral load.
# Then, by hand on live: edit Faithful Voices 2026 and add the flyer as Background Media.
# Rollback if needed: ./update_symlink.sh $D live 2.0.13 && ./drush_run.sh $D live cr
# (the content changes stay; re-run the 24 Oct -updates file to undo the renames).

: Drupal 11.4.11 (bug fix), Gin 5.0.16 (security,
# SA-CONTRIB-2026-219), Klaro 3.1.2. Code only; no config change, no DB updates.
# 2.0.12 (same day) was the October events batch. On the server as rob.
# ---------------------------------------------------------------------------
cd ~/infrastructure/provision-site/toolkit && git pull --ff-only
D=internationalgospelchoir.uk; V=2.0.13; R=/var/www/05/$D/releases

./create_release.sh $D $V git@github.com:ligchoir/ligchoir.git
ln -sfn ../../../../../../deployment_environments/live/assets/public/files $R/$V/code/web/sites/default/files
ls -l $R/$V/code/web/sites/default/files              # -> .../deployment_environments/live/...
./deploy.sh $D live $V
bash $R/$V/code/scripts/devops/post_deploy.sh live
./drush_run.sh $D live "status --field=drupal-version"   # -> 11.4.11
# Check: https://$D front page, /events, log in, admin/reports/status, then
# admin/reports/updates -> "Check manually": core, Gin and Klaro up to date.
# Rollback if needed: ./update_symlink.sh $D live 2.0.12 && ./drush_run.sh $D live cr

# ---------------------------------------------------------------------------
# Release 2.0.11 (2026-10-14): Drupal 11.4.10, security release (SA-CORE-2026-014
# critical RCE, -015 and -016 XSS). Code only; no config change of its own, but
# the release also carries the clau2 font-preload hotfix (8bfa7d6c) that 2.0.10
# missed. On the server as rob. Staging first (a few minutes), then live.
# ---------------------------------------------------------------------------
cd ~/infrastructure/provision-site/toolkit && git pull --ff-only
D=internationalgospelchoir.uk; V=2.0.11; R=/var/www/05/$D/releases

# Staging
./create_release.sh $D $V-staging git@github.com:ligchoir/ligchoir.git
ln -sfn ../../../../../../deployment_environments/staging/assets/public/files $R/$V-staging/code/web/sites/default/files
ls -l $R/$V-staging/code/web/sites/default/files      # -> .../deployment_environments/staging/...
./deploy.sh $D staging $V-staging                     # DB backup, composer install --no-dev, symlink, updb, cr
bash $R/$V-staging/code/scripts/devops/post_deploy.sh staging
# Check: https://staging.$D loads; admin/reports/status says 11.4.10.

# Live
./create_release.sh $D $V git@github.com:ligchoir/ligchoir.git
ln -sfn ../../../../../../deployment_environments/live/assets/public/files $R/$V/code/web/sites/default/files
ls -l $R/$V/code/web/sites/default/files              # -> .../deployment_environments/live/...
./deploy.sh $D live $V
bash $R/$V/code/scripts/devops/post_deploy.sh live
./drush_run.sh $D live "status --field=drupal-version"   # -> 11.4.10
# Check: https://$D front page, /about, /events, /book, log in, admin/reports/status.
# Rollback if needed: ./update_symlink.sh $D live 2.0.10 && ./drush_run.sh $D live cr
