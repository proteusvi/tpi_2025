(function ($, Drupal) {
  Drupal.behaviors.button = {
    attach: function attach(context) {
      let counter = 0;
      $('.cl-component--my-button', context)
        .once('cl-component--my-button')
        .on('click', function (event) {
          event.preventDefault();
          counter++;
          $(this).html($(this).html().replace(/ \([0-9]*\)$/, '') + ' (' + counter + ')');
        });
    },
  };
})(jQuery, Drupal);
