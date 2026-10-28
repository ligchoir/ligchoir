# Front page hero media

The two files below are not in git (they show choir members; the repo holds
no identifiable people). Rob keeps the originals privately. Before running
`scripts/devops/front_page_2026.php`, copy them into this directory (they are
gitignored here) or set `LIGC_HERO_MEDIA_DIR` to wherever they are, e.g. on
the server:

    scp hero-1280.mp4 hero-poster.webp server03:/tmp/hero/
    LIGC_HERO_MEDIA_DIR=/tmp/hero ./drush php:script scripts/devops/front_page_2026.php

- `hero-1280.mp4`: 19 s silent loop, 1280x672, H.264 High, CRF 32, from
  1:20 in "If He Did It Before - London International Gospel Choir"
  (youtube.com/watch?v=RgNVtjKTOhI, LIGC's own channel). The top 6.67% of
  the frame (ceiling and lights above the choir, about the height of the
  LIGC initials in the header) is cropped off before scaling, which also
  makes the file a little smaller. Made with `media/hero-loop.sh` in the
  infrastructure repo:
  `hero-loop.sh -u 'https://www.youtube.com/watch?v=RgNVtjKTOhI' -s 80 -t 19 -p 90 -w 1280 -q 32 -C 6.67 -W 1920 -n hero-1280`
- `hero-poster.webp`: the frame at 1:30 with the same crop, 1920x1008, WebP q75.

`scripts/devops/front_page_2026.php` imports both as media items on first
run. To change the clip later, upload a new video through the media library
and pick it in the hero paragraph; these files are only the first import.
The hero component's box is 40:21 (`height: min(52.5vw, 70vh)`) to match.
