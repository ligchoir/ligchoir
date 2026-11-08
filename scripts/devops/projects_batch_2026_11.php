<?php

/**
 * @file
 * First batch of Project and Collaborator nodes (issue #50, 2026-11-08),
 * one project per story, from the shortlist in
 * docs/tasks/projects-shortlist-2026-11.md. Safe to run repeatedly: a node
 * is only created when no node of that type with the same title exists, so
 * the team's later edits are never overwritten. Existing events are linked
 * as outcomes by nid; a missing nid is skipped with a message.
 *
 *   ./drush php:script scripts/devops/projects_batch_2026_11.php
 *
 * Needs the release's config imported (field_collaborations, collaboration
 * roles vocabulary) and collaboration_roles_2026.php run first.
 *
 * Bodies are short and factual (from newsletters and the notice board); the
 * choir team is expected to add the stories, photos and links.
 */

use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

$etm = \Drupal::entityTypeManager();
$nodes = $etm->getStorage('node');
$terms = $etm->getStorage('taxonomy_term');
$made = ['collaborator' => 0, 'project' => 0];

$find = function (string $type, string $title) use ($nodes) {
  $found = $nodes->loadByProperties(['type' => $type, 'title' => $title]);
  return $found ? reset($found) : NULL;
};
$role_id = function (string $name) use ($terms) {
  $found = $terms->loadByProperties(['vid' => 'collaboration_roles', 'name' => $name]);
  if (!$found) { print "  ! no role term '$name'\n"; return NULL; }
  return reset($found)->id();
};
// All-day smart date in UTC wall-clock (site convention): Y-m-d strings.
$dates = function (string $from, ?string $to = NULL): array {
  $start = strtotime($from . ' 00:00:00 UTC');
  $end = strtotime(($to ?: $from) . ' 23:59:00 UTC');
  return [['value' => $start, 'end_value' => $end, 'duration' => (int) (($end - $start) / 60), 'timezone' => '']];
};
$alias = function (Node $node, string $path) {
  $existing = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => $path]);
  if (!$existing) {
    PathAlias::create(['path' => '/node/' . $node->id(), 'alias' => $path, 'langcode' => 'en'])->save();
  }
};

// --- Collaborators ------------------------------------------------------------
// title => [kind, website, one-line body]
$collaborators = [
  'BBC Proms' => ['organisation', 'https://www.bbc.co.uk/proms', 'The BBC\'s summer season of classical concerts at the Royal Albert Hall.'],
  'London Philharmonic Choir' => ['organisation', 'https://www.lpc.org.uk', 'One of London\'s leading symphony choruses.'],
  'Southbank Centre' => ['organisation', 'https://www.southbankcentre.co.uk', 'Arts centre on London\'s South Bank, home of the Royal Festival Hall.'],
  'Take That' => ['person', '', 'British pop group.'],
  'Hugh Jackman' => ['person', '', 'Australian actor and singer.'],
  'George Ezra' => ['person', '', 'British singer-songwriter.'],
  'Joss Stone' => ['person', '', 'British soul singer.'],
  'Shelter' => ['organisation', 'https://www.shelter.org.uk', 'Housing and homelessness charity.'],
  'How Sweet The Sound' => ['organisation', '', 'National gospel choir competition with its final at the Royal Albert Hall.'],
  'Leeds Castle' => ['organisation', 'https://www.leeds-castle.com', 'Castle and estate in Kent, host of open-air summer concerts.'],
  'Royal Philharmonic Orchestra' => ['organisation', 'https://www.rpo.co.uk', 'London symphony orchestra.'],
  'XANG Gospel Choir' => ['organisation', '', 'Gospel choir in Wiesbaden, Germany, formerly led by LIGC\'s artistic director.'],
  'Soul of the City Gospel Choir' => ['organisation', '', 'Community gospel choir in Exeter.'],
  'Bloom For Love' => ['organisation', '', 'Gospel choir from Belgium.'],
  'Sollentuna Gospel' => ['organisation', '', 'Gospel choir from Sollentuna, Sweden.'],
  'Gospelfabrikken' => ['organisation', '', 'Gospel choir from Norway.'],
  'Immanuel Gospel Choir' => ['organisation', '', 'Gospel choir from Stockholm, Sweden.'],
  'Leeds Conservatoire Contemporary Pop Choir' => ['organisation', '', 'Pop choir at Leeds Conservatoire, directed by Craig Lees.'],
  'Sinfonia Smith Square' => ['organisation', '', 'Concert hall and orchestra at St John\'s Smith Square, Westminster.'],
  'Soul Sanctuary Gospel Choir' => ['organisation', '', 'London gospel choir.'],
  'Gospel Rising' => ['organisation', '', 'Gospel choir.'],
  'Sibiu International Theatre Festival' => ['organisation', 'https://www.sibfest.ro', 'One of Europe\'s largest performing arts festivals, held each June in Sibiu, Romania.'],
  'International Mziki Gospel Festival' => ['organisation', '', 'Gospel festival in Kenya with workshops, concerts and outreach in schools.'],
];
foreach ($collaborators as $title => [$kind, $website, $body]) {
  if ($find('collaborator', $title)) { continue; }
  $node = Node::create([
    'type' => 'collaborator', 'title' => $title, 'status' => 1,
    'field_kind' => $kind,
    'body' => ['value' => '<p>' . $body . '</p>', 'format' => 'basic_html'],
  ]);
  if ($website) { $node->set('field_website', ['uri' => $website, 'title' => '']); }
  $node->save();
  $made['collaborator']++;
}
print "Collaborators created: {$made['collaborator']}\n";

