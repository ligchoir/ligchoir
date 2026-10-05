/**
 * @file
 * Reveal the search form from its button; focus the input; Escape closes.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.clau3Search = {
    attach(context) {
      once('clau3-search', '[data-site-search]', context).forEach((root) => {
        const button = root.querySelector('.site-search__toggle');
        const panel = root.querySelector('.site-search__panel');
        if (!button || !panel) {
          return;
        }

        const setOpen = (open) => {
          panel.hidden = !open;
          button.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) {
            const input = panel.querySelector('input[type="search"], input[type="text"]');
            if (input) {
              input.focus();
            }
          }
        };

        button.addEventListener('click', () => setOpen(panel.hidden));

        root.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            button.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
