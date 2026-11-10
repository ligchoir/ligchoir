<?php

/**
 * @file
 * Review round 4 (Rob, 2026-11-10). Idempotent content changes that go with
 * the config of the same commit:
 *
 *   1. Past events page: the view paragraph that embeds the archive loses
 *      its colour pickers (red box with a white panel), so the archive's
 *      cards sit on the black page exactly as on the Events & News page.
 *      (The config makes the "block_events_past" display use the event
 *      card view mode; the theme template gives it the same grid.)
 *
 *   ./drush php:script scripts/devops/review_round_4_2026.php
 */

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_manager = \Drupal::service('path_alias.manager');
$node_at = function (string $alias) use ($alias_manager, $node_storage) {
  $path = $alias_manager->getPathByAlias($alias);
  return preg_match('~^/node/(\d+)$~', $path, $m) ? $node_storage->load($m[1]) : NULL;
};

// --- 1. Past events page: the archive's view paragraph without colour pickers
$past = $node_at('/events/past-events');
if (!$past) {
  print "No Past events page found at /events/past-events.\n";
}
else {
  foreach ($past->get('field_content')->referencedEntities() as $paragraph) {
    if ($paragraph->bundle() !== 'view') { continue; }
    $cleared = FALSE;
    foreach (['field_colour_background', 'field_colour_text', 'field_colour_border'] as $field) {
      if ($paragraph->hasField($field) && !$paragraph->get($field)->isEmpty()) {
        $paragraph->set($field, NULL);
        $cleared = TRUE;
      }
    }
    if ($cleared) {
      $paragraph->save();
      print "Past events page: colour pickers cleared from the archive's view paragraph (cards on black).\n";
    }
    else {
      print "Past events page: view paragraph already without colour pickers.\n";
    }
  }
}

drupal_flush_all_caches();
print "Done.\n";