// --- Projects -----------------------------------------------------------------
// Each: title, slug, status, dates [from, to], body paragraphs, collaborations
// [[collaborator title, role, note]], outcomes [event nids], links [[url, title]].
$projects = [
  [
    'title' => 'BBC Proms: Prom 66, In the Name of the Earth', 'slug' => 'bbc-proms-2019', 'status' => 'completed',
    'dates' => ['2019-09-08'],
    'body' => ['Around fifty LIGC singers took part in the European premiere of John Luther Adams\'s <em>In the Name of the Earth</em> at the 2019 BBC Proms: eight choirs and some 600 voices filling the Royal Albert Hall, half of them choral societies and half community choirs like ours.', 'LIGC was paired with the London Philharmonic Choir for the piece, which was broadcast live on BBC Radio 3.'],
    'collaborations' => [['BBC Proms', 'Hosted by', 'Prom 66 at the Royal Albert Hall, 8 September 2019.'], ['London Philharmonic Choir', 'Performed with', 'LIGC sang alongside the LPC within the eight-choir chorus.']],
    'outcomes' => [33], 'links' => [],
  ],
  [
    'title' => 'Don\'t You Ever Give Up: our first recordings', 'slug' => 'first-recordings', 'status' => 'completed',
    'dates' => ['2024-04-14', '2025-02-21'],
    'body' => ['In April 2024 the choir went into the studio for the first time: a whole day of singing with our band and added strings, recorded, mixed and mastered for release.', 'Three singles followed in the autumn of 2024, and the EP <em>Don\'t You Ever Give Up</em> was released in February 2025 on Spotify, Apple Music and the other streaming services.'],
    'collaborations' => [],
    'outcomes' => [],
    'links' => [['https://distrokid.com/hyperfollow/londoninternationalgospelchoir/dont-you-ever-give-up', 'Don\'t You Ever Give Up (single)'], ['https://distrokid.com/hyperfollow/londoninternationalgospelchoir/have-a-little-faith-in-me', 'Have a Little Faith in Me (single)'], ['https://distrokid.com/hyperfollow/londoninternationalgospelchoir/hark-the-herald-angels-sing', 'Hark the Herald Angels Sing (single)']],
  ],
  [
    'title' => 'How Sweet The Sound: final at the Royal Albert Hall', 'slug' => 'how-sweet-the-sound-2024', 'status' => 'completed',
    'dates' => ['2024-10-20'],
    'body' => ['LIGC reached the final of How Sweet The Sound, the national gospel choir competition, performing with the full band at the Royal Albert Hall on 20 October 2024.', 'The audition video was recorded on the same day as our first studio session; an auditioned competition group then rehearsed through the summer and autumn.'],
    'collaborations' => [['How Sweet The Sound', 'Organised by', 'Competition final, Royal Albert Hall.']],
    'outcomes' => [87], 'links' => [['https://youtu.be/9QPNbGxbns4', 'Our audition video']],
  ],
  [
    'title' => 'Michelle Obama\'s Becoming: UK book launch', 'slug' => 'becoming-book-launch-2018', 'status' => 'completed',
    'dates' => ['2018-12-03'],
    'body' => ['The choir was chosen to welcome guests to the Royal Festival Hall for the UK launch of Michelle Obama\'s memoir <em>Becoming</em> on 3 December 2018, performing in the Clore Ballroom as the audience arrived.'],
    'collaborations' => [['Southbank Centre', 'Hosted by', 'Royal Festival Hall, Clore Ballroom.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Lockdown: the virtual choir', 'slug' => 'lockdown-virtual-choir', 'status' => 'completed',
    'dates' => ['2020-03-21', '2021-07-31'],
    'body' => ['When rehearsals stopped in March 2020 the choir moved online: three Zoom sessions a week, weekly song emails with part recordings, quizzes and socials, and a series of guest workshops on vocal health, yoga for singers, songwriting and reading music.', 'The choir recorded two virtual-choir videos at home, <em>Lift Him Up</em> and <em>How Great Thou Art</em> for the 2020 Grenfell memorial service, and returned to singing together outdoors and then indoors in the summer of 2021.'],
    'collaborations' => [], 'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Leeds Castle open-air concert', 'slug' => 'leeds-castle-2025', 'status' => 'completed',
    'dates' => ['2025-07-12'],
    'body' => ['Sixty-five LIGC singers and our live band played the open-air summer concert at Leeds Castle in Kent on 12 July 2025, supporting the Royal Philharmonic Orchestra in front of an audience of around 10,000.'],
    'collaborations' => [['Leeds Castle', 'Hosted by', ''], ['Royal Philharmonic Orchestra', 'Supported', 'LIGC opened for the orchestra.']],
    'outcomes' => [90], 'links' => [],
  ],
  [
    'title' => 'On stage with Take That', 'slug' => 'take-that-2019', 'status' => 'completed',
    'dates' => ['2019-05-02', '2019-05-23'],
    'body' => ['Selected LIGC members performed on stage with Take That on the Greatest Hits Live tour in May 2019: eight nights at The O2 and the stadium show at Milton Keynes, with choreography and a heavy rehearsal commitment, all kept secret until the shows.'],
    'collaborations' => [['Take That', 'Performed with', 'Greatest Hits Live tour: The O2 (2 to 10 May) and Stadium MK (23 May 2019).']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'On stage with Hugh Jackman', 'slug' => 'hugh-jackman-2019', 'status' => 'completed',
    'dates' => ['2019-06-01', '2019-06-30'],
    'body' => ['A group of LIGC members joined Hugh Jackman for his arena week at The O2 in June 2019, <em>The Man. The Music. The Show.</em>, in the run-up to our own summer concert.'],
    'collaborations' => [['Hugh Jackman', 'Performed with', 'The O2, June 2019.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'George Ezra at Finsbury Park', 'slug' => 'george-ezra-2022', 'status' => 'completed',
    'dates' => ['2022-07-17'],
    'body' => ['Thirty LIGC singers supported George Ezra at his Finsbury Park show on 17 July 2022. The new introduction we sang for <em>Shackles</em> that day has stayed in the repertoire.'],
    'collaborations' => [['George Ezra', 'Supported', 'Finsbury Park, 17 July 2022.']],
    'outcomes' => [29], 'links' => [],
  ],
  [
    'title' => 'Joss Stone at the Royal Albert Hall', 'slug' => 'joss-stone-2024', 'status' => 'completed',
    'dates' => ['2024-07-08'],
    'body' => ['Fifty LIGC singers backed Joss Stone for two songs at the Royal Albert Hall on 8 July 2024. One of them, <em>The Answer</em>, went straight into our 2024 summer concert.'],
    'collaborations' => [['Joss Stone', 'Performed with', 'Royal Albert Hall, 8 July 2024.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Shelter\'s televised Christmas fundraiser', 'slug' => 'shelter-fundraiser-2025', 'status' => 'completed',
    'dates' => ['2025-12-15'],
    'body' => ['Around thirty LIGC singers took part in Shelter\'s televised Christmas fundraising event on 15 December 2025, singing two Christmas songs and joining a major artist on stage.'],
    'collaborations' => [['Shelter', 'Performed for', '']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Porto tour', 'slug' => 'porto-tour-2018', 'status' => 'completed',
    'dates' => ['2018-11-01', '2018-11-04'],
    'body' => ['The choir\'s first tour abroad in recent years: four days in Portugal in November 2018 with concerts in Leça and Porto, an evening with a local Portuguese choir and a tour of the Casa da Música.'],
    'collaborations' => [], 'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Romania: Sibiu International Theatre Festival', 'slug' => 'romania-sibiu', 'status' => 'completed',
    'dates' => ['2022-06-23', '2023-07-03'],
    'body' => ['Small groups of LIGC singers travelled to Romania in June 2022 and again in June 2023, when ten of us represented the choir at the Sibiu International Theatre Festival, one of the largest arts festivals in Europe. The first 2023 show, <em>Gospel &amp; Iubire</em>, was streamed by the festival.'],
    'collaborations' => [['Sibiu International Theatre Festival', 'Hosted by', 'June 2023.']],
    'outcomes' => [56], 'links' => [['https://sibfest.ro/ro/events/gospel--iubire--online-', 'Gospel & Iubire at Sibfest 2023']],
  ],
  [
    'title' => 'Germany: Wiesbaden with XANG', 'slug' => 'wiesbaden-tour-2024', 'status' => 'completed',
    'dates' => ['2024-03-14', '2024-03-18'],
    'body' => ['In March 2024 around twenty-six LIGC singers spent four days in Wiesbaden with XANG, the gospel choir our artistic director led before coming to London: a joint workshop on the Friday, a shared concert for an audience of 500 on the Saturday, and days out in the vineyards and castles of the Rhine.'],
    'collaborations' => [['XANG Gospel Choir', 'Performed with', 'Joint workshop and concert, Wiesbaden, March 2024.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Exeter with Soul of the City', 'slug' => 'exeter-tour-2024', 'status' => 'completed',
    'dates' => ['2024-09-27', '2024-09-29'],
    'body' => ['Twenty-eight LIGC members travelled to Exeter in September 2024 to sing with Soul of the City Gospel Choir: a joint concert in a city-centre church, workshops together and a Sunday social at the choir\'s rehearsal home. Soul of the City returned the visit in February 2025.'],
    'collaborations' => [['Soul of the City Gospel Choir', 'Performed with', 'Joint concert and workshops in Exeter.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Kenya: International Mziki Gospel Festival', 'slug' => 'kenya-2025', 'status' => 'completed',
    'dates' => ['2025-07-23', '2025-07-28'],
    'body' => ['Following the How Sweet The Sound final, LIGC was invited to the International Mziki Gospel Festival in Kenya. A self-funded group of members made the trip in July 2025 for a week of workshops, concerts and singing in local schools.'],
    'collaborations' => [['International Mziki Gospel Festival', 'Hosted by', 'July 2025.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Swedish and Norwegian choirs in London', 'slug' => 'choir-exchange-2019', 'status' => 'completed',
    'dates' => ['2019-10-12', '2019-10-13'],
    'body' => ['In October 2019 two visiting choirs joined us for a weekend: Sollentuna Gospel from Sweden and Gospelfabrikken from Norway. Two concerts ran on the same evening, one with our full band at the Swedish Church in Marylebone and one with a small LIGC group at Bloomsbury Central Baptist Church, followed by a special Gospel Sunday with both choirs and a week of guest workshops.'],
    'collaborations' => [['Sollentuna Gospel', 'Performed with', 'Swedish Church, Marylebone.'], ['Gospelfabrikken', 'Performed with', 'Bloomsbury Central Baptist Church.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'International Gospel Celebration', 'slug' => 'international-gospel-celebration-2025', 'status' => 'completed',
    'dates' => ['2025-02-22', '2025-02-23'],
    'body' => ['The return leg of our Exeter exchange: on 22 February 2025 Soul of the City Gospel Choir from Exeter and Bloom For Love from Belgium joined LIGC at All Saints Notting Hill for a three-choir concert, singing <em>The Blessing</em>, <em>Glorious</em> and <em>Shackles</em> together. The next day Craig Lees of Leeds Conservatoire led a workshop for all three choirs.'],
    'collaborations' => [['Soul of the City Gospel Choir', 'Performed with', ''], ['Bloom For Love', 'Performed with', ''], ['Leeds Conservatoire Contemporary Pop Choir', 'Workshop with', 'Sunday workshop led by Craig Lees.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Leeds: singing with the Conservatoire pop choir', 'slug' => 'leeds-pop-choir-2025', 'status' => 'completed',
    'dates' => ['2025-11-29', '2025-11-30'],
    'body' => ['Around forty LIGC singers travelled to Leeds for a weekend in November 2025 to perform with Leeds Conservatoire\'s Contemporary Pop Choir, directed by Craig Lees. A return visit to London is being planned.'],
    'collaborations' => [['Leeds Conservatoire Contemporary Pop Choir', 'Performed with', 'Joint concert in Leeds, 29 November 2025.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Stockholm\'s Immanuel Gospel Choir in London', 'slug' => 'immanuel-gospel-choir-2026', 'status' => 'completed',
    'dates' => ['2026-05-02'],
    'body' => ['On 2 May 2026 around twenty singers from Stockholm\'s Immanuel Gospel Choir, on a mini tour for their 35th anniversary, joined LIGC for a collaborative gospel singing workshop at St Mary\'s Islington. A visit to Stockholm is hoped for in 2027.'],
    'collaborations' => [['Immanuel Gospel Choir', 'Workshop with', 'St Mary\'s Islington, 2 May 2026.']],
    'outcomes' => [121], 'links' => [],
  ],
  [
    'title' => 'Rise Up! An Easter gospel experience', 'slug' => 'rise-up-easter-2026', 'status' => 'completed',
    'dates' => ['2026-04-04'],
    'body' => ['LIGC hosted a three-choir Easter concert at Sinfonia Smith Square on 4 April 2026 with Soul Sanctuary Gospel Choir and Gospel Rising, closing with all three choirs singing <em>Higher Love</em> together.'],
    'collaborations' => [['Soul Sanctuary Gospel Choir', 'Performed with', ''], ['Gospel Rising', 'Performed with', ''], ['Sinfonia Smith Square', 'Hosted by', '']],
    'outcomes' => [107], 'links' => [],
  ],
  [
    'title' => 'Sinfonia Smith Square partnership', 'slug' => 'sinfonia-smith-square', 'status' => 'active',
    'dates' => ['2024-07-20'],
    'body' => ['Our 2024 summer concert <em>Optimistic</em> at St John\'s Smith Square began a continuing relationship with Sinfonia Smith Square. The hall has since hosted our Easter concerts, our 2025 Christmas shows, which opened its Christmas season, and our 2026 summer concert, and the 2026 Christmas shows are a co-production.'],
    'collaborations' => [['Sinfonia Smith Square', 'Hosted by', 'Easter, summer and Christmas concerts since 2024; co-production from Christmas 2026.']],
    'outcomes' => [88, 93, 94, 107, 109, 116, 117], 'links' => [],
  ],
  [
    'title' => 'Faithful Voices interfaith gala', 'slug' => 'faithful-voices', 'status' => 'active',
    'dates' => ['2022-03-27'],
    'body' => ['Faithful Voices is an interfaith song festival that brings choirs of different faiths together for a shared concert, with workshops beforehand and pieces learned together on the day. LIGC has taken part since the first gala in March 2022, returning in June 2024 and November 2026.'],
    'collaborations' => [],
    'outcomes' => [83, 129], 'links' => [],
  ],
];

foreach ($projects as $p) {
  if ($find('project', $p['title'])) { continue; }
  $collabs = [];
  foreach ($p['collaborations'] as [$who, $role, $note]) {
    $c = $find('collaborator', $who);
    if (!$c) { print "  ! no collaborator '$who' for {$p['title']}\n"; continue; }
    $row = ['collaborator' => $c->id(), 'role' => $role_id($role)];
    if ($note !== '') { $row['note'] = '<p>' . $note . '</p>'; $row['note__format'] = 'basic_html'; }
    $collabs[] = $row;
  }
  $outcomes = [];
  foreach ($p['outcomes'] as $nid) {
    $e = $nodes->load($nid);
    if ($e && $e->bundle() === 'event') { $outcomes[] = ['target_id' => $nid]; }
    else { print "  ! event $nid not found for {$p['title']}\n"; }
  }
  $node = Node::create([
    'type' => 'project', 'title' => $p['title'], 'status' => 1,
    'field_status' => $p['status'],
    'field_dates' => $dates(...$p['dates']),
    'body' => ['value' => '<p>' . implode('</p><p>', $p['body']) . '</p>', 'format' => 'basic_html'],
    'field_collaborations' => $collabs,
    'field_outcomes' => $outcomes,
    'field_links' => array_map(fn($l) => ['uri' => $l[0], 'title' => $l[1]], $p['links']),
  ]);
  $node->save();
  $alias($node, '/projects/' . $p['slug']);
  $made['project']++;
}
print "Projects created: {$made['project']}\n";

// LIV: link its joint Crypt concert as an outcome if not already there.
if ($liv = $find('project', 'London International Voices')) {
  $ids = array_column($liv->get('field_outcomes')->getValue(), 'target_id');
  if (!in_array(62, $ids) && $nodes->load(62)) {
    $liv->get('field_outcomes')->appendItem(['target_id' => 62]);
    $liv->setNewRevision(FALSE);
    $liv->save();
    print "LIV: Crypt Lates (node 62) added as an outcome.\n";
  }
}

drupal_flush_all_caches();
print "Done.\n";
