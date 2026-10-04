# Webform external libraries: why the CDN list is not what visitors load

Written 2026-10-04, Webform 6.3.1, Drupal 11.4.8. File and line references
below are into `web/modules/contrib/webform/` at that version; re-check them
after a Webform update.

## The short version

- Webform knows about 14 optional JavaScript libraries. The status report
  lists all of them, and for any that are not downloaded into
  `web/libraries/` it says "CDN". That list is an **inventory of what Webform
  could use**, not a list of what any page loads.
- A library only reaches a visitor's browser when an element on the page
  **attaches** it. Drupal never loads a library because it exists; it loads it
  because something in the render array asked for it.
- Our four forms (book_us, contact, join_us, liv_book) use textfield, email,
  tel, textarea and one datetime element. None of those attach a CDN library,
  with one exception that is now excluded (below). As of this change **no
  public page loads anything from a library CDN**.
- The status-report entry itself is switched off (`requirements.cdn: false`
  in `webform.settings`). That only silences the report; it does not change
  what loads.

## How attachment works (Drupal core)

Drupal's asset system is pull-based. A module declares libraries in
`*.libraries.yml`; they are inert until a render element names one in
`#attached['library']`, at which point core adds its CSS and JS to that
response only. Core's documentation:

- "Adding assets (CSS, JS) to a Drupal module via *.libraries.yml",
  https://www.drupal.org/docs/develop/creating-modules/adding-assets-css-js-to-a-drupal-module-via-librariesyml
  (sections "Attaching a library to a specific element" and "Attaching
  configurable JavaScript"). The whole page is built on the rule that assets
  load only where attached.
- "Drupal 8+ assets are loaded only when needed" is the stated design of
  `Drupal\Core\Asset\AssetResolver`, which resolves the libraries attached to
  the page being rendered: `web/core/lib/Drupal/Core/Asset/AssetResolver.php`.

## How Webform applies that rule

Each Webform element plugin attaches only the library its feature needs, and
only when the feature is switched on for that element. Examples from the
module, all in `src/Plugin/WebformElement/` unless noted:

| Library in the status report | Attached where | Only when |
|---|---|---|
| jQuery: Select2 | `src/Plugin/WebformElement/Select.php:97` | the select element has `#select2` |
| jQuery: Chosen, Choices | `Select.php:102` and `:107` | `#choices` / `#chosen` set (both excluded on this site anyway) |
| jQuery: Input Mask | `src/Plugin/WebformElement/TextBase.php:114` | the text element has an `#input_mask` |
| jQuery: Text Counter | `TextBase.php:89` | the text element has a `#counter_type` |
| jQuery: International Telephone Input | `src/Plugin/WebformElement/Telephone.php:100` | `#international: true` on a tel element (ours is not) |
| jQuery: RateIt | `src/Element/WebformRating.php:137` | a rating element exists |
| Signature Pad | `src/Element/WebformSignature.php:83` | a signature element exists |
| Progress Tracker | `includes/webform.theme.template.inc:551` via library `webform.progress.tracker` (`webform.libraries.yml:239`) | the form has wizard pages with the progress bar on |
| Tippy.js (and Popper.js, its dependency) | `src/Plugin/WebformElementBase.php:772` (`webform.tooltip`) and the `webform.element.help` library (`webform.libraries.yml:303`, `:627`) | an element's description display is "tooltip", or an element has `#help` text |
| Code Mirror | `src/Element/WebformCodeMirror.php` | back-office editing (YAML, HTML, email handler twig) |
| Tabby | admin UI only | back-office editing |
| jQuery: Timepicker | `src/Element/WebformTime.php:79` | **any** time element, even in native mode; see below |

Verify any row with a grep, for example:

```bash
grep -n "attached" web/modules/contrib/webform/src/Plugin/WebformElement/Telephone.php
```

## The one exception, and what we did about it

The booking form's "Date and time (provisional)" element is a Webform
`datetime`. Its time part renders as a native `<input type="time">`
(`DateTime.php:54` sets `date_time_element` to `time` by default, and
`src/Element/WebformTime.php:195` renders `type="time"` unless the jQuery
picker is chosen). But `WebformTime::processWebformTime` attaches
`webform/webform.element.time` unconditionally (`WebformTime.php:79`), and
that library depends on `webform/libraries.jquery.timepicker`
(`webform.libraries.yml:870-875`), so the Timepicker JS and CSS were loaded
from cdn.jsdelivr.net on /book. The script then did nothing: it only acts on
`input[type="text"][data-webform-time-format]`, which only exists when the
jQuery picker mode is selected (`js/webform.element.time.js`). Webform calls
the library "a polyfill for HTML 5 time elements"
(`src/WebformLibrariesManager.php:380`); every current browser has a native
time input, so the polyfill is dead weight here.

Fix: `jquery.timepicker` is added to `libraries.excluded_libraries` in
`webform.settings` (admin UI: Structure > Webforms > Configuration >
Libraries, or `config/sync/webform.settings.yml`). Webform's
`hook_library_info_alter` removes excluded libraries from its library
definitions and from other libraries' dependency lists
(`src/Hook/WebformLibrariesHooks.php:121-139`), so the attach in
`WebformTime.php` still happens but now pulls in nothing external. The native
time input is unchanged.

The same hook is where the CDN URLs come from: when a library's directory is
absent from `web/libraries/`, its `/libraries/...` paths are rewritten to the
`cdn:` URL declared in `webform.libraries.yml` (`WebformLibrariesHooks.php:141`
and `includes/webform.libraries.inc:36-63`). So "CDN" in the status report
means exactly "would be served from the CDN if something attached it".

## How to check, any time

From anywhere with curl, list every external host each public page references.
Link targets (instagram.com, youtube.com, event venue sites) are expected;
library CDNs (cdn.jsdelivr.net, cdnjs.cloudflare.com, unpkg.com) are not:

```bash
for p in / /join /book /about /events; do
  printf '%-8s ' "$p"
  curl -sL "https://internationalgospelchoir.uk$p" \
    | grep -o -E '(src|href)="https?://[^"]+' \
    | grep -v internationalgospelchoir.uk | sed 's/.*:\/\///' | cut -d/ -f1 | sort -u | tr '\n' ' '
  echo
done
```

If a CDN host ever appears again, an element option that needs a library has
been switched on (or a new element type added). Either turn the option off,
exclude the library as above, or download it into `web/libraries/<dir>` so it
is served from our own domain (`drush webform:libraries:download`, or see
https://www.drupal.org/docs/contributed-modules/webform/webform-libraries).

## References

- Webform documentation, "Webform libraries":
  https://www.drupal.org/docs/contributed-modules/webform/webform-libraries
- Webform's own notes per library: `src/WebformLibrariesManager.php`,
  the `'notes'` key of each entry (e.g. line 380 for Timepicker).
- Drupal core, libraries and attachment:
  https://www.drupal.org/docs/develop/creating-modules/adding-assets-css-js-to-a-drupal-module-via-librariesyml
- The status-report entry: `WebformLibrariesManager::requirements()`
  (`src/WebformLibrariesManager.php:85`), which counts installed, excluded and
  CDN libraries and is governed by `requirements.cdn` in `webform.settings`.
