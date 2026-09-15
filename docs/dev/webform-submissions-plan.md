# Webform enquiries: housekeeping and retention plan

Written 2026-09-15 from the live database (read-only), the exported config
and the server settings. Nothing has been purged yet. This is the brief for
the scripts and config work that follows; the decisions Rob needs to make
are in section 5.

## 1. What is in place today

**Forms and recipients** (recipients are set in `settings.local.php` on the
server, which overrides the exported config; superadmin is on every form).

| Form | Page | Sent to | Anti-spam |
|------|------|---------|-----------|
| contact | /about | welcome@, superadmin@ | antibot |
| join_us | /join | f@example.com, superadmin@ | antibot |
| book_us | /book | booking@, welcome@, superadmin@ | antibot |
| liv_book | /liv | liv@, welcome@, superadmin@ | antibot |

**Submissions stored in the database** (live, 2026-09-15). Every form keeps
every submission forever (`purge: none`), and every submission stores the
sender's IP address.

| Form | Stored | Oldest | Contain a web link (spam proxy) |
|------|--------|--------|--------------------------------|
| contact | 3,329 | Oct 2021 | 2,521 (76%) |
| join_us | 3,535 | Jul 2021 | 620 (18%) |
| book_us | 474 | Aug 2021 | 126 (27%) |
| liv_book | 186 | Jan 2023 | 131 (70%) |
| **Total** | **7,524** | | ~3,400 |

Volume has dropped a lot: spring 2025 peaked at 200 to 370 a month on
join_us alone; the last six months run at 15 to 35 a month across all four
forms, mostly join_us.

**Mail**: the smtp module sends through Zoho (smtppro.zoho.com, port 587,
TLS) as superadmin@. The log has no mail errors in the last 180 days; every
send is logged as an "smtp" info entry, and a failure is logged as an error.
That is the hook for monitoring (section 3).

**Cron**: only Drupal's automated cron (runs on page requests, at most every
3 hours). There is no system crontab entry. Webform's purge runs on cron,
so a real cron job is part of this work.

**Backups**: `backup_db.sh` dumps the whole database into
`/var/www/05/internationalgospelchoir.uk/backups/database`. Each dump holds
every submission, so backup retention is part of data retention.

**Privacy policy** (/privacy): says contact information is kept "during the
course of our relationship" and disposed of "at the end of the process".
No retention period, no last-updated date, no mention that IP addresses are
stored, data requests are to be made "via the online forms".

## 2. Retention strategy (proposed)

The team members' mailboxes are the working record of an enquiry. The
website copy only needs to exist long enough to prove delivery and to
re-send if mail breaks. Proposal:

1. **Website database: 30 days.** Webform's built-in purge, per form:
   `purge: all`, `purge_days: 30`. Runs on cron, 100 at a time, no module
   needed. Thirty days is enough to notice an outage like April 2025 and
   re-send from the results page.
2. **Stop storing IP addresses.** Webform setting "Disable the tracking of
   user IP address" (`form_disable_remote_addr`) on all four forms. Antibot
   does not need it.
3. **superadmin@ mailbox: 30 days, then delete.** Two ways, pick one in
   section 5: a Zoho Mail retention rule set in the Zoho admin console (no
   code, preferred), or a small IMAP script run from the server by cron.
4. **Database backups: 30 days.** Add pruning to `backup_db.sh` so dumps
   older than 30 days are removed. The pre-purge backup taken in section 4
   is deleted once the purge is confirmed.
5. **Team mailboxes.** A one-paragraph note to the team: delete enquiry
   emails once the enquiry is closed, or within 12 months, whichever comes
   first. This is what the policy promises.

## 3. Knowing that mail works

The April 2025 outage went unnoticed for weeks because nothing reports on
delivery. Two cheap layers:

