<?php

/**
 * @file
 * Fill in the location of every venue that has none, via OpenStreetMap's
 * Nominatim geocoder (Geolocation module plugin).
 *
 *   ./drush php:script scripts/devops/geocode_venues.php
 *   ./drush php:script scripts/devops/geocode_venues.php -- --redo    (geocode all venues again)
 *
 * The query is the venue's "Address for the map" field when set, otherwise
 * the venue name followed by ", UK". Nominatim's usage policy asks for at
 * most one request a second and an identifying email, which the plugin takes
 * from the site mail; the script waits 1.2 s between venues. Venues it
 * cannot place are listed at the end: give them an address in the vocabulary
 * and run again. Safe to run repeatedly; only empty locations are filled.
 */

$redo = in_array('--redo', $extra ?? [], TRUE);
$geocoder = \Drupal::service('plugin.manager.geolocation.geocoder')->createInstance('nominatim');
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'venues']);
$done = $skipped = 0;
$failed = [];
foreach ($terms as $term) {
  if (!$redo && !$term->get('field_location')->isEmpty()) { $skipped++; continue; }
  $address = trim((string) $term->get('field_address')->value);
  $name = $term->getName();
  // Queries from most to least specific. Venue names on this site carry
  // notes ("- nr. One New Change entrance", "Floors 7 - 10", postcodes with
  // no street), which Nominatim rejects, so try plainer forms too.
  $plain = trim(preg_replace('/\s*[-\x{2013}(].*$/u', '', $name));
  $first = trim(explode(',', $plain)[0]);
  // Many venue descriptions hold a street address; try it before the name.
  $description = trim(strip_tags((string) $term->getDescription()));
  $description = preg_replace('/\s+/', ' ', $description);
  $queries = array_values(array_unique(array_filter([
    $address,
    mb_strlen($description) > 8 && mb_strlen($description) < 120 ? $description . ', UK' : '',
    $name . ', UK',
    $plain . ', London, UK',
    $first . ', London, UK',
  ])));
  $result = NULL;
  $query = '';
  foreach ($queries as $query) {
    $result = $geocoder->geocode($query);
    usleep(1200000);
    if (!empty($result['location'])) { break; }
  }
  if (empty($result['location'])) {
    $failed[] = $name . ' (tried ' . implode(' | ', array_map(fn($q) => '"' . $q . '"', $queries)) . ')';
    continue;
  }
  $term->set('field_location', ['lat' => $result['location']['lat'], 'lng' => $result['location']['lng']]);
  $term->save();
  printf("%-60s %9.5f %9.5f  %s\n", $term->getName(), $result['location']['lat'], $result['location']['lng'], mb_substr($result['address'] ?? '', 0, 70));
  $done++;
}
print "\nVenues: $done located, $skipped already had a location, " . count($failed) . " not found.\n";
foreach ($failed as $f) { print "  NOT FOUND: $f\n"; }
if ($failed) { print "Give these an 'Address for the map' at admin/structure/taxonomy/manage/venues/overview and run the script again.\n"; }
