<?php

/**
 * @file
 * Import Event nodes (and any missing Venue terms) from a YAML data file.
 *
 * Run through drush php:script from the repository root:
 *
 *   ./drush php:script scripts/content/events/import_events.php -- --file=scripts/content/events/data/2026-09.yml --dry-run
 *   ./drush php:script scripts/content/events/import_events.php -- --file=scripts/content/events/data/2026-09.yml
 *
 * Options (after the "--" separator):
 *   --file=PATH   YAML file to import. Relative paths resolve from the repo root.
 *   --dry-run     Report what would happen without writing anything.
 *   --update      When an event already exists (same title + same day), update
 *                 its when/venue/TBC/body/status from the file instead of skipping.
 *
 * The script is idempotent: an event is considered "already present" when an
 * event node exists with the same title (case/whitespace-insensitive) whose
 * start falls on the same calendar day. Venue terms are matched by name
 * (case/whitespace-insensitive) in the "venues" vocabulary and created when
 * missing.
 *
 * Time handling matches the site convention: the site default timezone is UTC
 * and no timezone override is set on the smart date formatters, so times are
 * stored as literal wall-clock values. "19:30" is stored as 19:30 UTC and
 * displayed as 7:30pm. A "timezone" key at the top of the data file can change
 * that if the convention ever changes.
 *
 * "Time TBC": if start is empty, "tbc", "tba" etc., field_time_tbc is set and
 * the smart date is stored as 00:00 -> 00:00 (duration 0), mirroring what the
 * smart_date_time_tbc module does in the edit form.
 *
 * See README.md alongside this file for the data file format.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Yaml\Yaml;

// ---------------------------------------------------------------------------
// Options.
// ---------------------------------------------------------------------------

$options = [
  'file' => NULL,
  'dry-run' => FALSE,
  'update' => FALSE,
];
foreach ($extra ?? [] as $arg) {
  if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
    $options[$m[1]] = $m[2] ?? TRUE;
  }
}

if (empty($options['file'])) {
  fwrite(STDERR, "Missing --file=PATH\n");
  exit(1);
}

$file = $options['file'];
if ($file[0] !== '/') {
  $file = DRUPAL_ROOT . '/../' . $file;
}
if (!is_readable($file)) {
  fwrite(STDERR, "Cannot read data file: {$file}\n");
  exit(1);
}

$dryRun = (bool) $options['dry-run'];
$updateExisting = (bool) $options['update'];

// ---------------------------------------------------------------------------
// Load data.
// ---------------------------------------------------------------------------

$data = Yaml::parseFile($file);
if (empty($data['events']) || !is_array($data['events'])) {
  fwrite(STDERR, "Data file has no 'events' list.\n");
  exit(1);
}

$defaults = [
  'timezone' => 'UTC',
  'uid' => 1,
  'promote' => 1,
  'status' => 1,
  'body_format' => 'basic_html',
  // Colour modifier terms used by every recent event.
  'colour_background' => 10,
  'colour_text' => 7,
];
$defaults = array_merge($defaults, $data['defaults'] ?? []);
if (!empty($data['timezone'])) {
  $defaults['timezone'] = $data['timezone'];
}
$tz = new \DateTimeZone($defaults['timezone']);

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------

/**
 * Normalise a string for loose comparison.
 */
function ev_norm(?string $s): string {
  $s = html_entity_decode((string) $s, ENT_QUOTES);
  $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}"], ["'", "'", '"', '"', '-', '-'], $s);
  $s = mb_strtolower(trim($s));
  return preg_replace('/\s+/', ' ', $s);
}

/**
 * Parse a date in the common formats seen in emails. Returns Y-m-d.
 */
function ev_parse_date($raw): ?string {
  if ($raw instanceof \DateTimeInterface) {
    return $raw->format('Y-m-d');
  }
  // Unquoted YAML dates (2026-12-20) arrive as UTC timestamps.
  if (is_int($raw)) {
    return gmdate('Y-m-d', $raw);
  }
  $raw = trim((string) $raw);
  if ($raw === '') {
    return NULL;
  }
  // ISO first.
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
    return $raw;
  }
  // UK numeric d/m/Y or d.m.Y or d-m-Y (2 or 4 digit year).
  if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$#', $raw, $m)) {
    $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
    return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
  }
  // "Sat 11 Oct 2026", "11th October 2026", "Saturday, 11 October 2026".
  $clean = preg_replace('/(\d)(st|nd|rd|th)\b/', '$1', $raw);
  $clean = preg_replace('/^[A-Za-z]+,?\s+/', '', $clean);
  $ts = strtotime($clean);
  if ($ts !== FALSE) {
    return date('Y-m-d', $ts);
  }
  return NULL;
}

/**
 * Parse a time. Returns [hour, minute], or NULL when the time is TBC/blank.
 */
