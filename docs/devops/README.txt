
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

# Option B - toolkit wrapper (tools/infrastructure/provision-site/toolkit/import_events.sh,
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
# the module while its code is still present. Otherwise composer deletes the
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
