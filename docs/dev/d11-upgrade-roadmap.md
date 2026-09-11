# LIGC site: updates, Drupal 11 and rebuild roadmap

Written 2026-09-11 on branch `chore/contrib-updates-2026-09`. Facts below come
from the local site, composer, upgrade_status and drupal.org package data on
that date. Re-check versions before each phase.

## 0. Where we are

| Item | State |
|------|-------|
| Drupal core | 10.6.16 (was 10.6.9, which had SA-CORE-2026-005 critical and six other advisories) |
| PHP | 8.3.30 (Drupal 11 needs 8.3+, fine) |
| Drush | 13.8.0 (Drupal 11 needs 13) |
| Composer | 2.9.5 (blocks packages with security advisories from resolving, so an insecure core must be updated before anything else will resolve) |
| Database | MariaDB 10.4 locally. **Drupal 11 needs MariaDB 10.6+ or MySQL 8.0+.** Check the server and bump ddev before Phase C. |
| Custom theme | `clau2`, no base theme, attaches `classy/node` in 4 templates. upgrade_status: only `core_version_requirement` needs changing. |
| Contrib module maintained by Rob | `smart_date_time_tbc` (drupal.org, 1.0.x-dev), 1 form_alter + 1 JS file. Needs a `core_version_requirement` update on drupal.org (Phase B4). |
| Config | Fully in `config/sync`, matched DB before and after the update. |

Drupal 10 is in its final minor (10.6). Its security support ends around the
Drupal 12 release; check drupal.org for the current date and treat Phase C as
time-boxed.

## 1. Contrib status against Drupal 11

**In use, already Drupal 11 compatible at the installed version**
antibot, block_classes, classy 2, classy_paragraphs 2, color_field 3.0, crop,
ctools, ds 3.37, entity_reference_revisions, eu_cookie_compliance,
field_formatter_class, fontawesome 3, fontyourface 4.2, image_widget_crop 3,
mailsystem, paragraph_blocks, paragraphs 1.23, pathauto, smart_date 4.3, smtp,
stable 2, structure_sync, token, viewsreference, webform 6.3.

**Need a different version at Drupal 11 time**
- gin 4.1.3 supports Drupal 11.0 and 11.1 only. gin 5.x needs 11.2+. Switch to gin ^5 and gin_toolbar ^3 in the same composer step as core ^11.
- youtube: only 3.0.0-beta1 supports Drupal 11. Used by the `youtube_video` paragraph type (4 config files).
- upgrade_status: 5.0.0-alpha3 for Drupal 11, or remove it after the upgrade.

**Blockers with no Drupal 11 release**
- content_sync 4.0.0-rc2: no Drupal 11 release. Removed (Phase B5).
- smart_date_time_tbc: Rob's own contrib module, needs a core_version_requirement release (see Phase B4).

**Enabled but unused. Uninstalled and removed on `chore/remove-unused-modules` (2026-09-11)**
No config depended on them. Deploy via `drush cim` (the export removes them from core.extension).

| Module | Notes |
|--------|-------|
| address | No address field storage exists. |
| csv_importer, csv_serialization | Superseded by `scripts/content/events` importer. |
| inline_entity_form | No form display uses it. |
| quickedit | Removed from core in 10; nothing uses it. |
| rdf | Removed from core in 10; nothing uses it. |
| views_data_export | No view uses a data export display. |
| entity_class_formatter | No display uses it. |
| twig_tweak | Was used once: the `image_style` filter in `node--home--full.html.twig`. Replaced by `clau2_preprocess_node()` in `clau2.theme` (done on `chore/remove-unused-modules`). |
| image_effects, file_mdm, file_mdm_exif, file_mdm_font | No image style uses an image_effects effect. Removing also removes the file_mdm pin that blocked the file_mdm update. |
| bartik, seven themes | Default theme is clau2, admin is gin. |
| stable theme | Keep until the new theme drops the `classy/node` library attachments; classy 2 still uses stable as base. |

## 2. Phases

### Phase A: this branch (done locally)
Core and contrib updated, config exported, pages verified. Next: Rob tests
locally, merge to `staging`, deploy to staging, then `main` and live. Treat as
urgent because of the core advisories.

### Phase B: housekeeping on Drupal 10 (each item is its own small branch)

