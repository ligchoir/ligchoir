(function ($, Drupal) {
    Drupal.behaviors.aboutus = {
      attach: function (context, settings) {


        // Initial check
        handleTabletChange(mediaQuery);

        // Create a condition that targets viewports at least 768px wide
        const mediaQuery = window.matchMedia('(min-width: 768px)');
/*
        function handleTabletChange(e) {
        // Check if the media query is true
          if (e.matches) {
            // Then log the following message to the console
            console.log('Media Query Matched!');
          }
        }

        // Register event listener
        mediaQuery.addListener(handleTabletChange);
*/

        
        /*
        // credit: https://stackoverflow.com/a/47650045
        //jQuery for Page Scroll - Single page
        $('a[href*="#"]:not([href="#"])').click(function() {
          $('#w-nav-overlay-0').css('display', 'none'); // hide the menu overlay so that the item scrolled to can be seen
  
          if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
              var target = $(this.hash);
              target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                  if (target.length) {
                      $('html, body').animate({
                          scrollTop: target.offset().top
                      }, 500);
                      return false;
                  }
          }
        });
        //
        */
      }
    };
  })(jQuery, Drupal);
  