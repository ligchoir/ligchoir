# New front end: theme, front page, events, projects and media workflow

Written 2026-10-04 on branch `feat/new-theme` from Rob's wireframe sketch
and brief, the local site (Drupal 11.4.8, config in `config/sync`), the
Solent Metropolitan code base
(`~/2-work/09-community/projects/thesolentmetropolitan`) and drupal.org
on that date. This is the design brief and work plan for Phase D of the
roadmap (`docs/dev/d11-upgrade-roadmap.md`); it replaces the short Phase D
and Phase E notes there. It will take several sessions; section 15 is the
order of work and section 16 the decisions still open.

Rob's brief, in one paragraph: a new custom theme that pulls through the
existing content; a front page of header, looping video with overlaid
intro, events grid, about blurb, three columns (book us, our projects,
join us) and a footer; the same header and footer on every other page;
events teasers that link to the event page rather than straight to the
organiser; a listing/map pair of tabs for events; a new Projects section
(Project, Collaborator) that houses LIV; a photo workflow that crops to a
fixed aspect ratio with upscaling and makes teaser sizes; the same colours
adjusted for contrast; an HTTP-auth review copy at new.ligchoir.com; and
use of newer Drupal page-building tools (Layout Builder, possibly Canvas)
as a way to learn them.

## 1. Where we start

**Content types** (node counts from the local database, which is a copy
of live minus recent uploads).

| Type | Count | Fields | Notes |
|------|-------|--------|-------|
| home | 1 | field_background_media, field_content | The front page. `field_content` holds a quote, two text paragraphs (a "Welcome" blurb and the "ABOUT" blurb), a Book us call to action, a vertical space and a YouTube video paragraph (youtu.be/RgNVtjKTOhI). Four orphan Book us call-to-action paragraphs still name two parent fields (`field_paragraph_reference`, `field_main_call_to_action_area`) that no longer exist on the type. |
| landing_page | 7 | field_background_media, field_content | /about, /events, /join, /book, /events/past-events, /privacy, /liv. Each is a background image plus a list of paragraphs. |
| page | 0 | body, layout_builder__layout | Layout Builder is enabled on its full display with one node-links block (old experiment, no content). |
| event | 107 | body, field_background_media, field_colour_background, field_colour_text, field_tickets, field_time_tbc, field_venue, field_when | `field_tickets` is a call_to_action paragraph whose link goes to the organiser ("details to follow", "organiser's page"). `field_venue` references the 60-term `venues` vocabulary, which has no fields of its own (name only). Colours are `modifiers_color` terms (8, each a hex colour). |
| team_member | 9 | body, field_image, field_order, field_role | Created 2026-10-04 to replace the musical_team vocabulary. `field_role` is free text. |
| webform | 1 | | The Join us form page. |

**Paragraph types** (13): about_intro, anchor, call_to_action, contact,
content_2_column, image, quote, social_and_contact_links, text,
vertical_space, video (media reference, accepts `video` and
`remote_video`), view (viewsreference), youtube_video. Several carry
their own colour fields (text, quote, view, content_2_column, contact).

**Media types**: image (crop widget), video (mp4 file), remote_video
(oEmbed), audio, document. `field_background_media` on home and
landing_page already accepts image, video and remote_video.

**Menu** (`main`): Events, About, Join us, Book us, L• I• V. No submenu
items. The `footer` menu exists but is empty.

**Theme `clau2`**: no base theme, 13 CSS files (about 2,100 lines), one
341-line jQuery file that drives the mobile menu overlay, 45 templates,
Vegur hosted locally, Bootstrap Icons SVGs via CSS mask for the social
icons. Regions: help, page_specific_action_tabs, header, primary_menu,
content, footer. The home and event templates write `<style>` blocks
with the original uploaded file as a CSS background, bypassing every
image style. Layout Builder, classy and stable are installed only for
historical reasons (roadmap Phase C notes).

**Images today**: media `image` uses the `image_widget_crop` widget with
crop types `header_cover_background` (6:4, soft limit 2000×1333),
`profile_pic` (1:1, 250×250) and `generic`. The `background_image_*`
styles each do `image_scale_and_crop` to a fixed box and then `crop_crop`
with the header crop; nothing on the front end uses them except the
`narrow` responsive style. GD on both local and server supports WebP
(local also AVIF; the server build does not, hence the status report
warning, so AVIF is out for now). JPEG quality is 75.

**Tools on Rob's Mac**: yt-dlp, ffmpeg, cwebp, avifenc. No HandBrakeCLI,
no ImageMagick. `~/2-work/08-infrastructure/infrastructure/media/igpad.sh`
is the model for a media script (ffmpeg, options, never touches originals).

## 2. Page builder: Canvas, Layout Builder or Paragraphs

Facts as of 2026-10-04:

- **Drupal Canvas** (drupal.org/project/canvas, formerly Experience
  Builder) is at 1.12.0 (2026-09-25), requires core ^11.3, so it installs
  on this site. It ships with Drupal CMS 2.0 as the default editor. It is
  released monthly and the drupal.org page says the only integration
  points for modules and themes are Single Directory Components (SDC) and
  blocks; wider APIs are still being stabilised. Canvas wants a sparse
  `page.html.twig` (header, content, footer; the content region not
  wrapped in a centring container, so it can do full-bleed sections).
  Contrib bridges exist for Paragraphs (paragraphs_components,
  paragraph_sdc) and there is a Canvas migration initiative for Layout
  Builder and Paragraphs sites. Canvas does not use the Paragraphs data
  of the existing pages; those pages would be rebuilt in Canvas.