**B1. Vegur font hosted by the theme, drop fontyourface** (done 2026-09-11 on `chore/vegur-local-font`: fonts in `clau2/fonts/`, rules in `css/common/fonts.css`; on the server, delete the Font entities and the terms in the languages_supported and font_classification vocabularies before `cim`, see docs/devops/README.txt)
Vegur was already local, served by fontyourface's `local_fonts` submodule from
`sites/default/files/fontyourface/local_fonts/vegur_{light,regular,bold}/font.woff`
(about 20 KB each). Steps:
1. Copy the three `.woff` files into `web/themes/custom/clau2/fonts/` (they are
   also base64-encoded in `config/sync/local_fonts.local_font_config_entity.vegur_*.yml`).
2. Add `@font-face` rules for weights 300, 400 and 700 to the theme CSS with
   `font-display: swap`, matching the existing `font-family: 'Vegur'` usage.
3. `drush pm:uninstall local_fonts fontyourface`, `drush cex`. This removes the
   fontyourface-owned vocabularies: font_classification, font_designer,
   font_foundry, font_tags, languages_supported, and the font manager view.
4. `composer remove drupal/fontyourface`.

FontAwesome is the other CDN dependency (`fontawesome.settings: use_cdn: true`,
loads `use.fontawesome.com` v6.4.2 plus a v4 shim). Either switch the module to
local assets, or, simpler for the new theme, inline the handful of SVG icons
actually used (social links) and drop the module.

**B2. Uninstall the unused modules** from section 1, one `drush pm:uninstall`
run, `drush cex`, then `composer remove` for each. Deploy to staging first: an
uninstall on live runs from the exported `core.extension.yml` during `drush cim`,
so the release must include the export.

**B3. Modifiers**
The contrib `modifiers` and `look` modules are not installed. What exists is
site-specific:
- paragraph types `image_bg_modifier` and `image_fx_modifier` (one instance each),
- `field_modifiers` on the `call_to_action` paragraph type (13 rows),
- vocabulary `modifiers_color` (8 terms), which **is** in use: event nodes
  reference it through `field_colour_background` and `field_colour_text`, and
  `node--event--full.html.twig` reads the colour from the term.

Done 2026-09-11 on `chore/remove-modifiers`: the 13 `field_modifiers` rows all pointed at paragraphs that no longer existed and the two modifier paragraphs were orphans, so the field, both paragraph types and their 30-odd fields were deleted. Server steps in docs/devops/README.txt. `modifiers_color` stays until the new theme replaces the per-event colour handling. The eight paragraph reference fields that still listed removed types (including the long-gone colors_modifier, custom_colors_modifier and date_time_single) under `target_bundles_drag_drop` were re-saved without them.

**B4. smart_date_time_tbc release for Drupal 11**
This is Rob's own contrib module (https://www.drupal.org/project/smart_date_time_tbc),
so the fix is a commit to its 1.0.x branch on drupal.org rather than a move to
custom. The code is one form_alter and one jQuery behaviour and needs no
changes for Drupal 11. Two edits:
1. `smart_date_time_tbc.info.yml`: `core_version_requirement: ^10 || ^11`.
2. `smart_date_time_tbc.libraries.yml`: declare what the JS uses, so it does
   not depend on another library having loaded jQuery first:
   ```yaml
   smart_date_time_tbc:
     version: VERSION
     js:
       js/smart_date_time_tbc.js: {}
     dependencies:
       - core/jquery
       - core/drupal
   ```
Tag a 1.0.1 release (or keep the site on 1.0.x-dev) and `composer update
drupal/smart_date_time_tbc`. The events importer relies only on the
`field_time_tbc` boolean, not on the module.

**B5. content_sync** (done 2026-09-11 on `chore/remove-content-sync`)
Uninstalled and removed with its export folder and settings line. For future
content moves: structure_sync for taxonomies/menus/blocks, single_content_sync
(Drupal 11 supported) for general content export, or the events importer. See
`docs/dev/content_sync.md`.

### Phase C: Drupal 11

Prerequisites: B4 and B5 done, server database MariaDB 10.6+ (or MySQL 8),
`.ddev/config.yaml` database bumped to match.

