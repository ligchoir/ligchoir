# Bulk event import

Creates Event nodes (and any missing Venue taxonomy terms) from a YAML file,
so a list of dates from an email can be entered once, reviewed, and applied to
local, staging and live without retyping.

Files:

| File | Purpose |
|------|---------|
| `import_events.php` | The importer. Runs under `drush php:script`. |
| `import_events.sh` | Runner: resolves the repo root and calls drush with the right arguments. |
| `events.example.yml` | Documented example of the data file format. |
| `data/*.yml` | Real data files, one per batch. Committed so live runs the same file as local. |
| `provision-site/toolkit/import_events.sh` (infrastructure repo) | Server wrapper: finds the release, backs up the DB, runs the import, clears cache. |

## Workflow

1. Turn the email table into `data/<yyyy-mm>-<name>.yml` (see `events.example.yml`).
2. Dry run locally and check the report: which events will be created, which
   are skipped as already present, which venues will be created.
   ```bash
   ddev exec scripts/content/events/import_events.sh scripts/content/events/data/2026-09.yml --dry-run
   ```
3. Run it for real locally, check the events on the site.
   ```bash
   ddev exec scripts/content/events/import_events.sh scripts/content/events/data/2026-09.yml
   ```
4. Commit the data file, create and deploy a release as usual.
5. On the server (the toolkit scripts are installed system-wide):
   ```bash
   import_events internationalgospelchoir.uk live scripts/content/events/data/2026-09.yml --dry-run
   import_events internationalgospelchoir.uk live scripts/content/events/data/2026-09.yml
   ```
   Without `install.sh` use `./import_events.sh ...` from the toolkit directory.
   A database backup is taken before a real run.

## Behaviour

- **Idempotent.** An event is treated as already present when an Event node
  exists with the same title (case and whitespace insensitive) starting on the
  same calendar day. Re-running a file is safe. Pass `--update` to rewrite
  matched events from the file instead: date/time/TBC are always replaced,
  venue, body and published only when that key is present in the row. Keep
  updates in their own data file so re-running the original file stays a no-op.
- **Venues.** Matched by name against the Venues vocabulary, ignoring case,
  whitespace, a trailing ", London" and a trailing postcode. Unmatched venues
  are created with the short name from `venue`; `venue_address` (if given)
  becomes the term description, which is where addresses live on existing
  terms. Check the dry-run output for unintended new venues and fix the
  spelling in the YAML to match an existing term where one exists.
- **Times.** The site runs on UTC with no timezone override on the smart date
  formatters, so times are wall-clock values and are stored exactly as given.
  Accepted forms: `18:30`, `18.30`, `6:30pm`, `6.30 pm`, `6pm`, `1830`, `noon`.
  A single `time: "6.30pm - 8pm"` range is split automatically. An end before
  the start is taken as finishing after midnight.
- **Time TBC.** Blank or `tbc`/`tba` start, or `tbc: true`, sets the Time TBC
  flag and stores the smart date as 00:00 to 00:00, which is what the
  smart_date_time_tbc module does in the edit form.
- **Defaults** match recently entered events: author uid 1, promoted,
  published, white background / dark red text colour modifiers. Override under
  a top-level `defaults:` key if needed.
- **Tickets.** Optional `tickets:` list of `{url, title}` creates call-to-action
  paragraphs in the Tickets field, e.g. `title: "organiser's info"` or
  `"venue website"` as used on existing events.
- Path aliases come from the existing pathauto pattern (`/event/[node:title]`).

Exit code is 2 if any row errored; other rows are still processed.
