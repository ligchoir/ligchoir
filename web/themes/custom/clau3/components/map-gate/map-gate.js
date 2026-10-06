/**
 * @file
 * Reveal a third-party map once the visitor agrees.
 *
 * The map markup is parked in a <template>, so Leaflet is not initialised
 * and no tile is fetched until it is moved into the page. The choice is
 * recorded with Klaro under the service id when Klaro is on the page (so it
 * appears in "Manage privacy settings"), otherwise in localStorage.
 */
(function (Drupal, once) {
  'use strict';

  function klaroManager() {
    if (!window.klaro || typeof window.klaro.getManager !== 'function') {
      return null;
    }
    // The Klaro Drupal module keeps the manager and its config on its own
    // behaviour (klaro.drupal.js); getManager() without the config throws
    // before Klaro has been set up, so fall back to localStorage rather than
    // break the page.
    const klaroBehavior = (window.Drupal && Drupal.behaviors && Drupal.behaviors.klaro) || {};
    if (klaroBehavior.manager) {
      return klaroBehavior.manager;
    }
    const config = klaroBehavior.config || (window.drupalSettings && window.drupalSettings.klaro && window.drupalSettings.klaro.config) || undefined;
    if (!config) {
      return null;
    }
    try {
      return window.klaro.getManager(config);
    }
    catch (e) {
      return null;
    }
  }

  function hasConsent(service) {
    const manager = klaroManager();
    if (manager) {
      return manager.getConsent(service) === true;
    }
    try {
      return window.localStorage.getItem('ligc-consent-' + service) === 'yes';
    }
    catch (e) {
      return false;
    }
  }

  function giveConsent(service) {
    const manager = klaroManager();
    if (manager) {
      manager.updateConsent(service, true);
      manager.saveAndApplyConsents();
      return;
    }
    try {
      window.localStorage.setItem('ligc-consent-' + service, 'yes');
    }
    catch (e) {
      // Private mode: the map still opens for this page view.
    }
  }

  Drupal.behaviors.clau3MapGate = {
    attach(context) {
      once('clau3-map-gate', '[data-map-gate]', context).forEach((gate) => {
        const service = gate.dataset.service || 'openstreetmap';
        const template = gate.querySelector('.map-gate__template');
        const target = gate.querySelector('.map-gate__map');
        const button = gate.querySelector('.map-gate__button');
        if (!template || !target) {
          return;
        }
        const open = () => {
          if (gate.classList.contains('is-open')) {
            return;
          }
          target.appendChild(template.content.cloneNode(true));
          gate.classList.add('is-open');
          Drupal.attachBehaviors(target, window.drupalSettings);
        };
        if (hasConsent(service)) {
          open();
        }
        if (button) {
          button.addEventListener('click', () => {
            giveConsent(service);
            open();
          });
        }
      });
    },
  };
})(Drupal, once);
