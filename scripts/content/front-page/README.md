# Front page hero media

- `hero-1280.mp4`: 19 s silent loop, 1280x720, H.264 High, CRF 32, from
  1:20 in "If He Did It Before - London International Gospel Choir"
  (youtube.com/watch?v=RgNVtjKTOhI, LIGC's own channel). Made with
  `media/hero-loop.sh` in the infrastructure repo:
  `hero-loop.sh -u 'https://www.youtube.com/watch?v=RgNVtjKTOhI' -s 80 -t 19 -p 90 -w 1280 -q 32 -n hero-1280`
- `hero-poster.webp`: the frame at 1:30, 1920 wide, WebP q78.

`scripts/devops/front_page_2026.php` imports both as media items on first
run. To change the clip later, upload a new video through the media library
and pick it in the hero paragraph; these files are only the first import.