1. `clau2.info.yml`: `core_version_requirement: ^10 || ^11`.
2. `composer require -W drupal/core-recommended:^11 drupal/core-composer-scaffold:^11 drupal/core-project-message:^11 drupal/gin:^5 drupal/gin_toolbar:^3 drupal/youtube:^3@beta` and remove or bump upgrade_status.
3. `drush updb -y`, `drush cex -y`, review the diff, `drush cr`.
4. Run `drush upgrade_status:analyze --all` once more on Drupal 11 to catch deprecations that only surface at runtime.
5. Full page check (front, events listing, event full, about, contact, book us, join us, admin content and media library, webform submissions).
6. Staging deploy, directors' sign-off, live.

### Phase D: new theme and front page

Start a fresh theme (`clau3`), no base theme, like Solent's `customsolent`.
Keep LIGC's palette and Vegur; take structure and mechanics from Solent.

What to take from thesolentmetropolitan (`~/2-work/09-community/projects/thesolentmetropolitan/sites/thesolentmetropolitan.com/web/themes/custom/customsolent`):

| Piece | Files | Notes |
|-------|-------|-------|
| Main menu | `templates/navigation/menu--main.html.twig` (167 lines), `js/menu-state.js` (132), `css/menu-common.css`, `menu-desktop.css`, `menu-mobile.css`, `menu-focus.css` (about 1,650 lines of CSS) | Built around mega-menu sub-panels and a search item. Without submenus LIGC needs roughly a third of it. The open/close logic lives in `js/customsolent.js`, so extract that part. |
| Header, footer, hero | `css/header.css`, `footer-base.css`, `footer-end.css`, `hero-art-styles.css`, `templates/block/*`, `templates/paragraphs/paragraph--hero*` | Logo and menu on one line is already how Solent's header works. |
| Event teasers | `css/event-teaser.css`, `event-compact.css` | Solent also has an events listing. |
| Theme settings | `theme-settings.php`, `customsolent.breakpoints.yml` | |

On packaging the menu as a module: the menu is twig plus CSS plus JS, which
lives naturally in a theme. A module could ship the library and a block plugin,
but every site would still need its own template overrides, so the reuse gain
is small. Better vehicle: a shared starter theme repo that both sites copy
from, with the menu as one component directory. Solent's `customsolent_helpers`
module exists only because lazy-builder callbacks must live in a module; LIGC
has no equivalent need yet.

Front page composition, all achievable with what the site already has:
1. Header: logo and menu on one line (new theme).
2. Hero video: `field_background_media` already accepts `video` and
   `remote_video` media. The new template plays a muted looping `<video>` with a
   poster image fallback.
3. Intro text: existing `about_intro` or `text` paragraph.
4. Events list: existing `events` view (`events_listing` display) as a block.
5. Book and Join CTAs: existing `call_to_action` paragraphs pointing at the
   `book_us` and `join_us` webforms.
6. Footer: `social_and_contact_links` paragraph or a footer menu block.

Once the new theme is live, drop `stable` and `classy` (only clau2 attaches
their libraries), and revisit `modifiers_color`.

### Phase E: media workflow (parked, last)

Current chain, for when this is picked up:
- Media type `image` with the `image_widget_crop` widget.
- Crop type `header_cover_background`, aspect 6:4, soft limit 2000×1333, hard limit 800×600.
- Image styles `background_image_{sm,md,lg,xl}` plus `_2x` variants and `_preload`, each `scale_and_crop` then `crop_crop` with that crop type.
- The event hero, however, bypasses all of this: `node--event--full.html.twig`
  uses `file_url()` on the original file as a CSS background. That is why the
  banner work needed a hand-made letterbox image. The new theme should render
  the hero through a responsive image style so editors can upload any shape and
  set a focal point (the `focal_point` module is an option to evaluate here).

### Phase F: testing and release, every phase

1. Branch from `main`, work locally with ddev, `drush cst` clean before commit.
2. Merge to `staging`, deploy to `staging.internationalgospelchoir.uk` with the toolkit (`create_release.sh`, `deploy.sh`, then `drush_run.sh ... "cim -y"`).
3. Directors review on staging. Keep a short checklist per phase: front page, events, one event, webforms submit, admin login, media library.
4. Merge `staging` into `main` (see `merging_staging_into_main.md`), deploy live. `deploy.sh` backs up the database first; `restore_db.sh` is the rollback.

## 3. Immediate next steps

1. Test `chore/contrib-updates-2026-09` locally, then staging, then live. Security fix.
2. Check the live server's MariaDB/MySQL version for Phase C.
3. Confirm the unused-module list in section 1 so B2 can be done.
