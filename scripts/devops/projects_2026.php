<?php

/**
 * @file
 * Projects section (plan step 5): the Projects landing page and L•I•V as the
 * first project. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/projects_2026.php
 *
 * Needs the config that defines the project and collaborator content types
 * and the "projects" view (imported with the release). Then:
 *
 *   1. Creates the landing page "Projects" at /projects (an intro text and the
 *      projects view) unless a node already has that alias.
 *   2. Turns the "London International Voices" landing page (/liv) into a
 *      project: same title, status active, image from the old page's
 *      background media, description from its text paragraph, the page's
 *      paragraphs copied into the project's page content, and the /liv alias
 *      moved across (Pathauto left off for that node so it keeps /liv). The
 *      old landing page is then deleted.
 *   3. Points the front page's "Our projects" card at /projects.
 *
 * Run scripts/devops/menu_2026.php afterwards: once /projects exists it
 * replaces the L• I• V menu item with Projects.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_repo = \Drupal::service('path_alias.repository');
foreach (['project', 'collaborator'] as $type) {
  if (!$etm->getStorage('node_type')->load($type)) {
    print "ERROR: content type $type missing; import config first (drush cim).\n";
    return;
  }
}

// --- 1. Projects landing page ------------------------------------------------
$landing = NULL;
if ($alias = $alias_repo->lookupByAlias('/projects', 'en')) {
  if (preg_match('#^/node/(\d+)$#', $alias['path'], $m)) {
    $landing = Node::load($m[1]);
  }
}
if (!$landing) {
  $intro = Paragraph::create([
    'type' => 'text',
    'field_text' => ['value' => '<div class="ligc-front-about"><h2>Our projects</h2><p>Alongside our concerts, LIGC works on longer strands: a professional vocal collective, recordings, and performances with other choirs and partners. Here is what we are working on and what we have made.</p></div>', 'format' => 'full_html'],
    'field_colour_background' => ['target_id' => 11],
    'field_colour_text' => ['target_id' => 10],
    'field_style' => ['target_id' => 'text_confine_single_column_wide'],
  ]);
  $intro->save();
  $view = Paragraph::create([
    'type' => 'view',
    'field_views_display' => ['target_id' => 'projects', 'display_id' => 'projects_block', 'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL])],
  ]);
  $view->set('field_colour_background', NULL)->set('field_colour_border', NULL)->set('field_colour_text', NULL);
  $view->save();
  $landing = Node::create([
    'type' => 'landing_page',
    'title' => 'Projects',
    'uid' => 1,
    'status' => 1,
    'field_content' => [
      ['target_id' => $intro->id(), 'target_revision_id' => $intro->getRevisionId()],
      ['target_id' => $view->id(), 'target_revision_id' => $view->getRevisionId()],
    ],
    'path' => ['alias' => '/projects', 'pathauto' => 0],
  ]);
  $landing->save();
  print "landing page Projects created: node " . $landing->id() . " at /projects\n";
}
else {
  print "landing page /projects exists: node " . $landing->id() . "\n";
}

// --- 2. L•I•V as a project ---------------------------------------------------
$liv = NULL;
if ($alias = $alias_repo->lookupByAlias('/liv', 'en')) {
  if (preg_match('#^/node/(\d+)$#', $alias['path'], $m)) {
    $liv = Node::load($m[1]);
  }
}
if ($liv && $liv->bundle() === 'project') {
  print "L•I•V is already a project: node " . $liv->id() . "\n";
}
elseif ($liv && $liv->bundle() === 'landing_page') {
  $description = '';
  $copies = [];
  foreach ($liv->get('field_content')->referencedEntities() as $paragraph) {
    if ($paragraph->bundle() === 'text' && $description === '') {
      // The first text paragraph becomes the description; the rest of the
      // page (the booking form etc.) stays as page content.
      $description = (string) $paragraph->get('field_text')->value;
      continue;
    }
    if ($paragraph->bundle() === 'vertical_space') {
      continue;
    }
    $copy = $paragraph->createDuplicate();
    $copy->save();
    $copies[] = ['target_id' => $copy->id(), 'target_revision_id' => $copy->getRevisionId()];
  }
  $project = Node::create([
    'type' => 'project',
    'title' => $liv->getTitle(),
    'uid' => 1,
    'status' => 1,
    'field_status' => 'active',
    'field_image' => $liv->get('field_background_media')->target_id ? ['target_id' => $liv->get('field_background_media')->target_id] : NULL,
    'body' => ['value' => $description, 'format' => 'full_html'],
    'field_content' => $copies,
    'path' => ['alias' => '/liv', 'pathauto' => 0],
  ]);
  // Free the alias first: the landing page goes, the project takes /liv.
  $old_id = $liv->id();
  $liv->delete();
  $project->save();
  print "L•I•V: landing page $old_id replaced by project node " . $project->id() . " at /liv with " . count($copies) . " page paragraph(s)\n";
}
else {
  print "WARNING: no /liv page found to convert.\n";
}

// --- 3. Front page card ------------------------------------------------------
$updated = 0;
foreach ($etm->getStorage('paragraph')->loadByProperties(['type' => 'card']) as $card) {
  $link = $card->get('field_link')->first();
  if ($link && $link->uri === 'internal:/liv' && $card->get('field_heading')->value === 'Our projects') {
    $card->set('field_link', ['uri' => 'internal:/projects', 'title' => $link->title]);
    $card->setNewRevision(FALSE);
    $card->save();
    $updated++;
  }
}
print "front page cards pointed at /projects: $updated\n";
drupal_flush_all_caches();
print "Done. Check /projects and /liv, then run scripts/devops/menu_2026.php.\n";
