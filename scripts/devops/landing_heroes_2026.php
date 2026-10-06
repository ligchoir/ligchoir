<?php

/**
 * @file
 * Give every landing page a hero paragraph (plan step 6). Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/landing_heroes_2026.php
 *
 * Each landing page used to paint its background media across the whole page
 * from the template. Now the same image becomes a hero paragraph at the top
 * of the page's content, with the page title as the heading, rendered
 * through the responsive image styles. Pages that already start with a hero
 * are left alone; pages without a background image get no hero.
 *
 * It also demotes <h1> to <h2> inside paragraph text fields across the site
 * (text, intro, guidance, quote fields): the hero or the page title is the
 * one H1 on a page, and several editor texts carried their own.
 */

use Drupal\paragraphs\Entity\Paragraph;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$added = $kept = $skipped = 0;
foreach ($storage->loadByProperties(['type' => 'landing_page']) as $node) {
  $items = $node->get('field_content')->getValue();
  $first = $node->get('field_content')->entity;
  if ($first && $first->bundle() === 'hero') { $kept++; continue; }
  $media_id = $node->get('field_background_media')->target_id;
  if (!$media_id) { print "no background image on '" . $node->getTitle() . "', skipped\n"; $skipped++; continue; }
  $hero = Paragraph::create([
    'type' => 'hero',
    'field_heading' => $node->getTitle(),
    'field_image' => ['target_id' => $media_id],
  ]);
  $hero->save();
  array_unshift($items, ['target_id' => $hero->id(), 'target_revision_id' => $hero->getRevisionId()]);
  $node->set('field_content', $items);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Hero paragraph added from the background image (scripts/devops/landing_heroes_2026.php).');
  $node->save();
  print "hero added to '" . $node->getTitle() . "' (" . $node->toUrl()->toString() . ")\n";
  $added++;
}
print "Landing pages: $added hero(es) added, $kept already had one, $skipped without an image.\n";

// Editor text with its own <h1> becomes <h2>: one H1 per page.
$pstorage = \Drupal::entityTypeManager()->getStorage('paragraph');
$demoted = 0;
foreach ($pstorage->loadMultiple() as $paragraph) {
  $changed = FALSE;
  foreach (['field_text', 'field_intro', 'field_guidance', 'field_quote', 'field_content_after_webform'] as $field) {
    if (!$paragraph->hasField($field) || $paragraph->get($field)->isEmpty()) { continue; }
    $item = $paragraph->get($field)->first();
    if (!isset($item->value) || stripos($item->value, '<h1') === FALSE) { continue; }
    $value = preg_replace('/<(\/?)h1(\s|>)/i', '<$1h2$2', $item->value);
    $paragraph->set($field, ['value' => $value, 'format' => $item->format]);
    $changed = TRUE;
  }
  if ($changed) { $paragraph->setNewRevision(FALSE); $paragraph->save(); $demoted++; }
}
print "Paragraphs with an <h1> demoted to <h2>: $demoted.\n";
drupal_flush_all_caches();
