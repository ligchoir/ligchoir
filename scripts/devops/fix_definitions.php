<?php

/**
 * @file
 * Clear two status-report leftovers after a deploy. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/fix_definitions.php
 *
 * 1. "Entity/field definitions: Mismatched" where the only change is that a
 *    field storage definition "needs to be updated" (stored metadata differs,
 *    the table schema does not; seen for taxonomy_term.field_role after the
 *    Drupal 11 upgrade). The definition is re-saved. Any other kind of change
 *    (new or deleted field, entity type change) is printed and left alone,
 *    because those can need data migrations.
 * 2. "The following module is missing from the file system" style warnings
 *    from drush, caused by system.schema key-value entries for modules whose
 *    code is gone (tour after Drupal 11, content_sync earlier). The stale
 *    entries are removed.
 */

$manager = \Drupal::entityDefinitionUpdateManager();
$field_manager = \Drupal::service('entity_field.manager');
$changes = $manager->getChangeList();
if (!$changes) {
  print "Entity/field definitions: no changes pending.\n";
}
foreach ($changes as $entity_type_id => $change) {
  if (!empty($change['entity_type'])) {
    print "LEFT ALONE: entity type $entity_type_id has a pending entity type change (" . $change['entity_type'] . ").\n";
  }
  foreach ($change['field_storage_definitions'] ?? [] as $field_name => $kind) {
    if ($kind === $manager::DEFINITION_UPDATED) {
      $definition = $field_manager->getFieldStorageDefinitions($entity_type_id)[$field_name] ?? NULL;
      if ($definition) {
        $manager->updateFieldStorageDefinition($definition);
        print "Re-saved field storage definition $entity_type_id.$field_name.\n";
        continue;
      }
    }
    print "LEFT ALONE: $entity_type_id.$field_name has a pending change of kind $kind.\n";
  }
}

$schema = \Drupal::keyValue('system.schema');
$known = array_keys(\Drupal::service('extension.list.module')->getList());
$removed = 0;
foreach (array_keys($schema->getAll()) as $module) {
  if (!in_array($module, $known, TRUE)) {
    $schema->delete($module);
    print "Removed stale system.schema entry for '$module' (no such module on disk).\n";
    $removed++;
  }
}
if (!$removed) {
  print "system.schema: no stale entries.\n";
}

drupal_flush_all_caches();
print "Caches rebuilt. Check: ./drush core:requirements --severity=2\n";
