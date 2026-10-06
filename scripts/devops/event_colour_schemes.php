<?php

/**
 * @file
 * Give every event a colour scheme from its old colour-term pair.
 *
 *   ./drush php:script scripts/devops/event_colour_schemes.php
 *
 * The old fields field_colour_background and field_colour_text (terms in the
 * modifiers_color vocabulary) are replaced by field_colour_scheme, a list of
 * tested background/text pairs (plan section 12). Every event on the site
 * used the same pair (white box, dark red text), which maps to "paper".
 * Events that already have a scheme are left alone. Safe to run repeatedly.
 */

$map = [
  // background term id => scheme
  10 => 'paper',   // white
  8 => 'paper',    // "yellow lime" (stored as white by mistake)
  11 => 'ink',     // black
  5 => 'ink',      // transparent (on the black page)
  6 => 'red',      // deep orange #D63936
  4 => 'red',      // red
  7 => 'red',      // dark red
  9 => 'cream',    // pink warm
];
$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'event')->execute();
$set = $kept = 0;
foreach ($storage->loadMultiple($ids) as $node) {
  if (!$node->get('field_colour_scheme')->isEmpty()) { $kept++; continue; }
  $bg = $node->hasField('field_colour_background') ? $node->get('field_colour_background')->target_id : NULL;
  $node->set('field_colour_scheme', $map[$bg] ?? 'paper');
  $node->setNewRevision(FALSE);
  $node->save();
  $set++;
}
print "Events: $set given a scheme, $kept already had one, " . count($ids) . " in total.\n";
