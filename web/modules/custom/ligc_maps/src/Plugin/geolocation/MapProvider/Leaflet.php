<?php

namespace Drupal\ligc_maps\Plugin\geolocation\MapProvider;

use Drupal\geolocation_leaflet\Plugin\geolocation\MapProvider\Leaflet as StockLeaflet;

/**
 * Leaflet map provider with the library served from web/libraries/leaflet.
 *
 * Substituted for the stock provider in
 * ligc_maps_geolocation_mapprovider_info_alter(). The class keeps the short
 * name "Leaflet" on purpose: the base class finds the provider's own
 * js/MapProvider/<ShortName>.js and css/MapProvider/<ShortName>.css by the
 * class short name and the plugin's provider module, so a different name
 * would lose them (import of 'null', seen 2026-10-06).
 *
 * Keep web/libraries/leaflet at the version the stock provider names
 * (1.9.3 at geolocation 4.0.0).
 */
class Leaflet extends StockLeaflet {

  /**
   * {@inheritdoc}
   */
  protected array $scripts = [
    '/libraries/leaflet/leaflet.js',
  ];

  /**
   * {@inheritdoc}
   */
  protected array $stylesheets = [
    '/libraries/leaflet/leaflet.css',
  ];

}
