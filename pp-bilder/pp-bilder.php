<?php if ( !defined("ABSPATH") ) die();
/*
 Plugin Name: Piratpartiet bildhanterare
 Plugin URI: http://www.piratpartiet.se
 Description: Gemensamma bilder för flera bloggar
 Version: 1.0
 Author: Rickard Andersson
 Author URI: https://0x539.se
 */

define('PP_BILDER_DIRECTORY', '/tmp');

/**
 * Common image manager
 * @author Rickard Andersson
 * @version 1.0
 * @since 1.0
 */
class PP_Bilder {

	/**
	 * Name of the plugin directory
	 * @var string
	 * @since 1.0
	 */
	private $plugin_name = 'pp-bilder';

	/**
	 * If any images has been loaded by the plugin
	 * @var bool
	 * @since 1.0
	 */
	private $images_loaded = false;

	/**
	 * An array of all the images currently loaded by the plugin
	 * @var array
	 * @since 1.0
	 */
	private $images = array();

	/**
	 * Initializes the plugin
	 * @return PP_Bilder
	 * @since 1.0
	 */
	public function __construct() {
		add_action('admin_head', array($this, 'admin_head'));
	}

	/**
	 * Returns the full path to the directory / file on disk where the file resides.
	 * @param string $filename  Optional. If given the returned string includes the filename
	 * @return string
	 * @since 1.0
	 */
	private function get_image_full_path( $filename = null ) {

		$directory = PP_BILDER_DIRECTORY;

		if ( substr( $directory, -1, 1) != '/' ) {
			$directory .= '/';
		}

		if ( !$filename ) {
			return $directory;
		} else {
			return $directory . $filename;
		}

	}

	/**
	 * Returns the directory on disk where the thumbnails resides
	 * @return string
	 * @since 1.0
	 */
	private function get_thumbs_dir() {
		return ABSPATH . PLUGINDIR . '/'.  $this->plugin_name . '/thumbs/';
	}

	/**
	 * Returns an URL where the thumbnails can be loaded from
	 * @return string
	 * @since 1.0
	 */
	private function get_thumbs_url() {
		return plugins_url( $this->plugin_name . '/thumbs/' );
	}

	/**
	 * Returns the name of the thumbnail for a specific file
	 * @param string $filename  Original file name
	 * @return mixed
	 * @since 1.0
	 */
	private function get_thumb_filename( $filename ) {
		return preg_replace('/(\.(jpg|gif|png))$/i', '-thumb$1', $filename );
	}

	/**
	 * Returns the full path (directory and filename) to where on disk the thumbnail can be found
	 * @param string $filename  Original file name
	 * @return string
	 * @since 1.0
	 */
	private function get_thumb_full_path( $filename ) {
		return $this->get_thumbs_dir() . $this->get_thumb_filename( $filename );
	}

	/**
	 * Returns the full URL to where the thumbnail can be loaded
	 * @param string $filename  Original file name
	 * @return string
	 * @since 1.0
	 */
	private function get_thumb_url( $filename ) {
		return $this->get_thumbs_url() . $this->get_thumb_filename( $filename );
	}

	/**
	 * Returns the URL for the thumb for the filename specified
	 * @param string $filename  Original file name
	 * @return string
	 * @since 1.0
	 */
	private function get_thumb($filename) {

		$full_thumb    = $this->get_thumb_full_path($filename);
		$full_filename = $this->get_image_full_path($filename);

		if ( file_exists($full_thumb) ) {
			$file_mtime  = filemtime($full_filename);
			$thumb_mtime = filemtime($full_thumb);

			if ( $file_mtime > $thumb_mtime ) {
				$this->create_thumb($full_filename, $full_thumb, $this->get_thumbs_dir());
			}

		} else {
			$this->create_thumb($full_filename, $full_thumb, $this->get_thumbs_dir());
		}

		return $this->get_thumb_url( $filename );
	}

	/**
	 * Creates a thumbnail for the file
	 * @param string $src   Full path to the source image file
	 * @param string $dst_file     Full path to the destination image file
	 * @param string $dst_dir      Directory where the thumbnail should be saved
	 * @return string    Returns the filename
	 * @since 1.0
	 */
	private function create_thumb($src, $dst_file, $dst_dir) {

		$width = get_option('thumbnail_size_w');
		$height = get_option('thumbnail_size_h');

		$resize = image_resize($src, $width, $height, false, 'thumb', $dst_dir);

		if ( is_wp_error($resize) ) {
			copy($src, $dst_file);

			return $dst_file;

		} else {
			return $resize;
		}
	}

	/**
	 * Loads the images from the server directory
	 * @return bool
	 * @since 1.0
	 */
	private function load_images() {

		// First check some preconditions
		if ( !file_exists( PP_BILDER_DIRECTORY ) ) {
			return false;
		}

		if ( !is_readable( PP_BILDER_DIRECTORY) ) {
			return false;
		}

		if ( !file_exists( $this->get_thumbs_dir() ) ) {
			return false;
		}

		if ( !is_writable( $this->get_thumbs_dir() ) ) {
			return false;
		}

		$dir = opendir( PP_BILDER_DIRECTORY );

		if ( !$dir ) {
			return false;
		}

		// Iterate the directory and get/create thumbnails
		while ( $file = readdir($dir) ) {

			if ( is_dir($file) || $file == '.' || $file == '..' ) {
				continue;
			}

			switch ( strtolower( substr($file, -4, 4) ) ) {
				case '.jpg':
				case '.png':
				case '.gif':

					$image = new stdClass;

					$image->filename = $file;
					$image->thumb    = $this->get_thumb($file);

					$this->images[] = $image;
					break;
			}
		}

		$this->images_loaded = count($this->images) > 0;

		return $this->images_loaded;
	}

	/**
	 * Loads our callback to handle the meta box for featured images
	 * @return void
	 * @since 1.0
	 */
	public function admin_head() {

		global $wp_meta_boxes;

		// Only proceed if the postimagediv callback is available
		if ( !isset($wp_meta_boxes['post']['side']['low']['postimagediv']['callback']) ) {
			return;
		}

		$this->load_images();

		if ( $this->images_loaded ) {
			$wp_meta_boxes['post']['side']['low']['postimagediv']['callback'] = array($this, 'post_thumbnail_meta_box');
		}
	}

	/**
	 * Renders the post thumbnail meta box with our additions
	 * @since 1.0
	 * @return void
	 */
	public function post_thumbnail_meta_box() {
		post_thumbnail_meta_box();?>
		<a href="">Hämta bild från bildbanken</a>

			<div>
				<?php foreach ( $this->images as $image ) : ?>

				<img src="<?php echo $image->thumb ?>">

				<?php endforeach ?>
			</div>

		<?php
	}

}

new PP_Bilder();