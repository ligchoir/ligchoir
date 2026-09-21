# Updating Drupal core

Reference from the 10.6.3 → 10.6.9 patch update performed on 2026-05-20.

This is a **patch-level** Drupal core update (10.6.x → 10.6.x). It only touches
`composer.lock` and the regenerated vendor / web/core trees — `composer.json`'s
`drupal/core-recommended: ^10.5` constraint already permitted any 10.x release,
so no manifest change was needed.

## What changed locally (2026-05-20)

| Package                          | Before  | After   |
|----------------------------------|---------|---------|
| `drupal/core`                    | 10.6.3  | 10.6.9  |
| `drupal/core-recommended`        | 10.6.3  | 10.6.9  |
| `drupal/core-composer-scaffold`  | 10.6.3  | 10.6.9  |
| `drupal/core-project-message`    | 10.6.3  | 10.6.9  |
| `drupal/quickedit`               | 1.0.4   | 1.0.5   |
| Symfony 6.4.x components         | 6.4.30–33 | 6.4.34–40 |
| twig/twig                        | 3.22.2  | 3.26.0  |
| guzzlehttp/guzzle                | 7.10.0  | 7.10.2  |
| guzzlehttp/psr7 / promises       | minor   | minor   |
| mck89/peast                      | 1.17.4  | 1.17.6  |

41 packages updated in total. No security advisories reported by composer
after the update. `drush updatedb` reported **no pending schema updates**.

## Local steps (already done)

Run from the project root with ddev up.

```bash
# 1. Safety snapshot (restore with: ddev snapshot restore pre-drupal-10.6.9-update)
ddev snapshot --name pre-drupal-10.6.9-update

# 2. Update core + its dependency closure
ddev composer update "drupal/core-*" --with-all-dependencies

# 3. Apply DB updates (none expected for a patch bump, but always run it)
ddev exec drush updatedb -y

# 4. Rebuild caches
ddev exec drush cr

# 5. Verify
ddev exec drush core:status --field=drupal-version    # → 10.6.9
```

Only `composer.lock` ends up tracked — `vendor/` and `web/core/` are
gitignored and rebuilt from the lockfile on the server.

## Deploying to live

The site is deployed on the DigitalOcean server using the release-based
toolkit in the separate infrastructure repository
(https://github.com/therobyouknow/infrastructure, folder `provision-site/toolkit/`).
See that folder's `README.md` for full script documentation.

### 0. Pick a release version

Use the next semver patch (look at `version.md` in the infrastructure repository and
the existing `releases/` directories on the server for the current
number). The version string is used only as the on-disk release folder
name; it does not have to match Drupal's version.

```bash
ssh server03    # adjust if your ~/.ssh/config uses a different alias
sudo -iu rob    # toolkit scripts run as rob
cd /var/www/05/<domain>/releases && ls -1 | sort -V | tail -5
```

### 1. Cut a new release on the server

```bash
# On the server, as rob, in the toolkit dir
cd ~/scripts    # or wherever the toolkit lives — see install.sh

# Clones the repo at the current tip of the default branch
./create_release.sh <domain> <new_release_version> git@github.com:ligchoir/ligchoir.git
```

This creates `/var/www/05/<domain>/releases/<new_release_version>/code/`
with the freshly cloned repo, drush/composer symlinks, and a
`settings.local.php` symlink.

### 2. Wire the files symlink for staging, then deploy to staging

```bash
# Point the new release's sites/default/files at the staging files dir
cd /var/www/05/<domain>/releases/<new_release_version>/code/web/sites/default
ln -sf ../../../../../../deployment_environments/staging/assets/public/files files

# Deploy: backs up DB, runs composer install --no-dev, flips the symlink,
# runs drush updb -y and drush cr.
cd ~/scripts
./deploy.sh <domain> staging <new_release_version>
```

### 3. Smoke-test staging

Visit `https://staging.<domain>` and check:
- Front page loads, no white screen, no errors at the top.
- Admin login works (`/user/login`).
- `Reports → Status report` shows Drupal **10.6.9** and no new critical errors.
- A representative page with paragraphs / views renders correctly.
- Webform submission still works if forms are critical.

If anything is wrong, roll staging back with:

```bash
./update_symlink.sh <domain> staging <previous_release_version>
./drush_run.sh <domain> staging "cr"
```

### 4. Repeat the files symlink + deploy for live

```bash
# Files symlink for live (each env has its own files dir)
cd /var/www/05/<domain>/releases/<new_release_version>/code/web/sites/default
ln -sf ../../../../../../deployment_environments/live/assets/public/files files

# Deploy to live
cd ~/scripts
./deploy.sh <domain> live <new_release_version>
```

`deploy.sh` backs up the live DB before doing anything, so you have a
fresh restore point if something goes sideways.

### 5. Verify live

```bash
./drush_run.sh <domain> live "core:status --field=drupal-version"   # → 10.6.9
./drush_run.sh <domain> live "watchdog:show --count=20"
```

And in a browser:
- `https://<domain>` — homepage renders.
- Admin login works.
- `Reports → Status report` is clean.

### Rollback (if needed)

The previous release directory is still on disk, so a rollback is just
flipping the symlink back:

```bash
./update_symlink.sh <domain> live <previous_release_version>
./drush_run.sh <domain> live "cr"
```

If the DB schema was changed (not the case for this 10.6.3 → 10.6.9
patch), restore the pre-deploy backup that `deploy.sh` wrote to
`/var/www/05/<domain>/backups/database/` with `restore_db.sh`.

## Notes for future core updates

- **Patch updates** (`10.6.x → 10.6.y`): no `composer.json` change
  needed; just `composer update "drupal/core-*" --with-all-dependencies`
  and commit `composer.lock`. This is the path used here.
- **Minor updates** (`10.6.x → 10.7.x`): bump the constraint in
  `composer.json` first (e.g. `^10.5` → `^10.7`) before the same
  composer update. Check Drupal's release notes for any module
  compatibility caveats.
- **Major updates** (`10.x → 11.x`): a separate exercise — run the
  `upgrade_status` module (already required in `composer.json`) first to
  audit module readiness, and follow the official Drupal upgrade docs.
- Always run `drush updatedb -y` after any core update, even if you
  expect no schema changes — it's cheap and catches surprises.
