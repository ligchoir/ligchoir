(function ($, Drupal) {
    Drupal.behaviors.smart_date_time_tbc = {
      attach: function (context, settings) {

        /// credit: https://css-tricks.com/working-with-javascript-media-queries/
        // but jquery check for a css class event better I think
        // https://www.fourfront.us/blog/jquery-window-width-and-media-queries/

        $(document).ready(function() {
            // run test on initial page load





            //$("#ligc-mob-menu-toggle").

              // Use context instead of document IF DRUPAL.

        });


        $("#edit-field-time-tbc-value").change(function() {
          if(this.checked) {
            // https://stackoverflow.com/a/587408/227926
            $('.field--type-smartdate .time-start.form-time').each( function() {

                console.log( $(this).attr('value'));
                $(this).val('00:00:00');

            });

            $('.field--type-smartdate .time-end.form-time').each( function() {

                console.log( $(this).attr('value'));
                $(this).val('00:00:00');

            });
          }
        });

        //
      }
    };


  })(jQuery, Drupal);
  