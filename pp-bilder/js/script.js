(function ($) {

  window.PPBilder = {

    nonce:false,

    init:function (nonce) {
      this.nonce = nonce;

      $(document).on('click', '#pp-bilder img', this.clickHandler);
    },

    clickHandler:function () {
      var $this = $(this),
        filename = $this.data('filename'),
        image_id = $this.data('post-id');

      if (!image_id && !filename) {
        PPBilder.showError();
        return;
      }

      if (filename) {
        var jqxhr = PPBilder.importImage(filename);

        jqxhr.done(function (data) {
          /[0-9]+/.test(data) && data != 0 ?
            PPBilder.selectImage(data) :
            PPBilder.showError();
        });
      } else {
        PPBilder.selectImage(image_id);
      }
    },

    importImage:function (filename) {

      //noinspection JSUnresolvedVariable
      return $.post(ajaxurl, {
        action:'pp_bilder_import_image',
        filename:filename
      });
    },

    selectImage:function (image_id) {
      window.post_id = parseInt(jQuery("#post_ID").val(), 10);
      WPSetAsThumbnail(image_id, PPBilder.nonce);
    },

    showError:function () {
      console.log('todo: implement showError');
    }
  };

}(jQuery));