/**
 * @file
 * Menu toggle for the site header on narrow screens.
 *
 * The <nav> is a normal block in the document; this only flips an
 * aria-expanded button and a data attribute that the CSS reads. Escape closes
 * the menu and returns focus to the button.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.clau3Header = {
    attach(context) {
      once('clau3-header', '[data-site-header]', context).forEach((header) => {
        const button = header.querySelector('[data-menu-toggle]');
        const label = header.querySelector('.site-header__toggle-label');
        if (!button) {
          return;
        }

        const setOpen = (open) => {
          header.dataset.menuOpen = open ? 'true' : 'false';
          button.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (label) {
            label.textContent = open ? label.dataset.labelClose : label.dataset.labelOpen;
          }
        };

        setOpen(false);

        button.addEventListener('click', () => {
          setOpen(header.dataset.menuOpen !== 'true');
        });

        header.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && header.dataset.menuOpen === 'true') {
            setOpen(false);
            button.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
