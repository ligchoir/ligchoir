<?php

/**
 * @file
 * Move the musical team from taxonomy terms to team_member nodes.
 *
 *   ./drush php:script scripts/devops/musical_team_to_nodes.php -- capture
 *   ./drush php:script scripts/devops/musical_team_to_nodes.php -- apply
 *   ./drush php:script scripts/devops/musical_team_to_nodes.php            (both, terms kept)
 *
 * The release that adds the team_member content type also removes the
 * musical_team vocabulary, and config import refuses to delete a vocabulary
 * that still has terms. So the work is split around the import
 * (scripts/devops/post_deploy.sh runs both halves):
 *
 *   capture  Saves every musical_team term (name, role, photo media id,
 *            description, weight) to Drupal state, then deletes the terms.
 *            Nothing to do when the vocabulary or its terms are already gone.
 *   apply    After the import: creates one published team_member node per
 *            captured term (order = weight), skipping names that already
 *            exist as team_member nodes, points every "view" paragraph that
 *            embedded the old musical_team view at team_members /
 *            team_members_block, and clears the captured state.
 *
 * With no argument it captures without deleting and then applies; that is
 * for a site where the type already exists alongside the vocabulary.
 * All of it is safe to run repeatedly.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;

const STATE_KEY = 'ligc.musical_team_capture';

$mode = $extra[0] ?? 'all';
if (!in_array($mode, ['capture', 'apply', 'all'], TRUE)) {
  print "Usage: musical_team_to_nodes.php -- capture|apply\n";
  return;
}
$state = \Drupal::state();
$entity_type_manager = \Drupal::entityTypeManager();
$term_storage = $entity_type_manager->getStorage('taxonomy_term');

if ($mode !== 'apply') {
  if (!Vocabulary::load('musical_team')) {
    print "capture: vocabulary musical_team is already gone; nothing to capture.\n";
  }
  else {
    $terms = $term_storage->loadTree('musical_team', 0, NULL, TRUE);
    if (!$terms) {
      print "capture: vocabulary has no terms; nothing to capture.\n";
    }
    else {
      $captured = [];
      foreach ($terms as $term) {
        $captured[] = [
          'name' => $term->getName(),
          'role' => $term->get('field_role')->value,
          'image' => $term->get('field_image')->target_id,
          'description' => $term->getDescription() ?? '',
          'format' => $term->get('description')->format ?: 'basic_html',
          'weight' => (int) $term->getWeight(),
        ];
      }
      $state->set(STATE_KEY, $captured);
      print "capture: " . count($captured) . " term(s) saved to state.\n";
      if ($mode === 'capture') {
        $term_storage->delete($terms);
        print "capture: terms deleted so config import can remove the vocabulary.\n";
      }
    }
  }
}

if ($mode !== 'capture') {
  if (!$entity_type_manager->getStorage('node_type')->load('team_member')) {
    print "apply: ERROR the team_member content type does not exist; import config first (drush cim).\n";
    return;
  }
  $node_storage = $entity_type_manager->getStorage('node');
  $captured = $state->get(STATE_KEY, []);
  $created = $skipped = 0;
  foreach ($captured as $person) {
    if ($node_storage->loadByProperties(['type' => 'team_member', 'title' => $person['name']])) {
      print "apply: skip " . $person['name'] . " (node exists)\n";
      $skipped++;
      continue;
    }
    $node = Node::create([
      'type' => 'team_member',
      'title' => $person['name'],
      'uid' => 1,
      'status' => 1,
      'field_role' => $person['role'],
      'field_image' => $person['image'],
      'field_order' => $person['weight'],
      'body' => ['value' => $person['description'], 'format' => $person['format']],
    ]);
    $node->save();
    print "apply: created " . $person['name'] . " -> node " . $node->id() . " (order " . $person['weight'] . ")\n";
    $created++;
  }
  print "apply: $created node(s) created, $skipped already present, " . count($captured) . " captured.\n";
  if (!$captured) {
    $existing = $node_storage->loadByProperties(['type' => 'team_member']);
    print "apply: nothing captured; " . count($existing) . " team_member node(s) already exist.\n";
  }

  $updated = 0;
  foreach ($entity_type_manager->getStorage('paragraph')->loadByProperties(['type' => 'view']) as $paragraph) {
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
    print "apply: paragraph " . $paragraph->id() . " now embeds team_members/team_members_block (on " . ($parent ? $parent->getEntityTypeId() . ' ' . $parent->id() : 'orphan') . ")\n";
    $updated++;
  }
  print "apply: $updated paragraph(s) re-pointed.\n";
  $state->delete(STATE_KEY);
  drupal_flush_all_caches();
  print "apply: caches rebuilt. Check /about.\n";
}
