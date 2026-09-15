# Webform enquiries: housekeeping and retention plan

Written 2026-09-15 from the live database (read-only), the exported config
and the server settings; decisions agreed with Rob the same evening
(section 5). Nothing has been purged yet. This is the brief for the scripts
and config work in section 6.

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
3. **superadmin@ mailbox: nothing accumulates.** superadmin@ comes off
   the recipient list of all four forms (a one-line edit each in
   `settings.local.php` on the server). The site still sends *as*
   superadmin@, and Zoho keeps a copy of every SMTP-sent message in that
   account's Sent folder by default. Turn that off in Zoho Mail: Settings,
   Mail Accounts, pick the address, SMTP section, untick "Save copy of sent
   emails". After the one-off purge (section 4) the mailbox then stays
   empty. No retention rule or script is needed going forward. (Zoho's own
   retention policy is part of eDiscovery, only on Mail Premium and
   Workplace Professional plans, and it governs the eDiscovery archive, not
   the live Inbox and Sent folders, so it would not have done the job.)
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
4. Empty the superadmin@ mailbox: Inbox, Sent, Trash. Manually in Zoho
   Mail (select all in each folder, delete, then empty Trash), or with the
   IMAP script below if the volume makes that tedious.
5. Delete the pre-purge dump and any older dumps once satisfied.

Steps 1 to 3 will be wrapped in `scripts/devops/webform-purge.sh` with a
dry-run mode.

**The mailbox script, for the record.** A short Python script using the
standard `imaplib` library: connects to Zoho IMAP (imappro.zoho.com, port
993, TLS) with an app-specific password generated in Zoho's security
settings (not the account password), lists the messages in a named folder
older than N days, prints what it would delete, and only deletes and
expunges with an explicit `--yes`. Run from Rob's machine or the server;
the password lives in an environment variable or a file readable only by
the user, never in the repo. Because superadmin@ stops receiving and
storing mail (item 3 in section 2), this script is only for the one-off
clean-out, if wanted, not something to run on a schedule.

## 5. Decisions (agreed 2026-09-15)

1. **Retention: 30 days** for the website copy of submissions.
2. **superadmin@ is dropped as a recipient** on all four forms. Delivery
   is proven by the daily check and weekly digest (section 3) instead.
3. **Mailbox: no rule, no scheduled script.** Zoho's retention policy
   turned out not to apply to the live mailbox (see section 2, item 3), so
   the approach is: stop the mail arriving (item 2) and stop Zoho saving
   SMTP copies to Sent, then a one-off clean-out. The IMAP script is
   documented above as an option for the clean-out only.
4. **Spam: Honeypot only, and Antibot to be reconsidered.** Honeypot is
   entirely server-side (a hidden field and a minimum fill time), so forms
   keep working with JavaScript off. Antibot, already installed, is the
   opposite: its project page says that without JavaScript "the form is
   hidden and a message is present in its place, telling them they need
   JavaScript in order to use the form". So the current setup is the one
   with the accessibility problem. Package B adds Honeypot; after a month
   with both, decide whether Antibot can go. Turnstile is held in reserve:
   it is free (Cloudflare's free plan allows 20 widgets and unlimited
   checks, no need to route the site through Cloudflare) but it also needs
   JavaScript, so it would only be added if Honeypot alone is not enough.
5. **Data requests: via the contact form on /about.** No email address on
   the page. The policy text will say so.

Also agreed: **move cookie consent to Klaro**, including gating the
YouTube embed on the front page until consent. The Klaro module is at
3.1.1 (July 2026), supports Drupal 11, ships with a predefined service for
oEmbed and social media embeds (which covers the YouTube paragraph) and
supports Google Consent Mode v2. Added as package I; it replaces
eu_cookie_compliance.

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
| G | Mailbox clean-out script | Optional, one-off: `scripts/devops/zoho-mailbox-purge.py` (IMAP, lists then deletes mail older than N days from one folder, `--yes` to act). |
| H | Privacy policy | Draft replacement text for the retention and IP paragraphs, a last-updated date, and the data-request route (contact form on /about); the wording is Rob's to approve and paste into the page. Plus the short note for team members (section 2, item 5). |
| I | Klaro | Replace eu_cookie_compliance with Klaro 3.1: consent banner, YouTube embed gated until consent, Google Analytics under Consent Mode; config export; theme styling to match the site. |
| R | Rob, on the server and in Zoho | Remove superadmin@ from the four `to_mail` lines in `settings.local.php`; in Zoho untick "Save copy of sent emails" for superadmin@; after E, empty the mailbox. Documented in `docs/devops/README.txt` when A ships. |

Suggested order: A, B, C and D together in one deploy (they are small and
reinforce each other) with R alongside, then E to run the one-off purge,
then F, H, then I as its own piece of work.

## 7. Notes

- Webform 6.3 has the purge, IP-tracking and spam-words features built in;
  no add-on module is needed for retention.
- The exported config in git has an empty recipient list for liv_book and
  older addresses for the others because the real addresses live in
  `settings.local.php` on the server. That is deliberate (keeps addresses
  out of the repo) and stays as is.
- Antibot stays; Honeypot and Antibot are commonly used together.
