<?php

/**
 * @file
 * Move the musical team from taxonomy terms to team_member nodes.
 *
 *   ./drush php:script scripts/devops/musical_team_to_nodes.php
 *
 * Needs the config that defines the team_member content type and the
 * "Team members" view (imported with the release). Safe to run repeatedly:
 * a term whose name already exists as a team_member node is skipped, and a
 * "view" paragraph that already points at the new view is left alone.
 *
 * 1. Each term in the musical_team vocabulary, in its admin order (weight),
 *    becomes a published team_member node: name -> title, role, photo
 *    (media reference), description -> biography, weight -> order.
 * 2. Every "view" paragraph that embeds the old musical_team view (the one
 *    on the About page) is pointed at team_members / team_members_block.
 *
 * The vocabulary, its fields and the old view are removed by the following
 * release, after this has run everywhere.
 */

use Drupal\node\Entity\Node;

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

if (!\Drupal::entityTypeManager()->getStorage('node_type')->load('team_member')) {
  print "ERROR: the team_member content type does not exist; import config first (drush cim).\n";
  return;
}

$terms = $term_storage->loadTree('musical_team', 0, NULL, TRUE);
$created = $skipped = 0;
foreach ($terms as $term) {
  $existing = $node_storage->loadByProperties(['type' => 'team_member', 'title' => $term->getName()]);
  if ($existing) {
    print "skip   " . $term->getName() . " (node " . reset($existing)->id() . " exists)\n";
    $skipped++;
    continue;
  }
  $node = Node::create([
    'type' => 'team_member',
    'title' => $term->getName(),
    'uid' => 1,
    'status' => 1,
    'field_role' => $term->get('field_role')->value,
    'field_image' => $term->get('field_image')->target_id,
    'field_order' => (int) $term->getWeight(),
    'body' => [
      'value' => $term->getDescription() ?? '',
      'format' => $term->get('description')->format ?: 'basic_html',
    ],
  ]);
  $node->save();
  print "create " . $term->getName() . " -> node " . $node->id() . " (order " . $term->getWeight() . ")\n";
  $created++;
}
print "Terms: $created node(s) created, $skipped already present, " . count($terms) . " in the vocabulary.\n";

$updated = 0;
foreach ($paragraph_storage->loadByProperties(['type' => 'view']) as $paragraph) {
  $item = $paragraph->get('field_views_display')->first();
  if (!$item || $item->target_id !== 'musical_team') {
    continue;
  }
  $value = $item->getValue();
  $value['target_id'] = 'team_members';
  $value['display_id'] = 'team_members_block';
  $paragraph->set('field_views_display', [$value]);
  $paragraph->setNewRevision(FALSE);
  $paragraph->save();
  $parent = $paragraph->getParentEntity();
  print "paragraph " . $paragraph->id() . " now embeds team_members/team_members_block (on " . ($parent ? $parent->getEntityTypeId() . ' ' . $parent->id() : 'orphan') . ")\n";
  $updated++;
}
print "Paragraphs updated: $updated.\n";

drupal_flush_all_caches();
print "Caches rebuilt. Check /about.\n";
