<?php

/**
 * @file
 * Review round 1 of the new theme (2026-10-06): content changes from Rob's
 * walk-through of the site. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/review_round_1_2026.php
 *
 *   1. Removes the round "button" paragraphs under the heroes: General
 *      enquiry (Home), What's on (Events), Contact us (Join us), Make an
 *      enquiry (Book us). The hero's own link and the footer cover them.
 *   2. Removes the "Privacy notice | Proudly open source" footnote paragraph
 *      from About us: the footer carries those links on every page.
 *   3. Marks the front page's "More about us and the team" link as a "more"
 *      link (class ligc-more: bold with a chevron, like the hero's video link).
 *   4. Footer policy links in the "footer" menu: Contact us, Terms of use,
 *      Privacy policy, Accessibility. Terms of use and Accessibility have no
 *      page yet, so a published landing page with a one-line placeholder is
 *      created for each (/terms-of-use, /accessibility) for the team to write.
 *
 * Needs the theme release's config imported first (the footer menu blocks).
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_manager = \Drupal::service('path_alias.manager');

/** Node behind a path alias, or NULL. */
$node_at = function (string $alias) use ($alias_manager, $node_storage): ?Node {
  $path = $alias_manager->getPathByAlias($alias);
  return preg_match('~^/node/(\d+)$~', $path, $m) ? $node_storage->load($m[1]) : NULL;
};

/** Remove paragraphs from a node's field_content where $match($paragraph) is TRUE. */
$remove_paragraphs = function (?Node $node, callable $match, string $why) {
  if (!$node) { return; }
  $items = $node->get('field_content')->getValue();
  $kept = [];
  $removed = [];
  foreach ($items as $item) {
    $paragraph = Paragraph::load($item['target_id']);
    if ($paragraph && $match($paragraph)) { $removed[] = $paragraph; continue; }
    $kept[] = $item;
  }
  if (!$removed) { return; }
  $node->set('field_content', $kept);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage($why . ' (scripts/devops/review_round_1_2026.php)');
  $node->save();
  foreach ($removed as $paragraph) { $paragraph->delete(); }
  print count($removed) . " paragraph(s) removed from '" . $node->getTitle() . "': $why\n";
};

// --- 1. Round buttons under the heroes --------------------------------------
$buttons = ['General enquiry', "What's on", 'Contact us', 'Make an enquiry'];
$is_button = fn(Paragraph $p) => $p->bundle() === 'call_to_action' && !$p->get('field_link')->isEmpty() && in_array($p->get('field_link')->title, $buttons, TRUE);
$homes = $node_storage->loadByProperties(['type' => 'home']);
$remove_paragraphs($homes ? reset($homes) : NULL, $is_button, 'General enquiry button removed');
foreach (['/events' => "What's on", '/join' => 'Contact us', '/book' => 'Make an enquiry'] as $alias => $title) {
  $remove_paragraphs($node_at($alias), $is_button, "$title button removed");
}

// --- 2. Footnote on About us --------------------------------------------------
$remove_paragraphs($node_at('/about'), fn(Paragraph $p) => $p->bundle() === 'text' && !$p->get('field_text')->isEmpty() && str_contains($p->get('field_text')->value, 'Proudly open source'), 'Privacy / open source footnote removed (now in the footer)');

// --- 3. "More about us" link on the front page -------------------------------
if ($homes) {
  foreach (reset($homes)->get('field_content')->referencedEntities() as $paragraph) {
    if ($paragraph->bundle() !== 'text' || $paragraph->get('field_text')->isEmpty()) { continue; }
    $item = $paragraph->get('field_text')->first();
    if (!str_contains($item->value, 'ligc-front-about') || str_contains($item->value, 'ligc-more')) { continue; }
    $value = preg_replace('~<a href="(/about[^"]*)">(More about us[^<]*)</a>~', '<a class="ligc-more" href="$1">$2</a>', $item->value, 1, $count);
    if ($count) {
      $paragraph->set('field_text', ['value' => $value, 'format' => $item->format]);
      $paragraph->save();
      print "Front page: 'More about us' link marked as a more-link.\n";
    }
  }
}

// --- 4. Footer policy links -------------------------------------------------
/** Landing page with a placeholder text, created once. */
$placeholder_page = function (string $title, string $alias) use ($node_storage, $node_at): Node {
  if ($node = $node_at($alias)) { return $node; }
  $existing = $node_storage->loadByProperties(['type' => 'landing_page', 'title' => $title]);
  if ($existing) { return reset($existing); }
  $text = Paragraph::create([
    'type' => 'text',
    'field_text' => ['value' => '<p>This page is being written. In the meantime, please <a href="/about#general-contact">contact us</a> with any questions.</p>', 'format' => 'basic_html'],
    'field_style' => ['target_id' => 'text_confine_single_column_wide'],
  ]);
  // Black ground, white text, like the Projects intro (colour terms by name).
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  foreach (['field_colour_background' => 'black', 'field_colour_text' => 'white'] as $field => $name) {
    if ($text->hasField($field) && ($term = current($terms->loadByProperties(['name' => $name])))) { $text->set($field, ['target_id' => $term->id()]); }
  }
  $text->save();
  $node = Node::create([
    'type' => 'landing_page',
    'title' => $title,
    'uid' => 1,
    'status' => 1,
    'field_content' => [['target_id' => $text->id(), 'target_revision_id' => $text->getRevisionId()]],
    'path' => ['alias' => $alias, 'pathauto' => 0],
  ]);
  $node->save();
  print "Placeholder page '$title' created at $alias (node " . $node->id() . ") for the team to write.\n";
  return $node;
};

$privacy = $node_at('/privacy');
$links = [
  ['Contact us', 'internal:/about#general-contact', -50],
  ['Terms of use', 'entity:node/' . $placeholder_page('Terms of use', '/terms-of-use')->id(), -49],
  ['Privacy policy', $privacy ? 'entity:node/' . $privacy->id() : 'internal:/privacy', -48],
  ['Accessibility', 'entity:node/' . $placeholder_page('Accessibility', '/accessibility')->id(), -47],
];
$link_storage = $etm->getStorage('menu_link_content');
foreach ($links as [$title, $uri, $weight]) {
  $existing = $link_storage->loadByProperties(['menu_name' => 'footer', 'title' => $title]);
  if ($existing) {
    $link = reset($existing);
    if ($link->link->uri !== $uri || (int) $link->getWeight() !== $weight) { $link->set('link', ['uri' => $uri]); $link->set('weight', $weight); $link->save(); print "Footer link '$title' updated.\n"; }
    continue;
  }
  MenuLinkContent::create(['title' => $title, 'link' => ['uri' => $uri], 'menu_name' => 'footer', 'weight' => $weight, 'expanded' => FALSE])->save();
  print "Footer link '$title' added.\n";
}

drupal_flush_all_caches();
print "Done.\n";
