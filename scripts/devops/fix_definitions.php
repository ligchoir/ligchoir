<?php

/**
 * @file
 * Repair the things that make a config import fail or the status report
 * complain after a deploy. Safe to run repeatedly; post_deploy.sh runs it
 * before and after the import.
 *
 *   ./drush php:script scripts/devops/fix_definitions.php
 *
 * 1. Modules still listed in core.extension whose code is no longer in the
 *    release (a module removed from composer before it was uninstalled, or
 *    one removed from core: tour). Drupal cannot uninstall them without their
 *    code; "drush cim" dies with "The module X does not exist" and the status
 *    report shows "Missing or invalid modules". They are removed from
 *    core.extension and from the system.schema key-value store, which is the
 *    documented repair. Their leftover config (X.settings) is removed by the
 *    following config import.
 * 2. Stale system.schema entries for modules not on disk (same cause).
 * 3. Entity types "pending installation" and hook_schema tables that do not
 *    exist: a module listed as installed whose hook_install never ran (klaro
 *    and honeypot on staging). The entity type is installed and the missing
 *    tables created from their declared definitions.
 * 4. "Entity/field definitions: Mismatched" where the only change is that a
 *    field storage definition "needs to be updated" (stored metadata differs,
 *    the table does not; taxonomy_term.field_role after the Drupal 11
 *    upgrade). The definition is re-saved. Other kinds of change are printed
 *    and left alone, as they can need data migrations.
 */

// reset() rescans the file system; the cached list can still name a module
// whose code was removed by the composer install that preceded this run.
$on_disk = array_keys(\Drupal::service('extension.list.module')->reset()->getList());
$extension = \Drupal::configFactory()->getEditable('core.extension');
$installed = $extension->get('module') ?: [];
$gone = array_diff(array_keys($installed), $on_disk);
if ($gone) {
  foreach ($gone as $module) {
    unset($installed[$module]);
    \Drupal::keyValue('system.schema')->delete($module);
    print "Removed '$module' from core.extension and system.schema (listed as installed, code not in this release).\n";
  }
  $extension->set('module', $installed)->save();
  drupal_flush_all_caches();
}
else {
  print "core.extension: every installed module is present on disk.\n";
}

$manager = \Drupal::entityDefinitionUpdateManager();
$field_manager = \Drupal::service('entity_field.manager');
$changes = $manager->getChangeList();
if (!$changes) {
  print "Entity/field definitions: no changes pending.\n";
}
foreach ($changes as $entity_type_id => $change) {
  if (($change['entity_type'] ?? NULL) === $manager::DEFINITION_CREATED) {
    // A module marked installed whose hook_install never ran (klaro on
    // staging): the entity type is known to the code but was never
    // registered. Installing it is what the installer would have done.
    $manager->installEntityType(\Drupal::entityTypeManager()->getDefinition($entity_type_id));
    print "Installed entity type $entity_type_id (was pending installation).\n";
  }
  elseif (!empty($change['entity_type'])) {
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

// Tables declared by an installed module's hook_schema that do not exist
// (same cause: the module was marked installed without hook_install running;
// honeypot_user on staging). Created from the declared definition.
$module_handler = \Drupal::moduleHandler();
$db_schema = \Drupal::database()->schema();
$created_tables = 0;
foreach (array_keys($module_handler->getModuleList()) as $module) {
  $module_handler->loadInclude($module, 'install');
  foreach ($module_handler->invoke($module, 'schema') ?: [] as $table => $spec) {
    if (!$db_schema->tableExists($table)) {
      $db_schema->createTable($table, $spec);
      print "Created missing table $table declared by $module.\n";
      $created_tables++;
    }
  }
}
if (!$created_tables) {
  print "hook_schema tables: all present.\n";
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
