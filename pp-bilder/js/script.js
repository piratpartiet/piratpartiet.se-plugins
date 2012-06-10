(function ($) {

  "use strict";

  //noinspection UnnecessaryLocalVariableJS
  /**
   * Main object for code organization
   * @type {Object}
   */
  var PPBilder = {

    /**
     * Nonce needed for the API call
     *
     * @type {String}
     * @property nonce
     * @default ''
     */
    nonce:'',

    /**
     * Initializes the plugin
     * @param {String} nonce
     */
    init:function (nonce) {
      this.nonce = nonce;

      $(document).on('click', '#pp-bilder img', this.clickHandler);
    },

    /**
     * Handles click events for image selection
     */
    clickHandler:function () {
      var $this = $(this),
        filename = $this.data('filename'),
        image_id = $this.data('post-id');

      // The image ID or filename needs to be supplied by the backend to continue
      if (!image_id && !filename) {
        PPBilder.showError();
        return;
      }

      // Filename supplied signifies that the image hasn't been imported into WordPress media store
      if (filename) {

        // Import image into backend media store
        var jqxhr = PPBilder.importImage(filename);

        // The ajax call should return the ID of the image created
        jqxhr.done(function (data) {

          if ( /[0-9]+/.test(data) && data !== 0 ) {
            PPBilder.selectImage(data);
          } else {
            PPBilder.showError();
          }

        });
      }

      // Image ID supplied signifies the image is already imported and only needs to be selected for this post
      else {
        PPBilder.selectImage(image_id);
      }

      // Close the image selector after processing
      PPBilder.close();
    },

    /**
     * Calls the WordPress backend and asks it to import the image into the media store
     * @param {String} filename
     * @return {Object} a jQuery.Deferred
     */
    importImage:function (filename) {

      //noinspection JSUnresolvedVariable
      return $.post(ajaxurl, {
        action:'pp_bilder_import_image',
        filename:filename
      });
    },

    /**
     * Calls the WordPress frontend and sets the chosen image as thumbnail
     * @param {int} image_id
     */
    selectImage:function (image_id) {

      // WPSetAsThumbnail requires the post_id var to be set in the global scope
      window.post_id = parseInt(jQuery("#post_ID").val(), 10);

      WPSetAsThumbnail(image_id, PPBilder.nonce);
    },

    /**
     * Displays a generic error message
     */
    showError:function () {

      var $error = $('<div class="error">Ett fel uppstod när bilden skulle väljas.</div>');

      $("#pp-bilder").prepend( $error );

      setTimeout(function() {
        $error.fadeOut('slow', function() {
          $error.remove();
        });
      }, 2000);
    },

    /**
     * Closes the modal window
     */
    close:function () {
      $("#TB_closeWindowButton").trigger('click');
    }
  };

  /**
   * Expose object as a public API
   * @type {Object}
   */
  window.PPBilder = PPBilder;

}(jQuery));