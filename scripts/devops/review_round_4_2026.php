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
 *   2. Footer social links: a LinkedIn link (company/ligchoir) after Spotify,
 *      on the LinkedIn blue, in the editors' markup block (Rob, 2026-11-11).
 *   3. Join us and About hero images: focal point near the top (50% / 12%),
 *      as round 2 did for Events and Book us, so the 16:9 crop keeps the
 *      heads; derivatives flushed (Rob, 2026-11-11).
 *   4. Front page hero: the "Watch the full video on YouTube" overlay is
 *      removed, since the reel has no YouTube equivalent (the comparison
 *      page with the concert clip keeps its own link).
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

// --- 2. Footer: LinkedIn after Spotify ---------------------------------------
$blocks = $etm->getStorage('block_content')->loadByProperties(['info' => 'Social and contact block for footer']);
if (!$blocks) {
  print "No 'Social and contact block for footer' block found.\n";
}
else {
  $block = reset($blocks);
  $html = $block->get('field_social_and_contact_markup')->value ?? '';
  if (str_contains($html, 'linkedin.com')) {
    print "Footer: LinkedIn link already there.\n";
  }
  else {
    $html = rtrim($html) . "\n\n<a href=\"https://www.linkedin.com/company/ligchoir\">\n<div class=\"social-contact-circle-outer ligc-li\"><div class=\"social-contact-circle-inner\"><span class=\"fab fa-linkedin\"></span></div></div>\n</a>";
    $block->set('field_social_and_contact_markup', ['value' => $html, 'format' => $block->get('field_social_and_contact_markup')->format]);
    $block->save();
    print "Footer: LinkedIn link added after Spotify.\n";
  }
}

// --- 3. Focal points near the top of the Join us and About hero images -------
$focal = \Drupal::service('focal_point.manager');
$flush = FALSE;
foreach (['/join', '/about'] as $alias) {
  $node = $node_at($alias);
  $hero = $node ? $node->get('field_content')->entity : NULL;
  if (!$hero || $hero->bundle() !== 'hero' || $hero->get('field_image')->isEmpty()) { print "$alias: no hero image.\n"; continue; }
  $file = $hero->get('field_image')->entity->get('field_media_image')->entity;
  if (!$file) { continue; }
  [$width, $height] = getimagesize($file->getFileUri()) ?: [0, 0];
  if (!$width) { continue; }
  $crop = $focal->getCropEntity($file, 'focal_point');
  $before = $crop->isNew() ? NULL : $crop->anchor();
  $focal->saveCropEntity(0.5, 0.12, $width, $height, $crop);
  if ($crop->isNew() || !$before || $before['y'] !== $crop->anchor()['y']) { $flush = TRUE; }
  print "$alias hero image: focal point at 50% / 12% (" . basename($file->getFileUri()) . ")\n";
}
if ($flush) {
  foreach (\Drupal\image\Entity\ImageStyle::loadMultiple() as $style) { $style->flush(); }
  print "Image style derivatives flushed.\n";
}

// --- 4. Front page hero: no YouTube overlay on the reel ----------------------
$front = \Drupal::config('system.site')->get('page.front');
$home = preg_match('~^/node/(\d+)$~', $front, $m) ? $node_storage->load($m[1]) : NULL;
$hero = $home ? $home->get('field_content')->entity : NULL;
if ($hero && $hero->bundle() === 'hero' && !$hero->get('field_video_link')->isEmpty()) {
  $hero->set('field_video_link', NULL);
  $hero->save();
  print "Front page hero: YouTube overlay link removed.\n";
}
else {
  print "Front page hero: no YouTube overlay link (already).\n";
}

drupal_flush_all_caches();
print "Done.\n";
