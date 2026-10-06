/**
 * @file
 * Hero video: show the poster instead of the loop for visitors who prefer
 * reduced motion or have asked their browser to save data. The CSS already
 * hides the video under prefers-reduced-motion; this also stops the download.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.clau3Hero = {
    attach(context) {
      once('clau3-hero', '[data-hero]', context).forEach((hero) => {
        const video = hero.querySelector('.hero__video');
        if (!video) {
          return;
        }
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const saveData = navigator.connection && navigator.connection.saveData;
        if (reduce || saveData) {
          video.pause();
          video.removeAttribute('autoplay');
          video.querySelectorAll('source').forEach((s) => s.remove());
          video.load();
          hero.classList.add('is-static');
        }
      });
    },
  };
})(Drupal, once);
