<?php

/**
 * @file
 * REVIEW ONLY: a second copy of the front page with the previous hero clip
 * (the concert video, 1280x672) at /home-concert-video, so the team can
 * compare it with the vertical reel on the real front page (Rob,
 * 2026-11-11). Delete the node before go-live (or just do not run this on
 * the live site); the front page itself is untouched.
 *
 * Idempotent: creates the node and its media once, by title / name. The
 * paragraphs are duplicates of the front page's at the time of the run.
 * Needs hero-concert-1280.mp4 and hero-concert-poster.webp in the hero
 * media dir (scripts/content/front-page/ or $LIGC_HERO_MEDIA_DIR).
 *
 *   ./drush php:script scripts/devops/review_homepage_variant_2026.php
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$media_storage = $etm->getStorage('media');
$title = 'Home (concert video)';
$alias = '/home-concert-video';

if ($node_storage->loadByProperties(['type' => 'home', 'title' => $title])) {
  print "Variant exists: $title at $alias\n";
  return;
}
$front = \Drupal::config('system.site')->get('page.front');
$home = preg_match('~^/node/(\d+)$~', $front, $m) ? $node_storage->load($m[1]) : NULL;
if (!$home || $home->bundle() !== 'home') {
  print "No front page node of type home ($front).\n";
  return;
}

// --- Media: the concert clip and its poster ----------------------------------
$source_dir = getenv('LIGC_HERO_MEDIA_DIR') ?: dirname(__DIR__) . '/content/front-page';
$file_repo = \Drupal::service('file.repository');
$dest_dir = 'public://2026-10';
$find_media = fn(string $name, string $bundle) => current($media_storage->loadByProperties(['name' => $name, 'bundle' => $bundle])) ?: NULL;

$video_media = $find_media('Front page hero loop (concert video)', 'video');
if (!$video_media) {
  $path = "$source_dir/hero-concert-1280.mp4";
  if (!is_file($path)) { print "ERROR: $path missing.\n"; return; }
  $file = $file_repo->writeData(file_get_contents($path), "$dest_dir/hero-concert-1280.mp4", FileExists::Replace);
  $video_media = Media::create(['bundle' => 'video', 'name' => 'Front page hero loop (concert video)', 'uid' => 1, 'status' => 1, 'field_media_video_file' => ['target_id' => $file->id()]]);
  $video_media->save();
  print "media video created: " . $video_media->id() . "\n";
}
$poster_media = $find_media('Front page hero poster (concert video)', 'image');
if (!$poster_media) {
  $path = "$source_dir/hero-concert-poster.webp";
  if (!is_file($path)) { print "ERROR: $path missing.\n"; return; }
  $file = $file_repo->writeData(file_get_contents($path), "$dest_dir/hero-concert-poster.webp", FileExists::Replace);
  $poster_media = Media::create(['bundle' => 'image', 'name' => 'Front page hero poster (concert video)', 'uid' => 1, 'status' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => 'The choir in black and red singing in a blue-lit church, a soloist at the front', 'title' => '']]);
  $poster_media->save();
  print "media poster created: " . $poster_media->id() . "\n";
}

// --- The copy: duplicated paragraphs, the hero pointed at the concert clip ---
$paragraphs = [];
foreach ($home->get('field_content')->referencedEntities() as $paragraph) {
  $copy = $paragraph->createDuplicate();
  if ($copy->bundle() === 'hero') {
    $copy->set('field_video', ['target_id' => $video_media->id()]);
    $copy->set('field_image', ['target_id' => $poster_media->id()]);
    if ($copy->hasField('field_video_phone')) { $copy->set('field_video_phone', NULL); }
    if ($copy->hasField('field_video_on_phones')) { $copy->set('field_video_on_phones', 0); }
    $copy->set('field_video_link', ['uri' => 'https://www.youtube.com/watch?v=RgNVtjKTOhI', 'title' => 'Watch the full video on YouTube']);
  }
  $copy->save();
  $paragraphs[] = ['target_id' => $copy->id(), 'target_revision_id' => $copy->getRevisionId()];
}
$node = Node::create([
  'type' => 'home', 'title' => $title, 'status' => 1, 'uid' => 1,
  'field_content' => $paragraphs,
]);
$node->save();
if (!$etm->getStorage('path_alias')->loadByProperties(['alias' => $alias])) {
  PathAlias::create(['path' => '/node/' . $node->id(), 'alias' => $alias, 'langcode' => 'en'])->save();
}
print "Variant created: node " . $node->id() . " at $alias (" . count($paragraphs) . " paragraphs)\n";

drupal_flush_all_caches();
print "Done.\n";
