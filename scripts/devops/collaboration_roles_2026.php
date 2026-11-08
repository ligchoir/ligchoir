<?php

/**
 * @file
 * Starter terms for the "Collaboration roles" vocabulary (issue #51,
 * 2026-11-08). Safe to run repeatedly: creates only the terms that do not
 * exist yet, by name.
 *
 *   ./drush php:script scripts/devops/collaboration_roles_2026.php
 *
 * Needs the release's config imported first (taxonomy.vocabulary.collaboration_roles).
 */

use Drupal\taxonomy\Entity\Term;

$vid = 'collaboration_roles';
if (!\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid)) {
  print "Vocabulary $vid does not exist yet; import config first.\n";
  return;
}
$roles = [
  'Performed with',
  'Supported',
  'Commissioned by',
  'Hosted by',
  'Produced by',
  'Recorded with',
  'Funded by',
  'Workshop with',
  'Organised by',
  'Broadcast by',
];
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$created = 0;
foreach ($roles as $weight => $name) {
  if ($storage->loadByProperties(['vid' => $vid, 'name' => $name])) {
    continue;
  }
  Term::create(['vid' => $vid, 'name' => $name, 'weight' => $weight])->save();
  $created++;
}
print "Collaboration roles: $created term(s) created, " . (count($roles) - $created) . " already there.\n";
