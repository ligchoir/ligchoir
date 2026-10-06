<?php

/**
 * @file
 * Review round 2 of the new theme (2026-10-06): content changes from Rob's
 * second walk-through. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/review_round_2_2026.php
 *
 *   1. Removes the "vertical space" paragraphs from the landing pages (they
 *      showed as black gaps under the hero band: About 10em, Events and
 *      Past events 3em).
 *   2. Join us: the "Our late Summer..." text uses the same wide column as the
 *      text that follows, so the two line up.
 *   3. Book us: the photo credit paragraph becomes the hero's "Photo credit"
 *      (shown small in the corner of the image) and is deleted as a paragraph.
 *   4. Focal points for the Events and Book us hero images near the top of
 *      the frame, so the top row of heads stays in view when the image is
 *      cropped at wide widths; then the image style derivatives are flushed.
 *   5. Every event uses the black colour scheme (white text, pink date line).
 *
 * Needs the theme release's config imported first (field_credit on hero).
 */

use Drupal\paragraphs\Entity\Paragraph;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_manager = \Drupal::service('path_alias.manager');
$node_at = function (string $alias) use ($alias_manager, $node_storage) {
  $path = $alias_manager->getPathByAlias($alias);
  return preg_match('~^/node/(\d+)$~', $path, $m) ? $node_storage->load($m[1]) : NULL;
};
$remove_paragraphs = function ($node, callable $match, string $why) {
  if (!$node) { return []; }
  $kept = $removed = [];
  foreach ($node->get('field_content')->getValue() as $item) {
    $paragraph = Paragraph::load($item['target_id']);
    if ($paragraph && $match($paragraph)) { $removed[] = $paragraph; continue; }
    $kept[] = $item;
  }
  if (!$removed) { return []; }
  $node->set('field_content', $kept);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage($why . ' (scripts/devops/review_round_2_2026.php)');
  $node->save();
  foreach ($removed as $paragraph) { $paragraph->delete(); }
  print count($removed) . " paragraph(s) removed from '" . $node->getTitle() . "': $why\n";
  return $removed;
};

// --- 1. Vertical space paragraphs on landing pages ---------------------------
foreach ($node_storage->loadByProperties(['type' => 'landing_page']) as $node) {
  $remove_paragraphs($node, fn(Paragraph $p) => $p->bundle() === 'vertical_space', 'Vertical space removed (gap under the hero band)');
}

// --- 2. Join us: first text in the wide column ------------------------------
if ($join = $node_at('/join')) {
  foreach ($join->get('field_content')->referencedEntities() as $paragraph) {
    if ($paragraph->bundle() === 'text' && $paragraph->hasField('field_style') && $paragraph->get('field_style')->target_id === 'text_confine_single_column'
      && str_contains($paragraph->get('field_text')->value ?? '', 'recruitment period')) {
      $paragraph->set('field_style', ['target_id' => 'text_confine_single_column_wide']);
      $paragraph->save();
      print "Join us: intro text moved to the wide column.\n";
    }
  }
}

// --- 3. Book us: photo credit into the hero ---------------------------------
if ($book = $node_at('/book')) {
  $hero = $book->get('field_content')->entity;
  if ($hero && $hero->bundle() === 'hero' && $hero->hasField('field_credit')) {
    $credit = NULL;
    foreach ($book->get('field_content')->referencedEntities() as $paragraph) {
      if ($paragraph->bundle() === 'text' && preg_match('~Credit:\s*(.+?)\s*</~i', $paragraph->get('field_text')->value ?? '', $m)) {
        $credit = 'Photo: ' . trim(strip_tags($m[1]));
      }
    }
    if ($credit) {
      $hero->set('field_credit', $credit);
      $hero->save();
      $remove_paragraphs($book, fn(Paragraph $p) => $p->bundle() === 'text' && stripos($p->get('field_text')->value ?? '', 'Credit:') !== FALSE, "Photo credit moved into the hero ($credit)");
    }
  }
  else {
    print "Book us: hero has no field_credit yet; import config first.\n";
  }
}

// --- 4. Focal points near the top of the Events and Book us hero images ------
$focal = \Drupal::service('focal_point.manager');
$flush = FALSE;
foreach (['/events', '/book'] as $alias) {
  $node = $node_at($alias);
  $hero = $node ? $node->get('field_content')->entity : NULL;
  if (!$hero || $hero->bundle() !== 'hero' || $hero->get('field_image')->isEmpty()) { continue; }
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

// --- 5. Events on the black scheme -------------------------------------------
$changed = 0;
foreach ($node_storage->loadByProperties(['type' => 'event']) as $event) {
  if ($event->hasField('field_colour_scheme') && $event->get('field_colour_scheme')->value !== 'ink') {
    $event->set('field_colour_scheme', 'ink');
    $event->setNewRevision(FALSE);
    $event->save();
    $changed++;
  }
}
print "Events moved to the black scheme: $changed.\n";

drupal_flush_all_caches();
print "Done.\n";
