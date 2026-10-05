# clau3

LIGC's front end from October 2026. No base theme, no jQuery, no build step.
Design brief and order of work: `docs/frontend/new-theme-plan.md`.

- `components/` single directory components (brand, header, menu, search,
  footer, more to come). Each has its `*.component.yml`, Twig, CSS and, where
  needed, a small vanilla JS behaviour. Drupal attaches a component's CSS/JS
  only where it is rendered.
- `css/tokens.css` is the palette and spacing; every colour on the site is a
  token, and every background token names the text token it is paired with.
- `css/legacy/` and most of `templates/content`, `templates/paragraphs`,
  `templates/views` and `templates/webform` are copies from clau2 so the
  existing pages render in the new frame. They are replaced step by step.
- Icons: Bootstrap Icons SVGs in `images/icons`, drawn through CSS masks.
