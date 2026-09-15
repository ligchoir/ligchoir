<?php

/**
 * @file
 * Mail health check for the webform enquiries.
 *
 * Run from the release code directory:
 *   ./drush php:script scripts/devops/mail-health.php -- daily [--to=a@x,b@y] [--mailbox-log=/path]
 *   ./drush php:script scripts/devops/mail-health.php -- weekly [--to=...] [--mailbox-log=/path]
 *
 * daily:  looks at the last 24 hours. If any mail error was logged, or
 *         submissions arrived but no send was logged, emails an alert and
 *         exits 1. Otherwise prints one line and exits 0.
 * weekly: emails a digest of the last 7 days whatever happened. If the
 *         digest stops arriving, mail is broken (a dead man's switch).
 *
 * Both rely on the smtp module logging "Sending mail to" (info) and
 * failures (error) in the dblog; dblog.settings row_limit must be large
 * enough to hold a day of entries (set to 100000 in config).
 *
 * Recipients: --to, else the site email address (system.site mail).
 * See docs/dev/webform-submissions-plan.md.
 */

use Drupal\Core\Database\Database;

$mode = 'daily';
$to = NULL;
$mailbox_log = NULL;
foreach ($extra ?? [] as $arg) {
  if ($arg === 'daily' || $arg === 'weekly') {
    $mode = $arg;
  }
  elseif (str_starts_with($arg, '--to=')) {
    $to = substr($arg, 5);
  }
  elseif (str_starts_with($arg, '--mailbox-log=')) {
    $mailbox_log = substr($arg, 14);
  }
}
$days = $mode === 'weekly' ? 7 : 1;
$since = \Drupal::time()->getRequestTime() - $days * 86400;
$db = Database::getConnection();

// Submissions per form in the window.
$submissions = $db->query('SELECT webform_id, COUNT(*) AS n FROM {webform_submission} WHERE created >= :since GROUP BY webform_id', [':since' => $since])->fetchAllKeyed();
$submissions_total = array_sum($submissions);

// Sends and errors from the log. smtp logs each send at severity 6 (info).
$sends = (int) $db->query("SELECT COUNT(*) FROM {watchdog} WHERE type = 'smtp' AND severity = 6 AND timestamp >= :since AND message LIKE 'Sending mail to%'", [':since' => $since])->fetchField();
$errors = (int) $db->query("SELECT COUNT(*) FROM {watchdog} WHERE type IN ('smtp', 'mail') AND severity <= 4 AND timestamp >= :since", [':since' => $since])->fetchField();
$last_error = $db->query("SELECT timestamp, message, variables FROM {watchdog} WHERE type IN ('smtp', 'mail') AND severity <= 4 AND timestamp >= :since ORDER BY timestamp DESC LIMIT 1", [':since' => $since])->fetchAssoc();

// Is the log deep enough to cover the window?
$oldest_log = (int) $db->query('SELECT MIN(timestamp) FROM {watchdog}')->fetchField();
$log_too_short = $oldest_log > $since;

// Retention: oldest stored submission and total stored.
$oldest_sub = (int) $db->query('SELECT MIN(created) FROM {webform_submission}')->fetchField();
$stored_total = (int) $db->query('SELECT COUNT(*) FROM {webform_submission}')->fetchField();
$oldest_days = $oldest_sub ? (int) floor((\Drupal::time()->getRequestTime() - $oldest_sub) / 86400) : 0;

$lines = [];
$lines[] = sprintf('%s report for the last %d day(s), %s', ucfirst($mode), $days, date('Y-m-d H:i'));
$lines[] = '';
$lines[] = sprintf('Submissions: %d', $submissions_total);
foreach ($submissions as $id => $n) {
  $lines[] = sprintf('  %-10s %d', $id, $n);
}
$lines[] = sprintf('Emails sent (logged by smtp): %d', $sends);
$lines[] = sprintf('Mail errors logged: %d', $errors);
if ($last_error) {
  $vars = @unserialize($last_error['variables']) ?: [];
  $text = strip_tags((string) t($last_error['message'], is_array($vars) ? $vars : []));
  $lines[] = sprintf('  last error %s: %s', date('Y-m-d H:i', $last_error['timestamp']), mb_substr($text, 0, 200));
}
$lines[] = sprintf('Stored submissions: %d (oldest %d days old; retention is 30 days)', $stored_total, $oldest_days);
if ($log_too_short) {
  $lines[] = sprintf('Note: the log only goes back to %s, less than the window. Raise dblog.settings row_limit.', date('Y-m-d H:i', $oldest_log));
}
if ($mailbox_log && is_readable($mailbox_log)) {
  $tail = trim((string) array_slice(file($mailbox_log), -1)[0] ?? '');
  $lines[] = 'Mailbox purge, last run: ' . $tail;
}
elseif ($mailbox_log) {
  $lines[] = 'Mailbox purge: no log found at ' . $mailbox_log;
}

$problems = [];
if ($errors > 0) {
  $problems[] = 'mail errors were logged';
}
if ($submissions_total > 0 && $sends === 0 && !$log_too_short) {
  $problems[] = 'submissions arrived but no email send was logged';
}
if ($oldest_days > 45) {
  $problems[] = 'submissions older than 45 days are still stored (is cron running?)';
}

$body = implode("\n", $lines);
print $body . "\n";

$send_it = $mode === 'weekly' || $problems;
if ($send_it) {
  $site = \Drupal::config('system.site');
  $to = $to ?: $site->get('mail');
  if (!$to) {
    print "No recipient: pass --to=address or set the site email address.\n";
  }
  $subject = $mode === 'weekly'
    ? sprintf('LIGC website weekly: %d enquiries, %d sent, %d errors', $submissions_total, $sends, $errors)
    : 'LIGC website ALERT: ' . implode('; ', $problems);
  if ($problems) {
    $body = 'PROBLEMS: ' . implode('; ', $problems) . "\n\n" . $body;
  }
  $result = $to ? \Drupal::service('plugin.manager.mail')->mail('system', 'action_send_email', $to, 'en', [
    'context' => ['subject' => $subject, 'message' => $body],
  ]) : [];
  print sprintf("Email to %s: %s\n", $to, !empty($result['result']) ? 'sent' : 'FAILED');
}
if ($problems) {
  print 'PROBLEMS: ' . implode('; ', $problems) . "\n";
  exit(1);
}