- **Layout Builder** is core and stable, stays in Drupal 12, and is the
  right tool for *display layouts of a content type* (per-bundle default
  layout, no per-node overrides). Per-page overrides are where editors
  get lost, and Canvas is core's answer to that use case.
- **Paragraphs** is what every existing page is made of. The sketch maps
  onto paragraph types the site already has (text, call_to_action, view,
  video) plus two or three new ones.
- **SDC** is in core (stable since 10.3, no module to enable). A component
  is a directory with a `*.component.yml`, a Twig file, CSS and optional
  JS; a Twig template includes it with `{% include 'clau3:hero' with {...} %}`.
  Paragraph templates, Layout Builder blocks and Canvas can all use the
  same component.

**Recommendation**: build the theme as SDC components first, keep
Paragraphs as the editing model for the launch, and add Canvas and
Layout Builder in the places where they are the right tool.

1. Theme `clau3` with components: `header`, `menu`, `hero-video`,
   `section`, `card`, `card-row`, `event-card`, `section-strip`, `footer`.
   The paragraph templates are thin wrappers that pass field values into
   the components. This is the part that carries all the design work and
   it is reusable by every builder.
2. Launch the front page and landing pages on Paragraphs (existing
   content, existing editor habits, no migration risk).
3. Layout Builder for the **Project** content type's full display (a new
   type, so no legacy): sections and blocks laid out once per bundle, no
   per-node overrides. That is the Layout Builder skill worth having.
4. Canvas on a **spike branch** once the components exist: enable it,
   build the Projects landing page (or a copy of the front page) from the
   same SDCs, and judge editor experience, output and update cadence. If
   it is good, move the front page to it; if not, nothing is lost because
   the components and content are builder-independent. Design
   `page.html.twig` the Canvas way (header, content, footer, no centring
   wrapper on content) from day one so the spike is cheap.

Why not Canvas first: 1.x is moving monthly, the site has one developer,
the choir team edits the pages, and a broken update on the editing tool
blocks everything. The SDC layer gives Rob the learning on Canvas without
betting the launch on it.

## 3. Theme `clau3`

- **Name** `clau3`, `base theme: false`, like Solent's `customsolent`.
  Vegur fonts copied from clau2. Breakpoints file like Solent's
  (`customsolent.breakpoints.yml`) so responsive image styles can use
  them; Solent's `large` breakpoint has a copy-paste bug (`min-width:
  1200px and max-width: 1199px`), do not copy it.
- **Regions**: header, primary_menu, search, content, footer, plus help
  and the local-tasks region clau2 added for the edit tabs. Content is
  rendered edge to edge; each component aligns itself to the 1200px grid
  with the Solent pattern `padding-inline: max(1em, calc((100% - var(--container-max)) / 2 + 1em))`.
- **CSS**: design tokens on `:root` (colours, spacing, container width,
  as in Solent's `elements.css`), one base stylesheet, then one CSS file
  per component inside the component directory. No preprocessor, no
  build step (clau2 has none either; the old gulp theme in
  `docs/frontend/old-bootstrap-scss-theme` stays archived).
- **JS**: no jQuery. One small vanilla script per component that needs
  it (menu toggle, video reduced-motion check). Use `once` and
  `Drupal.behaviors` so BigPipe and AJAX keep working.
- **Templates**: start from Solent's `page.html.twig`, `html.html.twig`,
  `region--footer.html.twig`, `field--node--title.html.twig`,
  `menu--main.html.twig`, the search templates and the system-page
  handling (`_customsolent_system_page()`), stripping the Solent
  specifics. Do not attach `classy/node`; once clau3 is default,
  uninstall classy and stable (roadmap Phase C leftovers).
- **Icons**: keep the Bootstrap Icons SVG-mask approach from clau2's
  `_social.css`; add search, menu (burger), close, chevron, map pin,
  calendar, play.
- **Theme settings**: none at first; Solent's `theme-settings.php` can be
  borrowed later if the team wants to switch the video off per season.

## 4. Header and menu (sketch item 1)

Desktop: logo left, menu items on the same line, search as the last item
(an icon button that opens the search form, as Solent's `search` region
does). The logo is the same size on every page, small enough to share the
line with six items at 1200px; if it does not fit at 992px, the fallback
is the logo on its own line above the menu, which the sketch allows.
Items, in this order (decision 16.2, 2026-10-06): Home, Events & News,
Join us, Book us, About, Projects, Search. "Events & News" is the events
landing page (events first, as the most visited page); news about the
choir is told through Projects, which the events page links to.

Mobile (below 800px): logo and a burger button on one line; the menu
opens **in document flow**, pushing the content down (clau2 overlays the
page with a 100vh absolutely positioned panel; that is the behaviour Rob
wants to lose). Implementation: the `<nav>` is a normal block element
after the header bar, hidden with the `hidden` attribute, shown by the
button; an optional `grid-template-rows: 0fr → 1fr` transition for the
slide, disabled under `prefers-reduced-motion`. Nothing is
`position: fixed`, so there is no scroll locking or overlay backdrop.

Accessibility requirements, checked before launch:

- The toggle is a `<button aria-expanded aria-controls="main-menu">`
  with a visible text label ("Menu") as well as the icon; Escape closes
  it and returns focus to the button.
- All items are real links, in DOM order, reachable with Tab; the search
  item is a button that reveals the search form, and the form's input
  gets focus when it opens.
- Visible focus style on every link and button (Solent's `menu-focus.css`
  is the reference), `aria-current="page"` on the current item (Solent's
  `menu-state.js` does this client-side; Drupal's menu system sets
  `is-active` server-side, which is enough without submenus).
- The skip link stays (`#main-content` in `html.html.twig`).
- Contrast of menu text against the bar at least 4.5:1 (section 12).

