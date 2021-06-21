(function ($, Drupal) {
    Drupal.behaviors.aboutus = {
      attach: function (context, settings) {




        // credit: https://css-tricks.com/working-with-javascript-media-queries/
        // but jquery check for a css class event better I think
        // https://www.fourfront.us/blog/jquery-window-width-and-media-queries/

        $(document).ready(function() {
            // run test on initial page load
            checkSize();
        
            // run test on resize of the window
            $(window).resize(checkSize);


            var togl_expd = document.getElementById('ligc-togl-expand');

            // mobile menu toggle hide/reveal
            togl_expd.addEventListener('click', function (e) {
              // only for mobile
               if ($(".ligc-main-menu li").css("display") == "block" ){
                togl_expd.classList.toggle('ligc-togl-expand--open');
               }
            });

            //$("#ligc-mob-menu-toggle").

              // Use context instead of document IF DRUPAL.

        });

        //     .ligc-togl-expand.ligc-togl-expand--open .menu-text {
        
        // credit:  https://www.fourfront.us/blog/jquery-window-width-and-media-queries/

        //Function to the css rule
        function checkSize(){
            if ($(".ligc-main-menu li").css("display") == "block" ){
                // your code here

              // credit: https://stackoverflow.com/a/5360831/227926
               var menuicon = 
                 '<a href="#" id="ligc-togl-expand" class="ligc-togl-expand"> \
                   <div class="menu-icon"> \
                   <span class="top"></span> \
                   <span class="middle"></span> \
                   <span class="bottom"></span> \
                   </div> \
                   <div class="menu-text"> \
                   <span class="icon-burger">MENU</span> \
                   <span class="icon-close">CLOSE</span> \
                   </div> \
                 </a>';

                // credit: https://stackoverflow.com/a/6813294/227926
               if ($('#ligc-mob-menu-toggle').is(':empty')){
                 //do something)
                 $("#ligc-mob-menu-toggle").append(menuicon);
               }
                


               /* TODO

  function isEmpty( el ){
      return !$.trim(el.html())
  }
  if (isEmpty($('#element'))) {
      // do something
  }

               */
            }
            else {
                $("#ligc-mob-menu-toggle").empty();
            }


        } // end checksize

/*

@media screen and (max-width: 767px ) {
  .ligc-main-menu li {
    margin-right: 1.15em;
    display: block;
    padding-bottom: 1.5em;
  }

*/


/*
        function handleTabletChange(e) {
        // Check if the media query is true
          if (e.matches) {
            // Then log the following message to the console
            console.log('Media Query Matched!');
          }
        }


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
  