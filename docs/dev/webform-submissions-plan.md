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
TLS) as superadmin@. Every send is logged as an "smtp" info entry and a
failure as an error; that is the hook for monitoring (section 3). But the
log (dblog) is capped at 1,000 rows, which is about one day of entries on
this site, so there is no evidence either way about April 2025, and "no
errors" only means none in the last day. Package A raises the cap.

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
3. **superadmin@ mailbox: Sent folder kept for 30 days.** superadmin@
   comes off the recipient list of all four forms (a one-line edit each in
   `settings.local.php` on the server), so the Inbox stops receiving
   enquiries. The site still sends *as* superadmin@, and Zoho saves a copy
   of every SMTP-sent message in that account's Sent folder. That copy is
   Rob's evidence that a form sent its email, so it stays on, and a
   scheduled script (package G) deletes Sent, Inbox and Trash mail older
   than 30 days. Zoho's own retention policy cannot do this: it is part of
   eDiscovery (Mail Premium and Workplace Professional plans only) and it
   governs the eDiscovery archive, not the live folders.
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

**First, the April 2025 window.** Email stopped working for a few weeks
before it was noticed around 14 April 2025. The four weeks up to and
including that date (17 March to 14 April 2025) hold 376 submissions:
contact 164, join_us 184, liv_book 26, book_us 2. Before anything is
purged, these are exported to CSV with webform's own exporter (one file per
form, `drush webform:export <form> --range-type=date --range-start=2025-03-17
--range-end=2025-04-14`), copied to Rob's machine and removed from the
server. A small triage script then splits each CSV into "likely spam"
(contains a link, known spam phrases, nonsense names) and "worth a look",
so Rob can read the short list and decide whether any late replies are
owed. The CSVs are deleted once that is done; nothing from the window
needs to stay in the database, so the purge below can then run in full.

Then, run on the server from the live release code directory, all
reversible until the last step:

1. `backup_db.sh internationalgospelchoir.uk 1` (pre-purge backup).
2. `drush webform:purge --all -y` (deletes all submissions, all forms), or
   per form with `drush webform:purge contact -y` etc.
3. Confirm counts are zero: `drush sqlq "SELECT webform_id, COUNT(*) FROM
   webform_submission GROUP BY webform_id"`.
4. Empty the superadmin@ mailbox of everything older than 30 days: the
   first run of the mailbox script (package G), or by hand in Zoho Mail.
5. Delete the pre-purge dump and any older dumps once satisfied.

Steps 1 to 3 will be wrapped in `scripts/devops/webform-purge.sh` with a
dry-run mode.

**The mailbox script.** A short Python script using the standard `imaplib`
library: connects to Zoho IMAP (imappro.zoho.com, port 993, TLS) with an
app-specific password generated in Zoho's security settings (not the
account password), lists the messages in Sent, Inbox and Trash older than
30 days, prints what it would delete, and only deletes and expunges with
`--yes`. It runs nightly from the server's crontab (package C). The
password lives in a file readable only by Rob's user on the server, never
in the repo.

## 5. Decisions (agreed 2026-09-15)

1. **Retention: 30 days** for the website copy of submissions.
2. **superadmin@ is dropped as a recipient** on all four forms. Delivery
   is proven by the daily check and weekly digest (section 3) instead.
3. **Mailbox: keep Sent as the delivery evidence, purge at 30 days by
   script.** Zoho's retention policy does not apply to the live mailbox,
   so the IMAP script in section 4 runs nightly from the server.
4. **Spam: Honeypot replaces Antibot.** Honeypot is entirely server-side
   (a hidden field and a minimum fill time), so forms keep working with
   JavaScript off, which matters for the accessibility work Rob has
   planned. Antibot is the opposite: its project page says that without
   JavaScript "the form is hidden and a message is present in its place,
   telling them they need JavaScript in order to use the form". Package B
   installs Honeypot on all four forms and uninstalls Antibot. Turnstile
   is held in reserve: it is free (Cloudflare's free plan allows 20
   widgets and unlimited checks, no need to route the site through
   Cloudflare) but it also needs JavaScript, so only if Honeypot alone is
   not enough.
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
| X | April 2025 window | Export the 376 submissions from 17 Mar to 14 Apr 2025 to CSV (read-only, done by Claude Code over ssh), copy to Rob's machine, triage script, short list for Rob. Must finish before E. |
| A | Retention config | Purge settings and IP tracking off on all four forms; dblog row limit raised to 100,000 so the log holds months, not a day (config export); `docs/devops/README.txt` deploy note. |
| B | Honeypot | `composer require drupal/honeypot`, enable on all four forms with time restriction, uninstall and remove Antibot (config export; deploy order for a removed module is in the README). |
| C | Cron | System crontab entries on server03 (via a small toolkit script `install_cron.sh`): `drush cron` every 15 minutes for the live release, the mail-health check daily, the mailbox purge nightly. So purge and the checks run on time rather than on traffic. |
| D | Mail health | `scripts/devops/mail-health.php` (drush php:script): daily alert, weekly digest, run from the crontab in C. Plus the visitor-facing message when a send fails (Rob's suggestion 2026-09-15): replace core's "Unable to send email. Contact the site administrator if the problem persists." with wording that says email is not working at the moment, the message has been saved and the team alerted, and to use the social media channels if it is urgent. Done with a string override in settings so no code change is needed to the module. The saved submission is then re-sent from the results page once mail is back. |
| E | Purge script | `scripts/devops/webform-purge.sh` with `--dry-run`, wraps backup + `webform:purge` + count check; README block. |
| F | Backup pruning | `backup_db.sh`: keep 30 days; note in README. |
| G | Mailbox purge script | `scripts/devops/zoho-mailbox-purge.py` (IMAP, lists then with `--yes` deletes mail older than 30 days from Sent, Inbox and Trash), scheduled nightly by C. Credentials from a file on the server (see R). |
| H | Privacy policy | Draft replacement text for the retention and IP paragraphs, a last-updated date, and the data-request route (contact form on /about); the wording is Rob's to approve and paste into the page. Plus the short note for team members (section 2, item 5). |
| I | Klaro | Replace eu_cookie_compliance with Klaro 3.1: consent banner, YouTube embed gated until consent, Google Analytics under Consent Mode; config export; theme styling to match the site. |
| R | Rob, on the server and in Zoho | Remove superadmin@ from the four `to_mail` lines in `settings.local.php`; in Zoho enable IMAP access and create an app-specific password for superadmin@ and put it in `/home/rob/.config/ligc/zoho-imap.env` (mode 600); run `install_cron.sh`; run the purge script after X. Documented in `docs/devops/README.txt` when A ships. |

Suggested order: X first (read-only, no deploy). Then A, B, C, D and G
together in one deploy with R alongside. Then E to run the one-off purge,
then F, H, then I as its own piece of work.

## 7. Notes

- Webform 6.3 has the purge, IP-tracking and spam-words features built in;
  no add-on module is needed for retention.
- The exported config in git has an empty recipient list for liv_book and
  older addresses for the others because the real addresses live in
  `settings.local.php` on the server. That is deliberate (keeps addresses
  out of the repo) and stays as is.
- Antibot goes when Honeypot arrives (package B). The two are commonly
  used together, but Antibot blocks visitors without JavaScript.
