<?php

/**
 * @file
 * Second batch of Project and Collaborator nodes (issue #50, 2026-11-10):
 * the "substantial one-offs" and the "recurring strands" from
 * docs/tasks/projects-shortlist-2026-11.md, one project each. Same rules as
 * projects_batch_2026_11.php: a node is only created when no node of that
 * type with the same title exists, existing events are linked as outcomes by
 * nid, bodies are short and factual for the team to build on. Items the
 * sources did not confirm (Memorial at the Barbican 2018, Holocaust Memorial
 * Day 2024) are left out (Rob, 2026-11-11: if in doubt, leave out).
 *
 *   ./drush php:script scripts/devops/projects_batch_2_2026_11.php
 *
 * Needs the first batch's prerequisites (config imported, roles seeded) and
 * reuses collaborators the first batch created (Sinfonia Smith Square,
 * Immanuel Gospel Choir).
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
  'Music Declares Emergency' => ['organisation', 'https://musicdeclares.net', 'Campaign group of musicians and music-industry organisations calling for action on the climate emergency.'],
  'Tate Modern' => ['organisation', 'https://www.tate.org.uk/visit/tate-modern', 'National gallery of modern art on Bankside, home of the Turbine Hall.'],
  'Juliet Russell' => ['person', '', 'Choir leader and singer, co-founder of Also Festival.'],
  'Bold Tendencies' => ['organisation', 'https://boldtendencies.com', 'Arts organisation that commissions and presents work in the multi-storey car park in Peckham.'],
  'IET London: Savoy Place' => ['organisation', '', 'The Institution of Engineering and Technology\'s venue on the Embankment.'],
  'Sense International' => ['organisation', 'https://www.senseinternational.org.uk', 'Charity supporting people with deafblindness and complex disabilities around the world.'],
  'Tom Randle' => ['person', '', 'American conductor and opera singer.'],
  'Barbican Centre' => ['organisation', 'https://www.barbican.org.uk', 'Performing arts centre in the City of London.'],
  'SK Shlomo' => ['person', 'https://skshlomo.com', 'Beatboxer, producer and performer.'],
  'Rich Mix' => ['organisation', 'https://richmix.org.uk', 'Arts centre and cinema in Shoreditch.'],
  'BIMM London' => ['organisation', 'https://www.bimm.ac.uk', 'Music college in London, part of BIMM University.'],
  'Kate Mayer' => ['person', '', 'Abstract artist whose live-painting events have featured the choir.'],
  'Common & Kind' => ['organisation', '', 'Organisation behind the <em>Humankind</em> concerts at Union Chapel that brought community choirs together.'],
  'Rainbow Against Borders' => ['organisation', '', 'Choir of LGBT refugees and asylum seekers.'],
  'Union Chapel' => ['organisation', 'https://www.unionchapel.org.uk', 'Church and concert venue in Islington.'],
  'Amnesty International UK' => ['organisation', 'https://www.amnesty.org.uk', 'Human rights organisation; its Human Rights Action Centre is in Shoreditch.'],
  'Anin Rose' => ['person', '', 'Singer-songwriter; the artist name of LIGC\'s artistic director.'],
  'The Haven + London' => ['organisation', '', 'London charity that partnered with the choir for concerts in 2018 and 2021.'],
  'The Albany' => ['organisation', '', 'Pub on Great Portland Street whose downstairs bar hosts the choir\'s Fun Friday showcases.'],
  'Mayor of London' => ['organisation', 'https://www.london.gov.uk', 'The Mayor of London and the Greater London Authority at City Hall.'],
  'Also Festival' => ['organisation', 'https://www.alsofestival.com', 'Summer festival of ideas and music at Compton Verney, Warwickshire.'],
  'African Promise' => ['organisation', '', 'UK charity working with primary schools in rural Kenya; its carol service is at St Giles-in-the-Fields.'],
  'Our Moon' => ['organisation', '', 'Charity whose London Christmas fundraiser the choir sings at each December.'],
  'Motor Neurone Disease Association' => ['organisation', 'https://www.mndassociation.org', 'Charity supporting people affected by motor neurone disease.'],
  'Kidney Research UK' => ['organisation', 'https://www.kidneyresearchuk.org', 'Charity funding research into kidney disease.'],
  'Maggie\'s' => ['organisation', 'https://www.maggies.org', 'Cancer support charity.'],
  'Pancreatic Cancer UK' => ['organisation', 'https://www.pancreaticcancer.org.uk', 'Charity funding research and support for people with pancreatic cancer.'],
  'London Landmarks Half Marathon' => ['organisation', 'https://www.llhm.co.uk', 'Half marathon through central London organised by the pregnancy charity Tommy\'s.'],
  'Tavistock Festival' => ['organisation', '', 'Community festival on Portobello Road, Notting Hill.'],
  'Bankside Open Spaces Trust' => ['organisation', 'https://www.bost.org.uk', 'Charity caring for parks and gardens in Bankside, Southwark, and host of a community festival.'],
  'London Fire Brigade' => ['organisation', 'https://www.london-fire.gov.uk', 'London\'s fire and rescue service; Islington Fire Station holds fundraising open days.'],
  'Bloomsbury Central Baptist Church' => ['organisation', '', 'Church on Shaftesbury Avenue that hosted Gospel Sunday and the choir\'s 2019 Christmas concert.'],
  'Omari Carter' => ['person', '', 'Movement director and performer with STOMP.'],
  'Pete Churchill' => ['person', '', 'Jazz singer, composer and professor at the Royal College of Music.'],
  'Elly Hoyt' => ['person', '', 'Award-winning jazz singer and vocal coach.'],
  'Mary Bourne' => ['person', '', 'Choir leader and songwriter behind Singing It Back.'],
  'Lizzy Renihan' => ['person', '', 'West End singer and founder of Natural Voices Choirs.'],
  'Jane Wheeler' => ['person', '', 'Choir leader and arranger behind Living Song.'],
  'Craig Lees' => ['person', '', 'Choral director of the Leeds Conservatoire Contemporary Pop Choir.'],
  'Clarence Hunte' => ['person', '', 'Musical director of Soul Sanctuary Gospel Choir.'],
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
// [[collaborator title, role, note]], outcomes [event nids], links [[url, title]],
// published (FALSE = created unpublished, needs the team's confirmation).
$projects = [
  [
    'title' => 'Music Declares Emergency: Sing The Change at Tate Modern', 'slug' => 'music-declares-emergency-2021', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2021-10-30'],
    'body' => ['On 30 October 2021, the day before COP26 opened, LIGC joined a national massed sing organised by Music Declares Emergency. Choirs in Glasgow, Bristol, Liverpool and London performed <em>What A Wonderful World</em>, in a new arrangement by Mark De Lisser, at the same time; the London choirs sang in the Turbine Hall at Tate Modern, led by Juliet Russell, and the event was filmed.'],
    'collaborations' => [['Music Declares Emergency', 'Organised by', 'National massed sing before COP26.'], ['Tate Modern', 'Hosted by', 'Turbine Hall, 30 October 2021.'], ['Juliet Russell', 'Performed with', 'Led the London choirs.']],
    'outcomes' => [19], 'links' => [],
  ],
  [
    'title' => 'Songs of Communion at Bold Tendencies', 'slug' => 'songs-of-communion-2024', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2024-06-01'],
    'body' => ['More than 190 singers from six choirs opened the 2024 Bold Tendencies season in the multi-storey car park in Peckham on 1 June 2024, performing the newly commissioned <em>Songs of Communion</em> on a programme with Mozart\'s Requiem. LIGC also sang <em>Hold On (Change Is Coming)</em> in its own set.'],
    'collaborations' => [['Bold Tendencies', 'Hosted by', 'Opening of the 2024 programme, Peckham, with a newly commissioned piece for six choirs.']],
    'outcomes' => [86], 'links' => [],
  ],
  [
    'title' => 'The space gig: singing to the International Space Station', 'slug' => 'space-gig-2024', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2024-09-25'],
    'body' => ['On 25 September 2024 the choir sang live to an astronaut aboard the International Space Station from Savoy Place, the Institution of Engineering and Technology\'s home on the Embankment. The set was <em>Rise Up</em>, <em>Ain\'t No Mountain High Enough</em>, <em>Grateful For</em>, <em>Can\'t Give Up Now</em> and <em>Proud</em>, and the link-up was filmed.'],
    'collaborations' => [['IET London: Savoy Place', 'Hosted by', 'Live link-up with the ISS, 25 September 2024.']],
    'outcomes' => [], 'links' => [['https://www.youtube.com/watch?v=gIHCEvNgl-M', 'Watch the space gig']],
  ],
  [
    'title' => 'Sense International\'s 30th anniversary gala', 'slug' => 'sense-international-gala-2024', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2024-09-26'],
    'body' => ['A group of LIGC singers performed at Sense International\'s 30th anniversary gala ball at Church House, Westminster, on 26 September 2024, attended by HRH The Princess Royal. The choir sang <em>Rise Up</em>, <em>Can\'t Give Up Now</em> and <em>Proud</em>.'],
    'collaborations' => [['Sense International', 'Performed for', '30th anniversary gala ball, Church House Westminster.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Messiah: Re-Imagined with Tom Randle', 'slug' => 'messiah-re-imagined-2026', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2026-12-10'],
    'body' => ['LIGC performs in <em>Messiah: Re-Imagined</em> at Milton Court Concert Hall, a Barbican concert on Thursday 10 December 2026. The show is written, arranged, directed and produced by the American conductor and opera singer Tom Randle.'],
    'collaborations' => [['Tom Randle', 'Produced by', 'Writes, arranges, directs and produces the show.'], ['Barbican Centre', 'Hosted by', 'Milton Court Concert Hall, Guildhall School, 10 December 2026.']],
    'outcomes' => [115], 'links' => [],
  ],
  [
    'title' => 'On stage with SK Shlomo', 'slug' => 'sk-shlomo-2023', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2023-06-03', '2023-12-09'],
    'body' => ['Twenty LIGC singers opened for and performed with the beatboxer SK Shlomo in <em>Breathe</em> at Rich Mix, Shoreditch, on 3 June 2023.', 'Shlomo returned the favour that December as special guest at both of the choir\'s first-ever double Christmas shows, <em>A Blessed Christmas</em>, at All Saints Notting Hill on 8 and 9 December 2023.'],
    'collaborations' => [['SK Shlomo', 'Performed with', 'Breathe at Rich Mix, June 2023; guest set at the Christmas concerts, December 2023.'], ['Rich Mix', 'Hosted by', '3 June 2023.']],
    'outcomes' => [55, 63, 64], 'links' => [['https://skshlomo.com/theshow', 'SK Shlomo: Breathe']],
  ],
  [
    'title' => 'BIMM London choir concerts', 'slug' => 'bimm-london-2023', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2023-04-30', '2023-12-14'],
    'body' => ['LIGC was the special guest at the BIMM Choirs Concert at All Saints Notting Hill on 30 April 2023, opening a show that brought together the college choirs from BIMM Bristol, Birmingham and London. The connection runs through our artistic director, who lectures at BIMM London and leads its choir.', 'In December 2023 the choir performed a joint Christmas concert with the BIMM London choir in Earl\'s Court.'],
    'collaborations' => [['BIMM London', 'Performed with', 'BIMM Choirs Concert, 30 April 2023; joint concert, 14 December 2023.']],
    'outcomes' => [49, 74], 'links' => [],
  ],
  [
    'title' => 'Afterglow: singing while Kate Mayer paints', 'slug' => 'kate-mayer-afterglow-2026', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2026-01-21', '2026-09-10'],
    'body' => ['The abstract artist Kate Mayer, who sings with the choir, invited LIGC into her 2026 exhibitions. The choir sang at her solo show at 54 The Gallery in Mayfair on 21 January 2026.', 'On 8 September 2026, a first for LIGC, the choir sang <em>Count Your Blessings</em> at Holy Trinity Barnsbury while Kate painted live in response to the music; the session was filmed and streamed. Two days later the choir performed three songs at the private view of her exhibition <em>Afterglow</em>.'],
    'collaborations' => [['Kate Mayer', 'Performed with', 'Live painting to the choir\'s singing, 8 September 2026, and the Afterglow private view, 10 September 2026.']],
    'outcomes' => [120], 'links' => [],
  ],
  [
    'title' => 'Common & Kind and Rainbow Against Borders', 'slug' => 'common-and-kind', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2018-06-29', '2019-06-27'],
    'body' => ['LIGC was the house choir for Common & Kind\'s concert at Union Chapel on 29 June 2018, singing <em>Humankind</em>, <em>Hear Us!</em> and <em>Senzenina</em> alongside Rainbow Against Borders, a choir of LGBT refugees, after joint rehearsals through May and June. The choir had already collected for Common & Kind at its 2017 event.', 'The partnership continued with a 2,000-guest gala at The O2 on 30 September 2018, where the choir sang <em>Humankind</em> with a small orchestra, a concert at Amnesty International\'s Human Rights Action Centre on 30 November 2018, and a return to Union Chapel on 27 June 2019.'],
    'collaborations' => [['Common & Kind', 'Performed for', 'Union Chapel 2018 and 2019, The O2 gala and Amnesty concert, 2018.'], ['Rainbow Against Borders', 'Performed with', 'Joint rehearsals and concert, Union Chapel, 29 June 2018.'], ['Union Chapel', 'Hosted by', '29 June 2018 and 27 June 2019.'], ['Amnesty International UK', 'Hosted by', 'Human Rights Action Centre, 30 November 2018.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Anin Rose: songs with our artistic director', 'slug' => 'anin-rose', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2017-09-01', '2020-06-01'],
    'body' => ['Between 2017 and 2020 the choir worked repeatedly with the singer-songwriter Anin Rose, the artist name of our artistic director Nina. A small LIGC group filmed the acoustic video for <em>Need You</em> in September 2017, and in 2018 members backed a crowdfunded record with choir harmonies.', 'Her songs became part of the choir\'s own shows: <em>Why Can\'t I</em> premiered at <em>Glorious!</em> in June 2018, <em>Stand For Something</em> was sung at the 2018 winter concert and at Michelle Obama\'s book launch, and <em>Too Much</em> at <em>Brighter Day</em> in June 2019. Seven LIGC voices backed her headline concert with the charity The Haven + London in October 2018, and her band LYEN opened <em>Christmas Magic</em> in December 2019.'],
    'collaborations' => [['Anin Rose', 'Performed with', 'Videos, a crowdfunded record and joint concerts, 2017 to 2020.'], ['The Haven + London', 'Performed for', 'Anin Rose headline concert, 19 October 2018.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'LIGC Extra: the auditioned small group', 'slug' => 'ligc-extra', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2022-09-17', '2024-12-18'],
    'body' => ['LIGC Extra is the choir\'s auditioned small group. It began as fortnightly small-group rehearsals in 2022, held its first auditions that September and relaunched under its current name in February 2023, with each musical director taking a month in turn. The group opens the second half of our summer concerts and learns a dozen extra songs each autumn.', 'Its main job is the choir\'s paid seasonal work: weekly festive performances in Covent Garden, Seven Dials and Carnaby Street, airport and hotel appearances and corporate parties, including the Covent Garden Christmas lights switch-on and a breakfast-television appearance in November 2024. The income funds our concerts and paid for the choir\'s first recordings.'],
    'collaborations' => [],
    'outcomes' => [68], 'links' => [],
  ],
  [
    'title' => 'Choir Fun Fridays at The Albany', 'slug' => 'choir-fun-fridays', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2024-04-26', '2026-10-30'],
    'body' => ['Since April 2024 the choir has run its own cabaret nights in the downstairs bar of The Albany on Great Portland Street, reviving a member\'s long-running showcase as a regular LIGC social. Members perform solos to piano or backing track, group numbers close the show and the bar turns into a club afterwards.', 'Each night has a theme: Stars in Their Eyes, LIGC does Eurovision, Casino Royale, As Heard on TV, Raw Material (members\' original songs and spoken word), Superheroes, Games Night, LIGC goes to Nashville, 90s Smash Hits and a Black History Month special. There are four or five a year.'],
    'collaborations' => [['The Albany', 'Hosted by', 'Downstairs bar, Great Portland Street, from April 2024.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => '7 July memorial service, Hyde Park', 'slug' => 'hyde-park-memorial-service', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2020-07-07', '2026-07-07'],
    'body' => ['Every July for more than a decade a specially formed LIGC vocal ensemble has sung at the memorial service for the victims of the 7 July 2005 London bombings at the memorial in Hyde Park, an invitation-only ceremony that is streamed live.', 'In 2020 the choir recorded songs in small groups for the 15th-anniversary film, and sang live again from 2021. For the 21st anniversary in 2026 the ensemble sang <em>Lean On Me</em>, <em>You Will Be Found</em> and <em>Call My Name</em>.'],
    'collaborations' => [],
    'outcomes' => [110], 'links' => [['https://www.youtube.com/watch?v=ppsdxQStliU', 'The 2020 memorial film'], ['https://youtu.be/2AcHBZVZFU0', 'The choir\'s recorded song for the 2021 service']],
  ],
  [
    'title' => 'Grenfell anniversary', 'slug' => 'grenfell-anniversary', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2018-06-02', '2024-06-14'],
    'body' => ['The choir\'s home at All Saints Notting Hill is a short walk from Grenfell Tower, and LIGC has sung at the community\'s anniversary commemorations: a small group at the memorial on 2 June 2018, and performances at Hope Gardens on 14 June 2023 and 14 June 2024, when members also sang solo slots.', 'During lockdown in 2020 the choir recorded <em>How Great Thou Art</em> at home for the third-anniversary service at All Saints, which was broadcast online on 14 June 2020.'],
    'collaborations' => [],
    'outcomes' => [51, 84], 'links' => [['https://m.youtube.com/watch?v=Qtocpl7Dc6I', 'The 2020 anniversary service']],
  ],
  [
    'title' => 'Singing for the Mayor of London', 'slug' => 'mayor-of-london-civic-services', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2018-11-09', '2026-11-06'],
    'body' => ['LIGC has become a regular at the Mayor of London\'s civic occasions. Since 2018 a small group has sung at the annual Remembrance service at City Hall most Novembers, and the choir has appeared at the Mayor\'s carol service at Southwark Cathedral, the Citizenship Award ceremony at the Queen Elizabeth Hall in 2019 and International Peace Day at City Hall and The Scoop in 2019.', 'In May 2021 a small group sang at the opening of the Blossom Garden, the Covid memorial in the Queen Elizabeth Olympic Park, and in 2022 the choir performed at the Mayor\'s St George\'s Day celebration on Trafalgar Square and at City Hall\'s service of remembrance for Queen Elizabeth II.'],
    'collaborations' => [['Mayor of London', 'Performed for', 'Remembrance services, carol services and civic ceremonies since 2018.']],
    'outcomes' => [20, 32, 38, 67], 'links' => [],
  ],
  [
    'title' => 'Also Festival, Compton Verney', 'slug' => 'also-festival', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2018-06-30', '2023-07-15'],
    'body' => ['For five festival summers LIGC made the trip to Also Festival at Compton Verney in Warwickshire: two sets in 2018, an overnight stay with Saturday evening and Sunday morning sets in 2019, the lake stage in 2021, and in 2022 a set of our own plus a mash-up of <em>I Am What I Am</em> and <em>Born This Way</em> with the festival\'s co-founder Juliet Russell.', 'In 2023 the choir stepped up to support the headline act on the main stage.'],
    'collaborations' => [['Also Festival', 'Hosted by', '2018, 2019, 2021, 2022 and 2023.'], ['Juliet Russell', 'Performed with', 'Joint set, 9 July 2022.']],
    'outcomes' => [34, 54], 'links' => [],
  ],
  [
    'title' => 'Christmas concerts for charities', 'slug' => 'charity-christmas-concerts', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2018-12-05', '2026-12-17'],
    'body' => ['December is the choir\'s busiest month, and much of it is spent singing for charities. LIGC has sung at African Promise\'s carol service at St Giles-in-the-Fields every December since 2018, and at Our Moon\'s Christmas fundraiser every year since 2023.', 'Other regular partners include the Motor Neurone Disease Association\'s Christmas concert (2024, 2025 and 2026), Kidney Research UK\'s candlelight carols (2025 and 2026), Maggie\'s (2022 and 2023) and, in individual years, the MS Society, the Peter Jones Foundation and Pancreatic Cancer UK.'],
    'collaborations' => [['African Promise', 'Performed for', 'Carol service at St Giles-in-the-Fields, every December 2018 to 2024.'], ['Our Moon', 'Performed for', 'Christmas fundraiser, 2023 to 2026.'], ['Motor Neurone Disease Association', 'Performed for', 'Christmas concerts 2024, 2025 and 2026.'], ['Kidney Research UK', 'Performed for', 'Candlelight carols 2025 and 2026.'], ['Maggie\'s', 'Performed for', 'December 2022 and 2023.'], ['Pancreatic Cancer UK', 'Performed for', 'Christmas concert, 7 December 2026.']],
    'outcomes' => [22, 39, 40, 41, 70, 72, 95, 96, 98, 114, 119, 126, 131], 'links' => [],
  ],
  [
    'title' => 'Community festivals, runs and open days', 'slug' => 'community-events', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2021-07-25', '2026-09-20'],
    'body' => ['Not every LIGC performance is a concert. Since 2022 the choir has sung a cappella sets for the runners at the London Landmarks Half Marathon every spring, from the Strand and One New Change, and has added the Skyscraper Challenge in 2023 and the Saucony Shoreditch Half in 2026.', 'Closer to home the choir is a regular at the Tavistock Festival on Portobello Road, at the Bankside Open Spaces Trust\'s community festival in Southwark, at Islington Fire Station\'s open days and at neighbourhood events such as the Olden Community Garden music festival.'],
    'collaborations' => [['London Landmarks Half Marathon', 'Performed for', 'Singing for the runners every year since 2022, plus the Skyscraper Challenge 2023.'], ['Tavistock Festival', 'Performed for', 'Portobello Road, 2021, 2022 and 2024.'], ['Bankside Open Spaces Trust', 'Performed for', 'Community festival, 2022 and 2023.'], ['London Fire Brigade', 'Performed for', 'Islington Fire Station open days, 2023, 2024 and 2026.']],
    'outcomes' => [16, 36, 43, 52, 60, 61, 75, 80, 85, 89, 108, 122, 125, 127], 'links' => [],
  ],
  [
    'title' => 'Gospel Sunday', 'slug' => 'gospel-sunday', 'status' => 'completed', 'published' => TRUE,
    'dates' => ['2018-03-01', '2019-10-13'],
    'body' => ['Through 2018 and 2019 the choir ran Gospel Sunday, a roughly monthly open day for anyone who wanted to sing: an afternoon gospel workshop led by our then artistic director, a shared dinner and an evening music service at Bloomsbury Central Baptist Church on Shaftesbury Avenue. Members busked and flash-mobbed around town to spread the word.', 'Special editions took Gospel Sunday to Union Chapel\'s Big Sing in June 2018 and, in October 2019, brought in our visiting choirs from Sweden and Norway with guest leaders from the IDMC Gospel Choir.'],
    'collaborations' => [['Bloomsbury Central Baptist Church', 'Hosted by', 'Monthly workshops and evening services, 2018 to 2019.'], ['Union Chapel', 'Hosted by', 'Gospel Sunday at the Big Sing, 24 June 2018.']],
    'outcomes' => [], 'links' => [],
  ],
  [
    'title' => 'Workshops with guest leaders', 'slug' => 'member-workshops', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2019-10-10', '2026-08-01'],
    'body' => ['Most years the choir pauses regular rehearsals for a run of workshops with guest leaders. The October 2019 intensive brought movement with Omari Carter of STOMP, a jazz masterclass with Pete Churchill of the Royal College of Music and a gospel day with John Fisher of the IDMC Gospel Choir; the 2020 lockdown series on Zoom covered vocal health with Elly Hoyt, yoga for singers, songwriting and reading music.', 'Since then members have worked with Mary Bourne, Lizzy Renihan, Jane Wheeler, Harlano Weekes, Barbara Amoakohene Pursey, Craig Lees, Clarence Hunte and Sabrina Altan, among others, and with Stockholm\'s Immanuel Gospel Choir in May 2026. One member\'s song written in a songwriting workshop was performed at the 2023 summer concert.'],
    'collaborations' => [['Omari Carter', 'Workshop with', 'Movement workshops, October 2019 and February 2021.'], ['Pete Churchill', 'Workshop with', 'Masterclass, October 2019.'], ['Elly Hoyt', 'Workshop with', 'Vocal health, July 2020.'], ['Mary Bourne', 'Workshop with', 'World songs and rounds, January 2021 and September 2022.'], ['Lizzy Renihan', 'Workshop with', 'September 2022, July 2025 and August 2026.'], ['Jane Wheeler', 'Workshop with', 'July 2023 and April 2024.'], ['Craig Lees', 'Workshop with', 'February 2025.'], ['Clarence Hunte', 'Workshop with', 'July 2026.'], ['Immanuel Gospel Choir', 'Workshop with', 'Joint workshop, 2 May 2026.']],
    'outcomes' => [121], 'links' => [],
  ],
  [
    'title' => 'Our own concerts: summer, Easter and Christmas', 'slug' => 'our-concerts', 'status' => 'active', 'published' => TRUE,
    'dates' => ['2018-06-16', '2026-12-12'],
    'body' => ['LIGC has staged its own full-choir concerts with live band twice a year since 2018, when <em>Glorious!</em> at St James\'s Piccadilly was our first summer concert and <em>A Gospel Christmas</em> sold out Regent Hall. After <em>Brighter Day</em> and <em>Christmas Magic</em> in 2019 and a year lost to Covid, the choir returned with a Christmas reunion at St Pancras Church in December 2021.', 'Summer concerts followed at St Giles-in-the-Fields (<em>Higher Love</em>, 2022; <em>The Power of One</em>, 2023) and Christmas at Holy Trinity Sloane Street in 2022. From 2023 the Christmas concert became two shows at All Saints Notting Hill, and both sold out. <em>Optimistic</em> at St John\'s Smith Square in 2024 began the choir\'s partnership with Sinfonia Smith Square, which now hosts our Easter, summer and Christmas shows.'],
    'collaborations' => [['Sinfonia Smith Square', 'Hosted by', 'Easter, summer and Christmas concerts since 2024.']],
    'outcomes' => [17, 35, 42, 50, 63, 64, 82, 88, 91, 92, 93, 94, 107, 109, 116, 117], 'links' => [['https://www.youtube.com/playlist?list=PL31uGTLGMRq13YfOan1gx1wNDbyovgdEm', 'Videos from the 2022 Christmas concert']],
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
    'type' => 'project', 'title' => $p['title'], 'status' => $p['published'] ? 1 : 0,
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
  if (!$p['published']) { print "  unpublished (needs confirming): {$p['title']}\n"; }
}
print "Projects created: {$made['project']}\n";

drupal_flush_all_caches();
print "Done.\n";
