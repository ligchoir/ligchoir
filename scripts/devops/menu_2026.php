<?php

/**
 * @file
 * Main menu for the new front end (plan decision 16.2). Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/menu_2026.php
 *
 * Order: Home, Events & News, Join us, Book us, About, Projects (Search is
 * the theme's own button). Menu links are content, not config, hence a
 * script. "Projects" replaces "L• I• V" only once /projects exists
 * (plan step 5); until then that item is left as it is.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$links = $storage->loadByProperties(['menu_name' => 'main']);
$by_uri = [];
foreach ($links as $link) {
  $by_uri[$link->get('link')->uri] = $link;
}

// "Home" is the standard profile's own static link (standard.front_page);
// give it the first slot through a static override instead of a second
// content link.
\Drupal::service('menu_link.static.overrides')->saveOverride('standard.front_page', ['menu_name' => 'main', 'parent' => '', 'weight' => -50, 'expanded' => FALSE, 'enabled' => TRUE]);
print "ok      Home (standard.front_page) weight -50\n";
foreach ($storage->loadByProperties(['menu_name' => 'main', 'title' => 'Home']) as $dup) {
  $dup->delete();
  print "removed duplicate content link 'Home'\n";
}
unset($by_uri['internal:/']);

$wanted = [
  // uri => [title, weight]
  'internal:/events' => ['Events & News', -49],
  'internal:/join' => ['Join us', -48],
  'internal:/book' => ['Book us', -47],
  'internal:/about' => ['About', -46],
];
$projects_exists = (bool) \Drupal::service('path_alias.repository')->lookupByAlias('/projects', 'en');
if ($projects_exists) {
  $wanted['internal:/projects'] = ['Projects', -45];
}

foreach ($wanted as $uri => [$title, $weight]) {
  if (isset($by_uri[$uri])) {
    $link = $by_uri[$uri];
    $changed = $link->getTitle() !== $title || (int) $link->getWeight() !== $weight;
    $link->set('title', $title)->set('weight', $weight)->set('enabled', TRUE)->save();
    print ($changed ? 'updated ' : 'ok      ') . "$title -> $uri (weight $weight)\n";
  }
  else {
    MenuLinkContent::create(['title' => $title, 'link' => ['uri' => $uri], 'menu_name' => 'main', 'weight' => $weight, 'expanded' => FALSE])->save();
    print "created $title -> $uri (weight $weight)\n";
  }
}
// L•I•V becomes Projects when /projects exists.
if (isset($by_uri['internal:/liv'])) {
  $liv = $by_uri['internal:/liv'];
  if ($projects_exists) {
    $liv->delete();
    print "removed the L•I•V item (Projects now links to /projects, and /liv is a project page)\n";
  }
  else {
    $liv->set('weight', -45)->save();
    print "kept     " . $liv->getTitle() . " -> /liv (weight -45) until /projects exists\n";
  }
}
\Drupal::service('plugin.manager.menu.link')->rebuild();
drupal_flush_all_caches();
