/**
 * @file
 * Hero video: load and play the loop only when it is worth it.
 *
 * The markup ships a <video> with no source and no autoplay. This adds the
 * source and starts playback when the viewport is at least 600px wide (or the
 * editor switched the video on for phones), the visitor has not asked for
 * reduced motion, the browser does not report save-data, and the connection
 * is not known to be 2G. Otherwise the poster stays and nothing is downloaded.
 * Under 600px an upright version of the clip (data-video-src-phone) is used
 * when the editor supplied one, and then plays whatever the phones switch says.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.clau3Hero = {
    attach(context) {
      once('clau3-hero', '[data-hero]', context).forEach((hero) => {
        const video = hero.querySelector('.hero__video');
        const wide = window.matchMedia('(min-width: 600px)');
        const phoneSrc = hero.dataset.videoSrcPhone || '';
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const conn = navigator.connection || {};
        const slow = conn.saveData === true || /(^|-)2g$/.test(conn.effectiveType || '');
        if (!video || reduce || slow) {
          hero.classList.add('is-static');
          return;
        }
        let current = '';
        const choose = () => {
          const wideEnough = wide.matches;
          const src = (!wideEnough && phoneSrc) ? phoneSrc : hero.dataset.videoSrc;
          const onPhones = hero.dataset.videoOnPhones === '1' || (!wideEnough && phoneSrc !== '');
          if (!src || (!wideEnough && !onPhones)) {
            hero.classList.add('is-static');
            video.removeAttribute('autoplay');
            video.pause();
            current = '';
            return;
          }
          if (src === current) {
            return;
          }
          current = src;
          video.querySelectorAll('source').forEach((s) => s.remove());
          const source = document.createElement('source');
          source.src = src;
          source.type = 'video/mp4';
          video.appendChild(source);
          video.setAttribute('autoplay', '');
          hero.classList.remove('is-static');
          video.load();
          const attempt = video.play();
          if (attempt && attempt.catch) {
            attempt.catch(() => hero.classList.add('is-static'));
          }
        };
        choose();
        // A window dragged across 600px (or a phone turned sideways) gets the
        // clip that fits the new width.
        wide.addEventListener('change', choose);
      });
    },
  };
})(Drupal, once);