function ev_parse_time($raw): ?array {
  if ($raw === NULL) {
    return NULL;
  }
  $s = mb_strtolower(trim((string) $raw));
  $s = str_replace(['.', ' '], [':', ''], $s);
  if ($s === '' || in_array($s, ['tbc', 'tba', 'tbd', 'n/a', '-', '?'], TRUE)) {
    return NULL;
  }
  if (in_array($s, ['noon', 'midday', '12noon'], TRUE)) {
    return [12, 0];
  }
  if ($s === 'midnight') {
    return [0, 0];
  }
  // 18:30, 6:30pm, 6pm, 1830, 630pm, 18h30.
  if (preg_match('/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm)?$/', $s, $m)) {
    $h = (int) $m[1];
    $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
    // "630" / "1830" with no separator.
    if (!isset($m[2]) || $m[2] === '') {
      if (strlen($m[1]) >= 3) {
        $digits = $m[1];
        $min = (int) substr($digits, -2);
        $h = (int) substr($digits, 0, -2);
      }
    }
    $ap = $m[3] ?? '';
    if ($ap === 'pm' && $h < 12) {
      $h += 12;
    }
    if ($ap === 'am' && $h === 12) {
      $h = 0;
    }
    if ($h > 23 || $min > 59) {
      return NULL;
    }
    return [$h, $min];
  }
  // 3-4 digit run e.g. "1830".
  if (preg_match('/^(\d{3,4})$/', $s, $m)) {
    return [(int) substr($m[1], 0, -2), (int) substr($m[1], -2)];
  }
  throw new \InvalidArgumentException("Unrecognised time: '{$raw}'");
}

/**
 * Split a combined "18:30 - 20:00" / "6.30pm to 8pm" string into start/end.
 */
function ev_split_time_range(?string $raw): array {
  $raw = trim((string) $raw);
  if ($raw === '') {
    return [NULL, NULL];
  }
  $parts = preg_split('/\s*(?:-|–|—|to|until|till)\s*/iu', $raw, 2);
  return [trim($parts[0]), isset($parts[1]) ? trim($parts[1]) : NULL];
}

/**
 * Find a venue term by name (loose match). Returns Term or NULL.
 */
function ev_find_venue(string $name): ?Term {
  static $index = NULL;
  if ($index === NULL) {
    $index = [];
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $tids = $storage->getQuery()->accessCheck(FALSE)->condition('vid', 'venues')->execute();
    foreach ($storage->loadMultiple($tids) as $term) {
      $index[ev_norm($term->getName())] = $term;
    }
  }
  $key = ev_norm($name);
  if (isset($index[$key])) {
    return $index[$key];
  }
  // Tolerate trailing ", London" or a postcode in the data but not the term.
  $stripped = preg_replace('/,?\s*(london)?\s*([a-z]{1,2}\d[a-z\d]?\s*\d[a-z]{2})?$/i', '', $key);
  foreach ($index as $k => $term) {
    if ($stripped !== '' && $stripped === preg_replace('/,?\s*(london)?\s*([a-z]{1,2}\d[a-z\d]?\s*\d[a-z]{2})?$/i', '', $k)) {
      return $term;
    }
  }
  return NULL;
}

/**
 * Find an existing event node by title + day. Returns Node or NULL.
 */
function ev_find_event(string $title, int $dayStart, int $dayEnd): ?Node {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'event')
    ->condition('field_when.value', $dayStart, '>=')
    ->condition('field_when.value', $dayEnd, '<=')
    ->execute();
  $want = ev_norm($title);
  foreach ($storage->loadMultiple($nids) as $node) {
    if (ev_norm($node->getTitle()) === $want) {
      return $node;
    }
  }
  return NULL;
}

// ---------------------------------------------------------------------------
// Main loop.
// ---------------------------------------------------------------------------

$prefix = $dryRun ? '[DRY RUN] ' : '';
$counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'venues' => 0, 'errors' => 0];
$createdVenues = [];

echo "{$prefix}Importing " . count($data['events']) . " event(s) from {$file}\n";
echo "{$prefix}Timezone for wall-clock times: {$defaults['timezone']}\n\n";

