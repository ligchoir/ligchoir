# Updating Drupal core

## 2026-10-24: 11.4.10 → 11.4.11, plus Gin 5.0.16 (security) and Klaro 3.1.2

Prompted by admin/reports/updates on live after release 2.0.12. Gin 5.0.16
fixes SA-CONTRIB-2026-219 (moderately critical, cross-site scripting in
tooltip rendering). Drupal 11.4.11 is a bug-fix release (dependency bumps;
`.htaccess` changes `php_value assert.active 0` to `php_value zend.assertions 0`,
which the scaffold applied and is committed). Klaro 3.1.2 is a bug-fix release
(HTML parsing via masterminds/html5). Color Field 3.1.0-beta1 was listed but
is a pre-release, not the recommended version, and was left alone.
`drush updatedb` reported no pending updates; no config change. Live release 2.0.13.

| Package                          | Before  | After   |
|----------------------------------|---------|---------|
| `drupal/core`, `core-recommended`, `core-composer-scaffold`, `core-project-message` | 11.4.10 | 11.4.11 |
| `drupal/gin`                     | 5.0.15  | 5.0.16  |
| `drupal/klaro`                   | 3.1.1   | 3.1.2   |
| `guzzlehttp/guzzle`, `promises`, `psr7` | 7.15.5, 2.5.3, 2.13.1 | 7.16.0, 2.6.0, 2.13.2 |

Local: on a copy of the live database, `ddev composer update drupal/core-recommended
drupal/core-composer-scaffold drupal/core-project-message drupal/gin drupal/klaro
--with-all-dependencies`, `drush updb -y`, `drush cr`, `drush cex --diff` (identical),
front and admin pages checked, `composer audit`.

Deploy: the release block "2.0.13" in `docs/devops/README.txt`.

---

## 2026-10-14: 11.4.8 → 11.4.10 (security release)

Drupal 11.4.10 (released 2026-10-14) fixes SA-CORE-2026-014 (critical,
remote code execution), SA-CORE-2026-015 and SA-CORE-2026-016 (moderately
critical, cross-site scripting). Security fixes only; `drush updatedb`
reported no pending updates; `composer audit` clean. Live release 2.0.11.

| Package                          | Before  | After   |
|----------------------------------|---------|---------|
| `drupal/core`, `core-recommended`, `core-composer-scaffold`, `core-project-message` | 11.4.8 | 11.4.10 |
| Symfony 7.4.x components (14)    | 7.4.8–7.4.20 | 7.4.21 |

Local: `ddev snapshot --name pre-drupal-11.4.10-update`, then
`ddev composer update "drupal/core-*" --with-all-dependencies`, `drush updb -y`,
`drush cr`, `drush status --field=drupal-version`, `composer audit`, pages
checked. Note: with `feat/new-theme` in progress, the local database had the
new theme's modules enabled and drush could not boot on `main`; the main-state
snapshot `all-four-branches-applied` (2026-10-03) was restored for the update
and the new-theme database saved as snapshot `new-theme-2026-10-14`.

Deploy: the release block "2.0.11" in `docs/devops/README.txt` (same as the
October flow: create_release → files symlink → deploy.sh → post_deploy.sh).

---


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
cd ~/infrastructure/provision-site/toolkit    # clone of the infrastructure repo; git pull first

# Clones the repo at the current tip of the default branch (main) ...
./create_release.sh <domain> <new_release_version> git@github.com:ligchoir/ligchoir.git
# ... or, for a staging test of an unmerged branch, name the branch as the 4th argument.
# Without it staging gets main, i.e. the code that is already live (seen 2026-10-03).
./create_release.sh <domain> <new_release_version>-staging git@github.com:ligchoir/ligchoir.git <branch>
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
cd ~/infrastructure/provision-site/toolkit
./deploy.sh <domain> staging <new_release_version>
```

### 2b. Run the post-deploy script

Every release since October 2026 finishes with one idempotent script that
imports config and does the release-specific repairs and migrations (see the
latest block in `docs/devops/README.txt` for what it covers):

```bash
bash /var/www/05/<domain>/releases/<new_release_version>/code/scripts/devops/post_deploy.sh staging
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
cd ~/infrastructure/provision-site/toolkit
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
