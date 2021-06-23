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




            //$("#ligc-mob-menu-toggle").

              // Use context instead of document IF DRUPAL.

        });

        //     .ligc-togl-expand.ligc-togl-expand--open .menu-text {
        
        // credit:  https://www.fourfront.us/blog/jquery-window-width-and-media-queries/

        //Function to the css rule
        function checkSize(){
            if ($(".ligc-main-menu li").css("display") == "block" ){
                // we're at mobile width, so show the burger menu icon

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
                


                if ( !$("#ligc-togl-expand").hasClass("ligc-togl-expand--open") ) {
                  $(".region-primary-menu").css("display","none");
                }
              
  
               // nearly there - i think this should be a media query break point rather than in js
               //$("#block-clau2-main-menu").toggleClass('ligc-main-menu-hide');
               /*
               var togl_expd = document.getElementById('ligc-togl-expand');

               // mobile menu toggle hide/reveal
               togl_expd.addEventListener('click', function (e) {
                 // only for mobile
                  if ($(".ligc-main-menu li").css("display") == "block" ){
                   togl_expd.classList.toggle('ligc-togl-expand--open');
                  }
               });
               */

               // need to re-add the event each time because the html is destroyed I think
               // but just in case it's there, want to remove anything that was there before
               // credit: https://stackoverflow.com/a/11613013/227926
$('#ligc-togl-expand')
  .off('.toglexpandns') // remove all events in namespace upload
  .on({
      'click.toglexpandns': function(e) {
          e.stopPropagation();
          e.preventDefault();
          if ($(".ligc-main-menu li").css("display") == "block" ){
            // a click on the menu burger symbol occured 
            //
            var togl_expd = document.getElementById('ligc-togl-expand');
            togl_expd.classList.toggle('ligc-togl-expand--open');

                // nearly there - i think this should be a media query break point rather than in js
            //$("#block-clau2-main-menu").toggleClass('ligc-main-menu-hide');

            // TODO could put this inside the if and else - opposite settings
            $(".layout-container").toggleClass('ligc-overlay-menu-bg');

            if ( $(".region-primary-menu").css("display") == "none" ) {
              // menu is not displayed
              //
              // so display it
              //
              // mobile menu overlay is displayed
              $(".region-primary-menu").css("display","inline-block");
              $("#ligc-hdr").removeClass('ligc-hdr-hgt-init');
              $("#ligc-hdr").addClass('ligc-overlay-hdr-hgt-togl-expand-mob-menu');

              // reposition displayed menu as per design
              $(".ligc-main-menu").css('position', 'relative');
              $(".ligc-main-menu").css('top', '8em');
              $(".ligc-main-menu").css('left', '-9em');
            }
            else {
              $(".region-primary-menu").css("display","none");
              $("#ligc-hdr").removeClass('ligc-overlay-hdr-hgt-togl-expand-mob-menu');

              // reset position of menu when not in use
              $(".ligc-main-menu").css('position', '');
              $(".ligc-main-menu").css('top', '');
              $(".ligc-main-menu").css('left', '');
            }

            // these two classes are never on at the same time
            //
            // initially on, so a toggle will turn it off

            //
            // initially off, so a toggle will turn it on




          }
          else {
            // click on menu burger occured but we're not at mobile width

            $(".region-primary-menu").css("display","inline-block");
            $("#ligc-hdr").addClass('ligc-hdr-hgt-init');

            // reset position of menu when not in use
            $(".ligc-main-menu").css('position', '');
            $(".ligc-main-menu").css('top', '');
            $(".ligc-main-menu").css('left', '');

            //$("#ligc-hdr").removeClass('ligc-overlay-hdr-hgt-togl-expand-mob-menu');

           // $(".region-primary-menu").css("display","none");
            // nearly there - i think this should be a media query break point rather than in js
            //$("#block-clau2-main-menu").toggleClass('ligc-main-menu-hide');
          }
      }
      // ...
  });

  // other refs:
  // https://stackoverflow.com/questions/5963669/whats-the-difference-between-event-stoppropagation-and-event-preventdefault
  // https://api.jquery.com/on/#event-names
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
               // we're not at mobile width

                $("#ligc-mob-menu-toggle").empty();

                // nearly there - i think this should be a media query break point rather than in js
                //$("#block-clau2-main-menu").toggleClass('ligc-main-menu-hide');

                $(".layout-container").removeClass('ligc-overlay-menu-bg');

                $(".region-primary-menu").css("display","inline-block");

                $("#ligc-hdr").addClass('ligc-hdr-hgt-init');

                // remove mobile positioning of menu so that menu displayed in correct position at non-mobile widths
                $(".ligc-main-menu").css('position', '');
                $(".ligc-main-menu").css('top', '');
                $(".ligc-main-menu").css('left', '');
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
  