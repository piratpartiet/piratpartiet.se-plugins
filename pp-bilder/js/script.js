(function($){

  var PPBilder = {

    init: function() {
      $(document).on('click', '#pp-bilder img', this.clickHandler);
    },

    clickHandler: function() {
      var $this    = $(this),
        filename = $this.data('filename'),
        post_id  = $this.data('post-id');

      if ( !post_id && !filename ) {
        PPBilder.showError();
        return;
      }

      if ( filename ) {
        var jqxhr = PPBilder.importImage( filename );

        jqxhr.done(function(data) {
          /[0-9]+/.test(data) ?
            PPBilder.selectImage(data) :
            PPBilder.showError();
        });
      } else {
        PPBilder.selectImage(post_id);
      }
    },

    importImage: function(filename) {

      //noinspection JSUnresolvedVariable
      return $.post(ajaxurl, {
        action:'pp_bilder_import_image',
        filename:filename
      });
    },

    selectImage: function(post_id) {
      console.log('todo: implement selectImage');
    },

    showError: function() {
      console.log('todo: implement showError');
    }
  };

  PPBilder.init();

}(jQuery));