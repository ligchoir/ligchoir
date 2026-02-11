# Merging staging into main

Reference from the merge performed on 2026-02-11.

## Overview

When staging has diverged significantly from main, merging requires reviewing conflicts file by file. In most cases staging supersedes main, but some files on main may contain changes worth keeping.

## Pre-merge: Understand the divergence

```bash
# Find common ancestor
git merge-base main staging

# Commits on staging not yet on main
git log --oneline main..staging

# Commits on main not yet on staging
git log --oneline staging..main

# Files changed on BOTH branches (likely to conflict)
merge_base=$(git merge-base main staging)
git diff --name-only $merge_base main | sort > /tmp/main_changes.txt
git diff --name-only $merge_base staging | sort > /tmp/staging_changes.txt
comm -12 /tmp/main_changes.txt /tmp/staging_changes.txt
```

## Start the merge

```bash
git checkout main
git merge staging
```

Git will auto-merge where changes don't overlap and report conflicts for the rest.

## Resolve conflicts

For each conflicted file (`git diff --name-only --diff-filter=U`):

```bash
# Take staging's version
git checkout --theirs <file> && git add <file>

# Take main's version
git checkout --ours <file> && git add <file>
```

### Decisions made (2026-02-11)

| File | Resolution | Reason |
|------|-----------|--------|
| `.ddev/.gitignore` | Took **main** | Main had newer ddev config (removed obsolete share-providers entries) |
| `composer.lock` | Took **staging** + regenerated | Generated file; took staging then ran `ddev composer install` |
| `config/sync/system.site.yml` | Took **staging** | Site name updated to include CIC; email moved to environment settings |
| 11 toolkit scripts (`tools/infrastructure/provision-site/toolkit/`) | Took **staging** | Staging removed the `[category]` parameter (now derived automatically) |
| `claude.ai-prompt.md` | Took **staging** + trimmed | Staging had ~2200 lines of accidentally pasted session output appended |

### Auto-merged files (verified correct)

| File | Main changed | Staging changed |
|------|-------------|----------------|
| `composer.json` | Added antibot, upgraded gin/gin_toolbar | Same + added content_sync, structure_sync |
| `config/sync/ds.settings.yml` | Added `array_merge_build_bc` key | Reordered keys + same new key |
| `config/sync/webform.webform.liv_book.yml` | Removed `<p>` tags from confirmation message | Cleared `to_mail` (emails moved to env settings) |

## Post-merge

```bash
# Regenerate composer.lock
ddev composer install

# Commit the merge
git push

# Sync staging with main so both branches match
git checkout staging
git merge main    # should fast-forward
git push
git checkout main
```

## Tips

- `composer.lock` conflicts should always be resolved by taking one side and running `composer install` to regenerate.
- Review auto-merged files too — git can combine non-overlapping changes incorrectly if the file's semantics matter (e.g. YAML ordering).
- After merging staging into main, immediately merge main back into staging so the branches stay in sync.