foreach ($data['events'] as $i => $row) {
  $n = $i + 1;
  try {
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
      throw new \RuntimeException('missing title');
    }
    $date = ev_parse_date($row['date'] ?? NULL);
    if (!$date) {
      throw new \RuntimeException("missing or unparseable date '" . ($row['date'] ?? '') . "'");
    }

    // Times: either start/end keys, or a combined "time" range.
    $startRaw = $row['start'] ?? NULL;
    $endRaw = $row['end'] ?? NULL;
    if ($startRaw === NULL && $endRaw === NULL && isset($row['time'])) {
      [$startRaw, $endRaw] = ev_split_time_range((string) $row['time']);
    }
    $start = ev_parse_time($startRaw);
    $end = ev_parse_time($endRaw);
    $tbc = !empty($row['tbc']) || $start === NULL;

    $startDt = new \DateTime($date . ' 00:00:00', $tz);
    $dayStart = $startDt->getTimestamp();
    $dayEnd = $dayStart + 86399;

    if ($tbc) {
      $startTs = $endTs = $dayStart;
      $duration = 0;
    }
    else {
      $startDt->setTime($start[0], $start[1]);
      $startTs = $startDt->getTimestamp();
      if ($end === NULL) {
        $endTs = $startTs;
      }
      else {
        $endDt = clone $startDt;
        $endDt->setTime($end[0], $end[1]);
        if ($endDt < $startDt) {
          // Finishes after midnight.
          $endDt->modify('+1 day');
        }
        $endTs = $endDt->getTimestamp();
      }
      $duration = (int) round(($endTs - $startTs) / 60);
    }

    $timeLabel = $tbc ? 'time TBC' : (
      date('H:i', $startTs) . ($endTs > $startTs ? ' - ' . date('H:i', $endTs) : '')
    );

    // Venue.
    $venueTerm = NULL;
    $venueName = trim((string) ($row['venue'] ?? ''));
    if ($venueName !== '') {
      $venueTerm = ev_find_venue($venueName);
      if (!$venueTerm && isset($createdVenues[ev_norm($venueName)])) {
        $venueTerm = $createdVenues[ev_norm($venueName)];
      }
      if (!$venueTerm) {
        $venueTerm = Term::create([
          'vid' => 'venues',
          'name' => $venueName,
        ]);
        if (!empty($row['venue_address'])) {
          $venueTerm->set('description', [
            'value' => '<p>' . nl2br(htmlspecialchars(trim((string) $row['venue_address']), ENT_QUOTES)) . '</p>',
            'format' => $defaults['body_format'],
          ]);
        }
        if (!$dryRun) {
          $venueTerm->save();
        }
        $createdVenues[ev_norm($venueName)] = $venueTerm;
        $counts['venues']++;
        echo "{$prefix}  + venue term created: \"{$venueName}\"\n";
      }
    }

    // Existing?
    $existing = ev_find_event($title, $dayStart, $dayEnd);
    if ($existing && !$updateExisting) {
      $counts['skipped']++;
      echo "{$prefix}#{$n} SKIP   {$date} {$timeLabel}  \"{$title}\" (exists as node/{$existing->id()})\n";
      continue;
    }

    $status = isset($row['published']) ? (int) (bool) $row['published'] : (int) $defaults['status'];
    $when = [
      'value' => $startTs,
      'end_value' => $endTs,
      'duration' => $duration,
      'timezone' => '',
    ];

    if ($existing) {
      $node = $existing;
      $node->set('field_when', [$when]);
      $node->set('field_time_tbc', $tbc ? 1 : 0);
      $node->set('field_venue', $venueTerm ? ['target_id' => $venueTerm->id()] : NULL);
      $node->set('status', $status);
      if (isset($row['body'])) {
        $node->set('body', ['value' => (string) $row['body'], 'format' => $defaults['body_format']]);
      }
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Updated by import_events.php');
      $verb = 'UPDATE';
      $counts['updated']++;
    }
    else {
      $values = [
        'type' => 'event',
        'langcode' => 'en',
        'title' => $title,
        'uid' => $defaults['uid'],
        'status' => $status,
        'promote' => $defaults['promote'],
        'field_when' => [$when],
        'field_time_tbc' => $tbc ? 1 : 0,
        'field_colour_background' => ['target_id' => $defaults['colour_background']],
        'field_colour_text' => ['target_id' => $defaults['colour_text']],
      ];
      if ($venueTerm) {
        $values['field_venue'] = ['target_id' => $venueTerm->id()];
      }
      if (!empty($row['body'])) {
        $values['body'] = ['value' => (string) $row['body'], 'format' => $defaults['body_format']];
      }
      $node = Node::create($values);

      // Optional call-to-action paragraphs in the Tickets field.
      $ctas = [];
      foreach ((array) ($row['tickets'] ?? []) as $link) {
        if (empty($link['url'])) {
          continue;
        }
        $p = Paragraph::create([
          'type' => 'call_to_action',
          'field_link' => [
            'uri' => $link['url'],
            'title' => $link['title'] ?? 'more info',
          ],
        ]);
        if (!$dryRun) {
          $p->save();
        }
        $ctas[] = $p;
      }
      if ($ctas) {
        $node->set('field_tickets', $ctas);
      }
      $verb = 'CREATE';
      $counts['created']++;
    }

    if (!$dryRun) {
      $node->save();
    }
    $nid = $dryRun ? '?' : $node->id();
    $venueLabel = $venueTerm ? $venueTerm->getName() : '(no venue)';
    echo "{$prefix}#{$n} {$verb} {$date} {$timeLabel}  \"{$title}\" @ {$venueLabel} -> node/{$nid}" . ($status ? '' : ' [unpublished]') . "\n";
  }
  catch (\Throwable $e) {
    $counts['errors']++;
    echo "{$prefix}#{$n} ERROR  " . ($row['title'] ?? '(no title)') . ": " . $e->getMessage() . "\n";
  }
}

echo "\n{$prefix}Done. created={$counts['created']} updated={$counts['updated']} skipped={$counts['skipped']} venues_created={$counts['venues']} errors={$counts['errors']}\n";
if ($counts['errors'] > 0) {
  exit(2);
}
