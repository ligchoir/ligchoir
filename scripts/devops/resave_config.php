<?php

/**
 * @file
 * Re-save active configuration items from the sync directory files.
 *
 * Use when `drush cst` keeps listing items whose only difference is key
 * order: config written by update hooks or imports is saved as trusted
 * data and keeps its key order, while a normal save sorts keys by schema.
 * Re-saving through the config factory applies the schema sort, so active
 * then matches what `drush cex` on another environment produces.
 *
 * Usage (from the repo root):
 *   ./drush php:script scripts/devops/resave_config.php
 *   ./drush php:script scripts/devops/resave_config.php -- webform.settings views.view.events
 *
 * With no names given it re-saves the webform items affected by the
 * September 2026 webform 6.3 update.
 */

use Symfony\Component\Yaml\Yaml;

$names = array_values(array_filter($extra ?? [], fn($a) => $a !== '' && $a[0] !== '-'));
if (!$names) {
  $names = [
    'webform.settings',
    'webform.webform.book_us',
    'webform.webform.contact',
    'webform.webform.join_us',
    'webform.webform.liv_book',
  ];
}

$sync = \Drupal::service('config.storage.sync');
$factory = \Drupal::configFactory();
foreach ($names as $name) {
  $data = $sync->read($name);
  if ($data === FALSE) {
    print "$name: not found in sync directory, skipped\n";
    continue;
  }
  $factory->getEditable($name)->setData($data)->save();
  print "$name: re-saved\n";
}
print "Done. Run: ./drush cr && ./drush cst\n";