From Solent, take the `menu--main.html.twig` structure and the focus and
mobile CSS; leave the mega-menu panels, the `customsolent.js` open/close
logic and most of `menu-desktop.css` (about two thirds of the 1,650 lines
are submenu work LIGC does not have).

## 5. Front page

The front page stays a `home` node edited with Paragraphs. Sections, top
to bottom, with the paragraph type that produces each:

| Sketch | Section | Paragraph type | Component |
|--------|---------|----------------|-----------|
| 1 | Header and menu | (theme regions) | header, menu |
| 2 | Looping video with intro text and "Read more" | **hero** (new) | hero-video |
| 2 | "Events" heading, grid of upcoming event cards, "View all events" | view (existing, `events` view, new `cards` display) | event-card |
| 3 | About us blurb | text (existing paragraph 43) | section |
| 4 | Book us / Our projects / Join us, three columns | **card_row** with three **card** children (new) | card-row, card |
| 4 | General enquiry | call_to_action or contact (existing) | section |
| 5 | Footer | (theme region, section 9) | footer |

### 5.1 Hero video (sketch item 2)

New paragraph type `hero`: `field_media` (media: video or image),
`field_poster` (media: image, shown before the video plays and whenever
video is off), `field_heading`, `field_text` (short, two or three
sentences, from the existing "Welcome to the London International Gospel
Choir!" paragraph), `field_link` ("Read more" to /about). The component
renders:

```html
<section class="hero">
  <video autoplay muted loop playsinline preload="metadata" poster="…webp">
    <source src="…/hero-1280.mp4" type="video/mp4">
  </video>
  <div class="hero__panel">           <!-- solid colour block, not an overlay gradient -->
    <h1>…</h1><p>…</p><a class="button" href="/about">Read more</a>
  </div>
</section>
```

Design rules: the text sits on a solid panel (brand colour or black at
full opacity) so contrast does not depend on the frame behind it; the
video has no sound and no controls. **Phones (decision 2026-10-06)**: the
`<video>` is rendered with no source and no autoplay; `hero-video.js`
adds the source and plays only when the viewport is 600px or wider (or
the hero's "Play the video on phones" switch is on), the visitor has not
asked for reduced motion, and the browser does not report save-data or a
2G connection. Below 600px the poster shows through the responsive `hero`
style (about 30 KB) and nothing else is downloaded; without JavaScript,
the same. This is decided in the browser from what it knows (width,
motion preference, connection), not by user-agent sniffing on the server
(mobile_detect would need a cache context on every page and still could
not tell wifi from mobile data). A small "Watch the full video on
YouTube" text link under the panel is a plain link, not an embed, so it
needs no Klaro consent and does not clutter the hero. Autoplay with the
`muted` attribute is allowed by every current browser.

Aspect ratio and the fold: the hero box is `aspect-ratio: 16 / 9` capped
at `max-height: 70vh` on desktop, with `object-fit: cover` on the video,
so a 1280×720 file fills a 1200px-wide hero and the events heading is
visible below it on a 1080px-tall screen. On phones the same file is
cropped to roughly 4:5 by `height: 60vh; object-fit: cover`; the subject
should therefore be centred in the frame when the clip is chosen. A
true 16:9 hero on mobile would be only 200px tall and not worth the
download, which is why the mobile branch may show the poster instead.

Video specification:

| Item | Value | Why |
|------|-------|-----|
| Length | 15 to 25 s, cut on a natural loop point | Loops are noticed less when short; file size |
| Frame | 1280×720, 25 fps (one file; add 1920×1080 only if the 720 looks soft on a large monitor) | Size versus sharpness |
| Codec | H.264 (libx264) High profile, CRF 26 to 28, `-movflags +faststart`, no audio track | Plays everywhere, starts before fully downloaded |
| Size target | Under 2.5 MB for 20 s at 720p | Comparable to one large hero photo on a slow connection |
| Poster | First or best frame as WebP 1280×720, about 60 to 80 KB | Shown instantly and whenever video is off |
| Source | yt-dlp from LIGC's own YouTube channel (the choir owns the footage; do not use third-party uploads), or a camera original if the team has one | Rights |

Pipeline, to become `media/hero-loop.sh` in the infrastructure repo next
to `igpad.sh` (same conventions: options, never overwrite the source):

```bash
yt-dlp -f "bv*[height<=1080][ext=mp4]" -o source.mp4 "https://youtu.be/RgNVtjKTOhI"
# trim (start, duration), scale, drop audio, web-optimise
ffmpeg -ss 00:01:12 -t 20 -i source.mp4 -an -vf "scale=1280:-2,fps=25" \
  -c:v libx264 -profile:v high -crf 27 -preset slow -pix_fmt yuv420p \
  -movflags +faststart hero-1280.mp4
ffmpeg -ss 00:01:12 -i source.mp4 -frames:v 1 -vf "scale=1280:-2" poster.png
cwebp -q 75 poster.png -o poster.webp
```

Storage: upload `hero-1280.mp4` as a `video` media item (the type already
exists and accepts mp4) and the poster as an `image` media item, so the
team can change the clip without a deploy. HandBrake is not needed;
ffmpeg does the trimming and encoding and is already installed.

### 5.2 Events grid

A new `cards` display on the `events` view: upcoming events, 8 per page
no pager, rendered with the `event_card` node view mode (title, date,
venue, image from `field_background_media` through the `card` image
style, section 11). "View all events" is the view's "more" link to
/events. Cards link to the event page (section 7). The grid is CSS grid,
4 columns at 1200px, 2 at 800px, 1 below 600px.

### 5.3 About and the three columns

The About section reuses the existing "ABOUT" text paragraph. The three
columns are a `card_row` paragraph holding three `card` paragraphs
(`field_image` media, `field_heading`, `field_text`, `field_link`),
rendered with the `card-row` component as a 3-column grid that stacks
below 800px. Book us links to /book, Our projects to /projects, Join us
to /join. The same card type serves the Projects landing page.

## 6. Other pages

About, Join us, Book us, Privacy keep their paragraphs and get the new
header, footer and typography. Their landing-page background image moves
from a full-screen CSS background to a `hero` paragraph with an image
(same component as the front page, image instead of video), which brings
them under the image workflow. The Join us webform page and the webform
templates (`webform-confirmation.html.twig`) are re-styled; the Klaro
consent dialog styles in `_common.css` carry over.

## 7. Events

### 7.1 Teaser and event page

Teasers (list and cards) link to the event node only; the organiser link
leaves the teaser. On the event page the existing `field_tickets`
call-to-action paragraph is shown as the "Tickets and details at the
organiser" button, with `rel="noopener"` and the organiser's domain
visible in the link text, and only while the event is in the future (the
current template already hides it afterwards). Past events show
"This event has passed" and, when the event is an outcome of a project
(section 8), a "Part of our project: …" link.

The per-event colour pair (`field_colour_background`,
`field_colour_text` from `modifiers_color`) is replaced by a single
`field_colour_scheme` list field with vetted pairs (section 12), and the
event hero is rendered through the responsive image style instead of
`<style>` with the original file. The `modifiers_color` vocabulary can
then be removed (roadmap Phase B3 leftover).

### 7.2 Listing, past events and map: the strip

/events gets a strip of three real links, not an ARIA tab widget, so each
view has its own shareable URL and works without JS:

    Upcoming · Past · Map

This is Solent's section strip (`slnt-section-strip.html.twig` in
`customsolent_helpers`, CSS at `section-listing.css` lines 771 to 880):
a flex row with a 4px underline on the `aria-current="page"` item, which
turns into a stacked list of 44px rows below 600px and never scrolls
sideways. For LIGC it is a `section-strip` SDC with a static item list
(Upcoming /events, Past /events/past-events, Map /events/map) rendered
by the events landing page template; no controller or path processor is
needed because the three paths already exist or are plain view pages.

### 7.3 Map

Venues have no location data today. Decision 16.6 (2026-10-06): use the
**Geolocation** module (drupal/geolocation), not geofield + leaflet; it is
actively developed, Google-optional, and brings Leaflet with OpenStreetMap
tiles and address geocoding in its own submodules. Needed:

1. `geolocation` with its Leaflet submodule; a `field_location`
   (geolocation) on the `venues` vocabulary with the address-geocoding
   widget (Nominatim/OpenStreetMap provider), so an editor types an
   address and the point is filled in; the 60 existing terms get their
   points from a CSV and a `drush php:script` (one evening's work). Which
   Geolocation submodules provide Leaflet and Nominatim at the version
   installed is confirmed at step 4.
2. A `map` page display on the events view using Geolocation's Leaflet
   views style: markers from the venue's location, popups with event
   title, date and link. Check that Leaflet's JS and CSS are served from
   `web/libraries` and not from a CDN, in keeping with the no-CDN rule.
3. Map tiles are a request to a third party (tile.openstreetmap.org)
   and OSM's tile policy forbids heavy use. Gate the map behind Klaro
   like the YouTube embeds (a "Load map" button, the pattern is already
   in `_common.css`); a paid tile provider with a key stays the fallback.

Past events: the Past tab is the existing `block_events_past` display.
Linking a past event to a project is the `field_outcomes` reference on
the project (section 8), shown on the event page as the reverse
relationship via a small view or a preprocess lookup.

## 8. Projects and collaborators

Projects becomes a top-level section (/projects) that houses L•I•V and
any later project. The menu item "L• I• V" goes; /liv stays as the path
alias of the LIV project node so old links keep working.

**Content type `project`**

| Field | Type | Notes |
|-------|------|-------|
| title | | |
| field_image | media image | card and hero |
| body | text | Description |
| field_status | list: planned, active, completed | drives sorting and the "current/past" split on the landing page |
| field_dates | smart_date (optional) | for time-boxed projects |
| field_collaborators | paragraph `collaborator`, unlimited | see below |
| field_outcomes | entity reference to event, unlimited | past or future events; auto-complete |
| field_links | link, unlimited | Spotify release, press, videos |
| field_content | paragraphs | the existing page-building types, so LIV's text and booking form (`contact` paragraph with the `liv_book` webform) move across unchanged |

Full display built with **Layout Builder** (per-bundle default layout,
overrides disabled): hero (image and title), two-column section with body
left and collaborators right, outcomes list, links, then `field_content`.
Teaser display is the `card` component; a `projects` view lists cards on
/projects (a `landing_page` node with a view paragraph, like /events).

**Collaborator**: decision 16.3 (2026-10-06): a **content type**, because
the same collaborator (another choir, a producer, a venue) can take part
in more than one project. `collaborator` is the party itself and replaces
the `organisation` type first planned: title, body, `field_image` (media
image: logo or portrait), `field_website` (link), `field_kind` (list:
organisation, person). The project's `field_collaborators` is an
entity reference to collaborator nodes. The per-project role (performed
with, commissioned, hosted, produced, recorded, funded…) is a small
`collaboration` paragraph on the project (collaborator reference, roles
from a `collaboration_roles` vocabulary, optional note) if the team wants
roles shown; decided at step 5 when the first real project is entered.
Collaborators have no public page at first (no path alias; shown only on
project pages), which avoids publishing a page per partner before there is
anything to say.

**Person**: decision 16.4 (2026-10-06): `team_member` is renamed in place
to `person` (bundle label and machine name, with the migration-script
pattern from `scripts/devops/musical_team_to_nodes.php`): title,
field_image, body, field_role (free text), `field_team_order` (integer,
empty for non-team people), plus `field_website` (link). The Team members
view filters on `field_team_order` not empty. A guest musical director
who collaborates on a project is a `collaborator` of kind person, not a
`person`, unless they are also part of the team.

Rob's three scenarios in this model:

- **LIV**: a project, status active, no collaborators, outcomes are its
  concerts, `field_content` carries the current /liv page.
- **A joint performance with another choir**: project with one
  collaborator (organisation: the choir, role "performed with"), one
  outcome (the event).
- **Singles on Spotify**: project, status completed, links to Spotify,
  collaborators for producer and studio if wanted, no outcomes.

## 9. Footer (sketch item 5)

Solent's footer is a custom block type (`footer_menu_custom_block`:
copyright, year, privacy link, social links paragraph) in the `footer`
region, styled by `footer-base.css` (full-width coloured band, 1200px
inner) and `footer-end.css`. LIGC does the same with less:

- A `footer` custom block type: `field_text` (copyright line; the year
  comes from a `[date:custom:Y]` token in the text or the template),
  `field_links` (link, unlimited: Privacy, Contact, Join us, Book us),
  `field_social` (the existing `social_and_contact_links` paragraph, so
  the icon markup the team already maintains is reused).
- The `footer` SDC (built in step 1): a **slate grey** band
  (`--ligc-slate`, #37474f; white text on it is 9.3:1), white text at 85%
  opacity, links at least 4.5:1, social icons on their coloured discs as
  now (Rob, 2026-10-06, items 10 and 12) with the contrast fixes in
  section 12, stacked on mobile.
- The empty `footer` system menu is deleted or used for the links instead
  of `field_links`; a menu is easier for the team to edit, so prefer the
  menu and drop `field_links`.

## 10. Search

Core `search` with the `node_search` page is enabled already. Add the
search form block to the `search` region, the Solent search templates
(`templates/search/*`) and a `search-result` component. Index status is
checked on cron; `install_cron.sh` from the webform retention work must
be in place on the server (automated cron is off).

## 11. Photo workflow

Goal: an editor uploads any photo, picks the subject once, and every
place on the site shows it at the right shape and size without the
hand-made letterbox images that the event banners needed.

**Modules**: keep `crop` (installed). Add `focal_point` (2.x, Drupal 11
release on drupal.org; it depends on crop). Retire `image_widget_crop`
once the media form uses the focal point widget: one click on the subject
replaces drawing a crop per aspect ratio, and the same point serves every
style. If the team still wants a manual crop for a particular shape, the
two widgets can coexist on different crop types.

**Shapes** (aspect ratios the design uses). Correction made while
building step 2: Focal Point stores one point per image in its own crop
type (`focal_point`, created by the module); the shapes are not crop
types but the target boxes of the image styles below, which all read that
one point. The old crop types (`generic`, `header_cover_background`,
`profile_pic`) are retired with their styles at launch.

| Shape | Ratio | Used by |
|-------|-------|---------|
| wide | 16:9 | hero images, event page banner |
| card | 3:2 | event cards, project cards, three-column cards |
| square | 1:1 | people (replaces `profile_pic`), collaborator logos |

**Image styles**, one family per crop type, each effect chain being
`focal_point_scale_and_crop` to the target box, then `image_convert` to
WebP. Scale-and-crop in core and in focal_point **upscales** a smaller
source to the target box (that is why the contrib module
"image_scale_and_crop_without_upscale" exists); the result is soft but
the shape is uniform, which is what Rob asked for. Styles:

| Style | Box | Where |
|-------|-----|-------|
| wide_800, wide_1200, wide_1600, wide_2000 | 16:9 | hero and banners via responsive style `hero` |
| card_400, card_800 | 3:2 | cards via responsive style `card` |
| square_200, square_400 | 1:1 | people, logos |
| media_library, thumbnail | existing | admin |

Responsive image styles `hero` and `card` use `sizes` (`100vw` for hero,
`(min-width: 1200px) 25vw, (min-width: 800px) 50vw, 100vw` for cards)
with the `width` descriptors, so one `<img srcset>` per image and no
breakpoint multipliers. The old `background_image_*`,
`header_cover_background` and `max_*` styles are deleted after the
templates stop using them.

**Why WebP, and AVIF** (Rob's question, 2026-10-06): WebP is supported by
every browser in use since Safari 14 (2020), so a WebP derivative needs no
JPEG fallback, and at the same visual quality it is about 25 to 35 per
cent smaller than JPEG. AVIF is smaller again (roughly another 20 to 30
per cent) and also universal in browsers since 2024, but producing it is a
**server** matter: Drupal's GD toolkit can only write AVIF when the
server's PHP GD extension was built with libavif, and the status report
says this server's build was not (the local ddev PHP is). Options are a
PHP GD build with libavif on the server, or the ImageMagick toolkit module
with ImageMagick installed on the server. Neither is a repo change, so the
plan is WebP now for every derivative (replacing JPEG output), and AVIF as
a follow-up once the server can encode it; the image styles then just
switch the convert effect. Originals stay as uploaded.

**Quality and size**: WebP at quality 80 for photos (set in the GD
toolkit settings; check in `GDToolkit::save()` which quality setting the
WebP branch reads at 11.4), JPEG fallback not needed (WebP support is
universal in current browsers), originals kept as uploaded. Upload
limit stays at the media type's current value; the crop types get soft
limits at the largest style (2000 px wide) and no hard limits, so a small
photo can still be placed. Budget: hero under 250 KB at 1600 wide, card
under 60 KB at 800 wide. AVIF waits until the server's GD build supports
it (status report warning).

**Editor guidance** (one page in `docs/functionality-documentation`):
upload the largest original you have, click the subject, and check the
three previews the focal point widget shows.

## 12. Colours and accessibility

Existing palette, from `clau2/css/styles` and `_social.css`, with WCAG
contrast ratios (text needs 4.5:1, large text and icons 3:1):

| Colour | Use | Against | Ratio | Verdict |
|--------|-----|---------|-------|---------|
| #d63836 brand red | buttons, Klaro | white text | 4.69 | passes AA for text, keep; hover #b52f2d 6.16 |
| #d63836 | text on black | black | 4.48 | borderline; use #ff9b99 (10.4) for red text on black |
| #325e1c dark green | text | #c9e1bd pale green | 5.44 | passes |
| #77b259 green | backgrounds with white text | white | 2.53 | fails even for icons; use #1a7a3a (5.40) behind white text, keep #77b259 only as a decorative band with dark text (#333 on it is 4.5+) |
| #f4daa6, #f9c9bf, #c9e1bd, #f3faef | panel backgrounds with #333 text | #333 | 8.5 to 19.7 | pass |
| rgb(200,200,200) menu text | menu on black | black | about 12 | passes; white on black for current item |
| Instagram #E53777 | social circle, white icon | white | 4.08 | passes 3:1 for icons |
| Facebook #4364a2 | white icon | white | 5.86 | passes |
| YouTube #ff0000 | white icon | white | 4.00 | passes 3:1 |
| Spotify #1db954 | white icon | white | 2.59 | fails; use #17863d (4.65) or a black icon on the brand green |
| modifiers_color terms: pink warm #BE84AA with white | event boxes | white | 2.97 | fails; yellow lime stores #FFFFFF by mistake |

Decisions: keep the brand red and the dark green as the two accents;
define them once as tokens (`--ligc-red`, `--ligc-red-dark`,
`--ligc-green`, `--ligc-green-pale`, `--ligc-ink`, `--ligc-paper`); every
background token has a named text token it is paired with, and templates
never combine colours outside those pairs. The per-event colour pickers
become a `colour_scheme` list (red, green, black, cream) mapped to the
vetted pairs, which is what retires `modifiers_color`. Other checks for
launch: focus rings on every interactive element, 44px minimum touch
targets in the mobile menu and strip, `prefers-reduced-motion` honoured
by the video and the menu animation, heading order (one `h1` per page,
section headings `h2`), alt text on media images enforced by the media
form (already required), and a run of axe DevTools and the WAVE
extension on front, events, one event, about and the forms.

## 13. Review site new.ligchoir.com

A third environment on server03 alongside live and staging:
`deployment_environments/review` (own database restored from a live
backup, own files directory copied from live, own `settings/3`), a
release cut from `feat/new-theme` with `create_release.sh` (4th argument
is the branch), nginx server block for new.ligchoir.com with a Let's
Encrypt certificate and HTTP basic auth (`auth_basic` with an htpasswd
file outside the web root), `X-Robots-Tag: noindex` from nginx and
`robots.txt` disallow all. Drupal's `trusted_host_patterns` in that
environment's settings lists the new host. The toolkit changes (a third
environment number, the nginx template with basic auth) belong in the
infrastructure repo github.com/therobyouknow/infrastructure (confirmed
by Rob, 2026-10-06, item 13). ligchoir.com is a different domain from the live
internationalgospelchoir.uk; confirm its DNS is at the same provider
before the session that sets this up.

## 14. What stays as it is

Webforms and their anti-spam and retention work, Klaro consent, the
events importer, smart_date and the TBC module, pathauto patterns, gin
for admin, the deploy toolkit and `post_deploy.sh`. The clau2 theme stays
installed until clau3 is live, then is removed with classy, stable and
Layout Builder's field-blocks leftovers.

## 15. Order of work

Each step is a branch off `feat/new-theme` (or a commit on it while the
theme is not yet default), tested locally, then merged into
`feat/new-theme`; the review site deploys that branch. Nothing reaches
`main` until the team signs off the review site, then `feat/new-theme`
is merged and released as one version.

1. **Theme skeleton** — DONE 2026-10-06 (commit on `feat/new-theme`):
   `clau3` info, libraries, breakpoints, tokens, page/html templates,
   components `brand`, `header`, `menu`, `search`, `footer`, system pages
   (log in, password, 403, 404), block placements and `clau3` as the
   default theme in config (this branch only; `main` stays on clau2).
   The front page, landing pages, events, team and forms render with the
   clau2 paragraph templates and CSS copied into `css/legacy/` and
   `templates/{content,paragraphs,field,views,webform}`. Deferred to later
   steps: the footer custom block type (section 9; the footer component
   takes the social block and a template-side legal line for now), the
   search results page styling (core markup inside the frame), and the
   landing pages' tiny page title over the old background band, which the
   hero paragraph replaces in step 6.
2. **Media workflow** — DONE 2026-10-06 (on `feat/new-theme`): focal_point
   2.1.3 installed and enabled; image styles `wide_800/1200/1600/2000`,
   `card_400/800`, `square_200/400` (focal-point scale and crop, then
   convert to WebP); responsive styles `hero` (sizes 100vw) and `card`;
   the media image form uses the focal point widget with previews instead
   of the manual crop widget; WebP originals allowed; GD quality 80 (the
   GD toolkit uses its one quality setting for WebP too). Derivatives
   generate on demand with a centred point for existing media, so no
   re-save was needed. First use: the team grid portraits now come from
   `square_400`. Editor guidance: `docs/functionality-documentation/images.md`.
   Left for launch (step 9): uninstall image_widget_crop, delete the old
   crop types and `background_image_*`, `max_*`, `profile_pic`,
   `crop_thumbnail` styles once no template uses them.
3. **Front page** — DONE 2026-10-06 (on `feat/new-theme`): paragraph types
   `hero` (video, poster image, heading, text, Read more link, full-video
   link), `card` and `card_row`; components hero-video, card, card-row and
   event-card; node view mode `event_card` and the events view's `cards`
   display (upcoming, eight, "View all events"); `media/hero-loop.sh` in
   the infrastructure repo, and the first clip (19 s from 1:20 of "If He
   Did It Before", 1280x720 CRF 32, 2.6 MB, poster 1920 wide) committed
   under `scripts/content/front-page/`. `scripts/devops/front_page_2026.php`
   imports the media, builds the paragraphs (hero, events cards, About
   text, card row Book us / Our projects / Join us, the existing call to
   action re-pointed to the general contact anchor), sets the order,
   deletes the YouTube embed, vertical space and old Welcome paragraphs,
   and removed the twelve orphan paragraphs whose parent fields were gone.
   Run it after `cim` on each environment; it is idempotent. Open point:
   the events grid shows a slate "LIGC" placeholder for the many events
   without a photo; a per-event photo is an editor task, not a theme one.
4. **Events** — DONE 2026-10-06 (on `feat/new-theme`), pending Rob's view
   on the scheme names (16.8): teasers link to the event page only
   (organiser link removed from the teaser and past view modes); new event
   page (component `event-page`: image through the `hero` responsive style,
   details box in the scheme colours, date line, venue, body, organiser
   button with its domain shown and only while the event is in the future,
   "This event has passed" otherwise); `field_colour_scheme` list (paper,
   red, green, ink, cream) on events with
   `scripts/devops/event_colour_schemes.php` (every event used the same
   white/dark-red pair, so all 107 map to `paper`; the two old colour
   pickers are off the form, the fields and `modifiers_color` go at
   launch); the Upcoming / Past / Map strip (component `section-strip`) on
   /events, /events/past-events and /events/map; Geolocation 4.0 with its
   Leaflet submodule, `field_location` and an optional `field_address` on
   venues, `scripts/devops/geocode_venues.php` (Nominatim, 60 of 60 venues
   placed, 13 after an address was added), the events view `map` page
   display, Leaflet 1.9.3 served from `web/libraries/leaflet` by the small
   `ligc_maps` module (the stock provider loads it from unpkg), and the
   `map-gate` component: the map markup waits in a `<template>` until the
   visitor presses "Show the map", so no tile or script is fetched before
   consent; the choice is recorded with Klaro as the `openstreetmap`
   service. Also from the side chat: `scripts/devops/menu_2026.php` sets
   the menu to Home, Events & News, Join us, Book us, About, and swaps
   L• I• V for Projects once /projects exists (step 5); the brand lock-up
   now runs the full name across the width of "LIGC" (1.05rem under
   2.6rem on desktop; measured equal at 234px).
5. **Projects** — DONE 2026-10-07 (on `feat/new-theme`): `team_member`
   relabelled **Person** (Drupal cannot rename a bundle's machine name in
   place, so it stays `team_member`; Team order is now optional and the
   team view shows only people who have one; `field_website` added);
   content types **collaborator** (kind, logo or portrait, about, website;
   no path alias) and **project** (image, status planned/active/completed,
   dates, collaborators, outcomes, links, page content paragraphs; pathauto
   `/projects/[node:title]`); the project full display is **Layout
   Builder**, per bundle with overrides off, four sections (image + title +
   status; description beside dates, collaborators and links at 67/33;
   outcomes as event cards; page content edge to edge); `projects` view
   (cards) and the `/projects` landing page; `scripts/devops/projects_2026.php`
   creates the landing page, turns the L•I•V landing page into a project at
   `/liv` (description from its text paragraph, booking form kept as page
   content, old page deleted) and points the front page card at /projects;
   `scripts/devops/menu_2026.php` then replaces L• I• V with Projects in the
   menu. Event pages show "Part of our project: …" from `field_outcomes`.
   Not done: the optional `collaboration` paragraph for per-project roles
   (decide when the first collaborator is entered).
6. **Other pages and polish**: landing pages onto the hero paragraph,
   typography, forms, Klaro styling, accessibility audit (section 12).
7. **Review site** and the team's feedback rounds.
8. **Canvas spike** on a branch off `feat/new-theme`, after step 5, with
   a written verdict in this document.
9. **Launch**: merge, release, uninstall clau2/classy/stable/
   image_widget_crop, delete old image styles and `modifiers_color`,
   update the roadmap.

## 16. Decisions (answered by Rob 2026-10-06 unless marked open)

1. Builder: **agreed** with section 2 (SDC + Paragraphs now, Layout
   Builder on Project, Canvas as a spike).
2. Menu: **Events in the bar**, labelled "Events & News"; order Home,
   Events & News, Join us, Book us, About, Projects, Search (section 4).
3. Collaborator: **content type**, because one collaborator can be in
   several projects (section 8).
4. `team_member` to `person`: **rename in place** (section 8).
5. Video source: **LIGC's own channel**, youtube.com/@LIGChoir/videos;
   rights are the choir's own and the review round can discuss the clip.
6. Map: **Geolocation module** (not geofield), OpenStreetMap tiles via
   Leaflet, still Klaro-gated (section 7.3).
7. Focal point: **yes** (section 11).
8. OPEN: colour scheme list for events (red, green, black, cream) or a
   different set of vetted pairs. Needed at step 4.
9. OPEN: ligchoir.com DNS access for the `new` record, and who gets the
   HTTP auth login. Needed at step 7.
10. Social icons keep their coloured discs (section 9).
11. WebP now, AVIF when the server can encode it (section 11).
12. Footer band is slate grey, not black (section 9).
13. new.ligchoir.com is set up through the infrastructure repo
    (section 13).

## Sources

- Drupal Canvas project page and 1.12.0 release:
  https://www.drupal.org/project/canvas and
  https://www.drupal.org/project/canvas/releases/1.0.0 ("Works with
  Drupal ^11.3"; 1.0 released 2025-12-04, see
  https://dri.es/drupal-canvas-1-0-released).
- Canvas theme guidance (sparse page template, no centring wrapper):
  https://www.thedroptimes.com/node/65907
- Focal Point module: https://www.drupal.org/project/focal_point;
  Image Widget Crop: https://www.drupal.org/project/image_widget_crop;
  the upscaling behaviour of scale-and-crop is the reason
  https://www.drupal.org/project/image_scale_and_crop_without_upscale exists.
- Leaflet module (depends on Geofield, OpenStreetMap default layer):
  https://www.drupal.org/project/leaflet
- Solent section strip: `web/modules/custom/customsolent_helpers/templates/slnt-section-strip.html.twig`
  and `web/themes/custom/customsolent/css/section-listing.css` (lines
  771 to 880) in the Solent repo; footer:
  `templates/block/block--footermenucustomblock.html.twig`,
  `css/footer-base.css`, `css/footer-end.css`; menu:
  `templates/navigation/menu--main.html.twig`, `js/menu-state.js`,
  `css/menu-*.css`.
- Contrast ratios computed with the WCAG 2.x relative luminance formula
  on 2026-10-04 (script in the session; re-run with any online checker).
