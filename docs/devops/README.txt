
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
