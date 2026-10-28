<?php

/**
 * @file
 * Rebuild the front page in the October 2026 design. Safe to run repeatedly.
 *
 *   ./drush php:script scripts/devops/front_page_2026.php
 *
 * Needs the config of the new theme release imported first (paragraph types
 * hero, card, card_row; the events view "cards" display). Then, on the Home
 * node (content type home):
 *
 *   1. Imports hero-1280.mp4 and hero-poster.webp (not in git: they show
 *      choir members) from $LIGC_HERO_MEDIA_DIR, default
 *      scripts/content/front-page/, into the public files directory as media items "Front page hero loop"
 *      (video) and "Front page hero poster" (image), unless they exist.
 *   2. Builds the paragraphs: hero (heading and text taken from the existing
 *      "Welcome" text paragraph), events view (cards display), an About text,
 *      a card row (Book us, Our projects, Join us) and keeps the existing
 *      call-to-action paragraph as the general enquiry link.
 *   3. Sets field_content to that order; deletes the paragraphs no longer
 *      used on the page (YouTube embed, vertical space, the old Welcome text).
 *   4. Deletes orphan paragraphs anywhere whose parent field no longer exists
 *      (left over from fields removed years ago).
 *
 * Each step looks for what it would create before creating it.
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$media_storage = $etm->getStorage('media');
$paragraph_storage = $etm->getStorage('paragraph');

$homes = $node_storage->loadByProperties(['type' => 'home']);
if (!$homes) {
  print "ERROR: no home node.\n";
  return;
}
$home = reset($homes);
print "Home node " . $home->id() . "\n";
foreach (['hero', 'card', 'card_row'] as $type) {
  if (!$etm->getStorage('paragraphs_type')->load($type)) {
    print "ERROR: paragraph type $type missing; import config first (drush cim).\n";
    return;
  }
}

// --- 1. Media ---------------------------------------------------------------
// The clip and poster show choir members, so they are kept out of git; copy
// them in (or point LIGC_HERO_MEDIA_DIR at them) before the first run.
$source_dir = getenv('LIGC_HERO_MEDIA_DIR') ?: dirname(__DIR__) . '/content/front-page';
$file_repo = \Drupal::service('file.repository');
$dest_dir = 'public://2026-10';
\Drupal::service('file_system')->prepareDirectory($dest_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

$find_media = fn(string $name, string $bundle) => current($media_storage->loadByProperties(['name' => $name, 'bundle' => $bundle])) ?: NULL;

$video_media = $find_media('Front page hero loop', 'video');
if (!$video_media) {
  $path = "$source_dir/hero-1280.mp4";
  if (!is_file($path)) { print "ERROR: $path missing.\n"; return; }
  $file = $file_repo->writeData(file_get_contents($path), "$dest_dir/hero-1280.mp4", FileExists::Replace);
  $video_media = Media::create(['bundle' => 'video', 'name' => 'Front page hero loop', 'uid' => 1, 'status' => 1, 'field_media_video_file' => ['target_id' => $file->id()]]);
  $video_media->save();
  print "media video created: " . $video_media->id() . " (" . round(filesize($path) / 1024) . " KB)\n";
}
else {
  print "media video exists: " . $video_media->id() . "\n";
}
$poster_media = $find_media('Front page hero poster', 'image');
if (!$poster_media) {
  $path = "$source_dir/hero-poster.webp";
  if (!is_file($path)) { print "ERROR: $path missing.\n"; return; }
  $file = $file_repo->writeData(file_get_contents($path), "$dest_dir/hero-poster.webp", FileExists::Replace);
  $poster_media = Media::create(['bundle' => 'image', 'name' => 'Front page hero poster', 'uid' => 1, 'status' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => 'The choir in black and red singing in a blue-lit church, a soloist at the front', 'title' => '']]);
  $poster_media->save();
  print "media poster created: " . $poster_media->id() . "\n";
}
else {
  print "media poster exists: " . $poster_media->id() . "\n";
}

// --- 2. Paragraphs ----------------------------------------------------------
$current = $home->get('field_content')->referencedEntities();
$by_bundle = [];
foreach ($current as $p) { $by_bundle[$p->bundle()][] = $p; }

// Hero: heading and text from the old Welcome paragraph (h3 + p).
$hero = $by_bundle['hero'][0] ?? NULL;
if (!$hero) {
  $heading = 'Welcome to the London International Gospel Choir!';
  $text = '';
  foreach ($by_bundle['text'] ?? [] as $t) {
    $html = $t->get('field_text')->value ?? '';
    if (stripos($html, 'Welcome') !== FALSE) {
      if (preg_match('~<h\d[^>]*>(.*?)</h\d>~is', $html, $m)) { $heading = trim(strip_tags($m[1])); }
      if (preg_match_all('~<p[^>]*>(.*?)</p>~is', $html, $mm)) {
        $paras = array_filter(array_map(fn($x) => trim(strip_tags(html_entity_decode($x))), $mm[1]));
        $text = '<p>' . implode('</p><p>', $paras) . '</p>';
      }
      break;
    }
  }
  $hero = Paragraph::create([
    'type' => 'hero',
    'field_heading' => $heading,
    'field_text' => ['value' => $text, 'format' => 'basic_html'],
    'field_link' => ['uri' => 'internal:/about', 'title' => 'Read more'],
    'field_video' => ['target_id' => $video_media->id()],
    'field_image' => ['target_id' => $poster_media->id()],
    'field_video_link' => ['uri' => 'https://www.youtube.com/watch?v=RgNVtjKTOhI', 'title' => 'Watch the full video on YouTube'],
  ]);
  $hero->save();
  print "hero paragraph created: " . $hero->id() . "\n";
}
else { print "hero exists: " . $hero->id() . "\n"; }

// Events cards view.
$events = NULL;
foreach ($by_bundle['view'] ?? [] as $v) {
  if (($v->get('field_views_display')->target_id ?? '') === 'events') { $events = $v; break; }
}
if (!$events) {
  $events = Paragraph::create([
    'type' => 'view',
    'field_views_display' => ['target_id' => 'events', 'display_id' => 'cards', 'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL])],
  ]);
  print "events view paragraph created\n";
}
else { print "events view exists: " . $events->id() . "\n"; }
// No colour pickers: the cards display brings its own layout and the old
// white wrapper (the border field defaults to white) must not render.
$events->set('field_colour_background', NULL)->set('field_colour_border', NULL)->set('field_colour_text', NULL);
$events->save();

// About text (marker class so re-runs find it).
$about = NULL;
foreach ($by_bundle['text'] ?? [] as $t) {
  if (str_contains($t->get('field_text')->value ?? '', 'ligc-front-about')) { $about = $t; break; }
}
if (!$about) {
  $about = Paragraph::create([
    'type' => 'text',
    'field_text' => ['value' => '<div class="ligc-front-about"><h2>About us</h2><p>We are London International Gospel Choir, one of the capital\'s largest, hardest-working community-based choirs. Ranging from beginners to professionals, our members sing gospel, funk, soul, jazz and pop across the UK and Europe: flash-mobs, charity fundraisers, the Royal Albert Hall and stadium tours. Soaring solos, powerful harmonies, tight backing and mesmerising soundscapes. LIGC delivers them all.</p><p><a href="/about">More about us and the team</a></p></div>', 'format' => 'full_html'],
    'field_colour_background' => ['target_id' => 11],
    'field_colour_text' => ['target_id' => 10],
    'field_style' => ['target_id' => 'text_confine_single_column_wide'],
  ]);
  $about->save();
  print "about text paragraph created: " . $about->id() . "\n";
}
else { print "about text exists: " . $about->id() . "\n"; }

// Card row: Book us / Our projects / Join us.
$row = $by_bundle['card_row'][0] ?? NULL;
if (!$row) {
  $cards_spec = [
    ['Book us', 'BookUs.jpg', 'internal:/book', '<p>Corporate events, weddings, festivals, fundraisers: bring the energy of LIGC to your event.</p>', 'Book the choir'],
    ['Our projects', 'liv_DSC_5455.jpg', 'internal:/liv', '<p>L•I•V, our concerts and recordings, and the partners we make music with.</p>', 'See our projects'],
    ['Join us', 'join-img2-desktop@3x.jpg', 'internal:/join', '<p>No auditions, all voices welcome. Rehearsals in London; come and sing with us.</p>', 'Join the choir'],
  ];
  $cards = [];
  foreach ($cards_spec as [$heading, $media_name, $uri, $text, $cta]) {
    $image = $find_media($media_name, 'image');
    if (!$image) { print "WARNING: image media '$media_name' not found; card '$heading' has no image.\n"; }
    $card = Paragraph::create([
      'type' => 'card',
      'field_heading' => $heading,
      'field_text' => ['value' => $text, 'format' => 'basic_html'],
      'field_link' => ['uri' => $uri, 'title' => $cta],
      'field_image' => $image ? ['target_id' => $image->id()] : NULL,
    ]);
    $card->save();
    $cards[] = ['target_id' => $card->id(), 'target_revision_id' => $card->getRevisionId()];
  }
  $row = Paragraph::create(['type' => 'card_row', 'field_cards' => $cards]);
  $row->save();
  print "card row created: " . $row->id() . " with " . count($cards) . " cards\n";
}
else { print "card row exists: " . $row->id() . "\n"; }

// General enquiry: the existing call to action, re-pointed.
$cta = $by_bundle['call_to_action'][0] ?? NULL;
if ($cta) {
  $cta->set('field_link', ['uri' => 'internal:/about#general-contact', 'title' => 'General enquiry']);
  $cta->setNewRevision(FALSE);
  $cta->save();
  print "call to action " . $cta->id() . " now 'General enquiry' -> /about#general-contact\n";
}

// --- 3. Order and clean-up ---------------------------------------------------
$keep = array_filter([$hero, $events, $about, $row, $cta]);
$keep_ids = array_map(fn($p) => $p->id(), $keep);
$home->set('field_content', array_map(fn($p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()], $keep));
$home->setNewRevision(TRUE);
$home->setRevisionLogMessage('Front page rebuilt in the October 2026 design (scripts/devops/front_page_2026.php).');
$home->save();
print "home field_content order: " . implode(', ', array_map(fn($p) => $p->bundle() . '#' . $p->id(), $keep)) . "\n";
$removed = 0;
foreach ($current as $p) {
  if (!in_array($p->id(), $keep_ids, TRUE)) { print "deleted paragraph " . $p->bundle() . '#' . $p->id() . " (no longer on the front page)\n"; $p->delete(); $removed++; }
}

// --- 4. Orphans everywhere ---------------------------------------------------
$orphans = 0;
$ids = \Drupal::database()->select('paragraphs_item_field_data', 'p')->fields('p', ['id'])->condition('parent_type', 'node')->execute()->fetchCol();
foreach ($paragraph_storage->loadMultiple($ids) as $p) {
  $parent = $p->getParentEntity();
  $field = $p->get('parent_field_name')->value;
  if (!$parent || !$field || !$parent->hasField($field)) {
    print "deleted orphan paragraph " . $p->bundle() . '#' . $p->id() . " (parent field '$field' gone)\n";
    $p->delete();
    $orphans++;
  }
}
print "Removed $removed front page paragraph(s) and $orphans orphan(s).\n";
drupal_flush_all_caches();
print "Done. Check /.\n";
