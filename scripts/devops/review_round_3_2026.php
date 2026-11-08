<?php

/**
 * @file
 * Review round 3 of the new theme (2026-11-08): content changes behind the
 * "new site" GitHub issues #39 to #49. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/review_round_3_2026.php
 *
 *   1. The Events page is "Events & News" (#47, #48): node title, hero
 *      heading, and a new alias /events-news. The old /events alias is kept
 *      as a second alias so links in the wild still work (no redirect module
 *      on the site); the newer alias is the one Drupal prints.
 *   2. The main menu's "Events & News" item points at /events-news.
 *   3. Events page: the "What's on?" text moves from the narrow centred
 *      column to the wide column, so it lines up with the Upcoming / Past /
 *      Map strip (#39). Book us keeps its narrow centred statement (#41).
 *   4. Events page: the view paragraph that embeds the listing loses its
 *      colour pickers (red box with a white panel), so the cards sit on the
 *      black page exactly as on the front page (#49).
 *
 * Needs the theme release's config imported first (events view: cards on the
 * Events & News page, heading and link on the front page).
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\path_alias\Entity\PathAlias;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_storage = $etm->getStorage('path_alias');
$alias_manager = \Drupal::service('path_alias.manager');
$node_at = function (string $alias) use ($alias_manager, $node_storage) {
  $path = $alias_manager->getPathByAlias($alias);
  return preg_match('~^/node/(\d+)$~', $path, $m) ? $node_storage->load($m[1]) : NULL;
};

// --- 1. Events -> Events & News, at /events-news ------------------------------
$events = $node_at('/events-news') ?: $node_at('/events');
if (!$events) {
  print "No Events landing page found at /events or /events-news.\n";
}
else {
  $changed = FALSE;
  if ($events->getTitle() !== 'Events & News') {
    $events->setTitle('Events & News');
    $changed = TRUE;
  }
  $hero = $events->get('field_content')->entity;
  if ($hero && $hero->bundle() === 'hero' && $hero->get('field_heading')->value !== 'Events & News') {
    $hero->set('field_heading', 'Events & News');
    $hero->save();
    print "Events page: hero heading is now 'Events & News'.\n";
  }
  if ($changed) {
    $events->setNewRevision(TRUE);
    $events->setRevisionLogMessage('Title: Events & News (scripts/devops/review_round_3_2026.php)');
    $events->save();
    print "Events page: title is now 'Events & News'.\n";
  }
  $system_path = '/node/' . $events->id();
  $has_new = (bool) $alias_storage->loadByProperties(['path' => $system_path, 'alias' => '/events-news']);
  if (!$has_new) {
    // Created after /events, so it has the higher id and is the alias
    // Drupal prints for the node; /events keeps resolving to the node.
    PathAlias::create(['path' => $system_path, 'alias' => '/events-news', 'langcode' => 'en'])->save();
    print "Events page: alias /events-news added (/events kept as a second alias).\n";
  }
}

// --- 2. Main menu item -> /events-news ---------------------------------------
foreach ($etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main']) as $link) {
  /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $link */
  $uri = $link->get('link')->uri ?? '';
  if (in_array($uri, ['internal:/events', 'internal:/events/'], TRUE)) {
    $link->set('link', ['uri' => 'internal:/events-news', 'title' => '', 'options' => []]);
    $link->save();
    print "Main menu: '" . $link->getTitle() . "' now points at /events-news.\n";
  }
}

// --- 3. Events page: "What's on?" in the wide column --------------------------
if ($events) {
  foreach ($events->get('field_content')->referencedEntities() as $paragraph) {
    if ($paragraph->bundle() === 'text' && $paragraph->hasField('field_style')
      && $paragraph->get('field_style')->target_id === 'text_confine_single_column'
      && stripos($paragraph->get('field_text')->value ?? '', "What's on") !== FALSE) {
      $paragraph->set('field_style', ['target_id' => 'text_confine_single_column_wide']);
      $paragraph->save();
      print "Events page: \"What's on?\" text moved to the wide column.\n";
    }
  }
}

// --- 4. Events page: the listing's view paragraph without colour pickers -----
if ($events) {
  foreach ($events->get('field_content')->referencedEntities() as $paragraph) {
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
      print "Events page: colour pickers cleared from the events view paragraph (cards on black, as on the front page).\n";
    }
  }
}

drupal_flush_all_caches();
print "Done.\n";
