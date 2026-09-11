# Moving content between environments

The `content_sync` module was removed on 2026-09-11 (branch
`chore/remove-content-sync`): it has no Drupal 11 release. It had been used
once, in February 2026, to export the Musical Team taxonomy and its images to
`content/sync` for import on live. That folder is gone; the files remain in git
history before this change if ever needed.

Options now:

- **structure_sync** (installed, Drupal 11 ready): taxonomies, menus and
  custom blocks. `drush export:taxonomies`, then commit
  `config/sync/structure_sync.data.yml`, then `drush import:taxonomies --choice=safe`
  on the target.
- **single_content_sync** (https://www.drupal.org/project/single_content_sync,
  Drupal 11 supported): export and import individual nodes, terms, media and
  more as YAML, with references. The closest replacement for content_sync if a
  general content export is needed again.
- **Events**: use the importer in `scripts/content/events/` (see its README).
