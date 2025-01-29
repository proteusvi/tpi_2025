(function ($, once) {
  Drupal.behaviors.tpi_compare_list_popup = {
    attach: function (context, settings) {
      $(document).ready(function () {
        $(once('compare-list-toggle-btn','#compare-list-toggle-btn')).click(function () {
          console.log("click")
          $('#popup-compare-list').toggle(100);
        },
      );
        $(once('compare-list-close-btn','#compare-list-close-btn')).click(function () {
          console.log("click")
          $('#popup-compare-list').toggle(100);
        },
      );
    })
    }
  };

}(jQuery, once));