1. **Daily check on the server.** A drush php script run by cron: if the
   log has any smtp or mail error in the last 24 hours, or if there are
   submissions but no "Sending mail" log entries, email an alert to
   superadmin@ and Rob's Gmail (the alert uses the same SMTP route, so it
   also goes through a second route: `mail` on the server, or a webhook).
2. **Weekly digest as a dead man's switch.** Every Monday the same script
   emails a one-line summary (submissions per form, sends, errors). If the
   digest stops arriving, mail is broken. This replaces the need to keep
   superadmin@ copied on every enquiry just to prove delivery, if Rob wants
   to drop that.

## 4. One-off housekeeping (after the decisions in section 5)

Run on the server from the live release code directory, all reversible
until the last step:

1. `backup_db.sh internationalgospelchoir.uk 1` (pre-purge backup).
2. `drush webform:purge --all -y` (deletes all submissions, all forms), or
   per form with `drush webform:purge contact -y` etc.
3. Confirm counts are zero: `drush sqlq "SELECT webform_id, COUNT(*) FROM
   webform_submission GROUP BY webform_id"`.
4. Purge the superadmin@ mailbox in Zoho (manual, or the script).
5. Delete the pre-purge dump and any older dumps once satisfied.

These steps will be wrapped in `scripts/devops/webform-purge.sh` with a
dry-run mode.

## 5. Decisions for Rob

1. Retention period for the website copy: 30 days (proposed), or other.
2. Keep superadmin@ as a recipient on every form, or rely on the daily
   check and weekly digest instead? Keeping it is fine with a 30-day
   mailbox rule.
3. Mailbox purge: Zoho retention rule (no code) or IMAP script on the
   server (needs an app password stored in the server environment).
4. Spam: start with Honeypot (module 2.2.2, Drupal 11, no user
   interaction, adds a hidden field and a minimum fill time), review after
   a month, and only add a CAPTCHA (Cloudflare Turnstile, free, module
   1.2.0) if spam is still a problem. Turnstile shows a small widget to
   users.
5. Data requests contact in the privacy policy: keep "via the online
   forms" or publish welcome@ (or a dedicated privacy@) for it.

## 6. Work packages for Claude Code

Each is a branch, tested locally, deployed by Rob.

| # | Package | Deliverables |
|---|---------|--------------|
| A | Retention config | Purge settings and IP tracking off on all four forms (config export); `docs/devops/README.txt` deploy note. |
| B | Honeypot | `composer require drupal/honeypot`, enable on all four forms with time restriction, config export. |
| C | Cron | System crontab entry on server03 running `drush cron` every 15 minutes for the live release (via a small toolkit script `install_cron.sh`), so purge and the checks run on time rather than on traffic. |
| D | Mail health | `scripts/devops/mail-health.php` (drush php:script): daily alert, weekly digest, run from the crontab in C. |
| E | Purge script | `scripts/devops/webform-purge.sh` with `--dry-run`, wraps backup + `webform:purge` + count check; README block. |
| F | Backup pruning | `backup_db.sh`: keep 30 days; note in README. |
| G | Mailbox purge | Only if the script option is chosen in decision 3: `scripts/devops/zoho-mailbox-purge.py` (IMAP, deletes mail older than N days from one folder, dry-run first). |
| H | Privacy policy | Draft replacement text for the retention and IP paragraphs, a last-updated date, and the data-request contact; the wording is Rob's to approve and paste into the page. Plus the short note for team members (section 2, item 5). |

Suggested order: A, B, C and D together in one deploy (they are small and
reinforce each other), then E to run the one-off purge, then F, G, H.

## 7. Notes

- Webform 6.3 has the purge, IP-tracking and spam-words features built in;
  no add-on module is needed for retention.
- The exported config in git has an empty recipient list for liv_book and
  older addresses for the others because the real addresses live in
  `settings.local.php` on the server. That is deliberate (keeps addresses
  out of the repo) and stays as is.
- Antibot stays; Honeypot and Antibot are commonly used together.
