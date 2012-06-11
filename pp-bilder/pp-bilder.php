<?php if ( !defined( 'ABSPATH' ) ) die();
/*
 Plugin Name: Piratpartiet bildhanterare
 Plugin URI: http://www.piratpartiet.se
 Description: Gemensamma bilder för flera bloggar
 Version: 1.0
 Author: Rickard Andersson
 Author URI: https://0x539.se
 */

define( 'PP_BILDER_DIRECTORY', '/tmp/' );

/**
 * Common image manager
 * @author  Rickard Andersson
 * @version 1.0
 * @since   1.0
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
	 * @var stdClass[]
	 * @since 1.0
	 */
	private $images = array();

	/**
	 * Initializes the plugin
	 * @return PP_Bilder
	 * @since 1.0
	 */
	public function __construct() {

		// Since 3.4 there's a hook for the ajax call, this won't work in WP <3.4
		add_action( 'wp_ajax_set-post-thumbnail', array( $this, 'wp_ajax_set_post_thumbnail' ), 0 );
		add_action( 'wp_ajax_pp_bilder_import_image', array( $this, 'ajax_import_image' ) );

		// Only add head and footer hooks on post.php and post-new.php
		add_action( 'admin_head-post-new.php', array( $this, 'admin_head' ) );
		add_action( 'admin_head-post.php', array( $this, 'admin_head' ) );

		add_action( 'admin_footer-post-new.php', array( $this, 'admin_footer' ) );
		add_action( 'admin_footer-post.php', array( $this, 'admin_footer' ) );

	}

	/**
	 * Returns the full path to the directory / file on disk where the file resides.
	 *
	 * @param string $filename  Optional. If given the returned string includes the filename
	 *
	 * @return string
	 * @since 1.0
	 */
	private function get_image_full_path( $filename = null ) {

		$directory = PP_BILDER_DIRECTORY;

		if ( substr( $directory, -1, 1 ) != '/' ) {
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
		return ABSPATH . PLUGINDIR . '/' . $this->plugin_name . '/thumbs/';
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
	 *
	 * @param string $filename  Original file name
	 *
	 * @return mixed
	 * @since 1.0
	 */
	private function get_thumb_filename( $filename ) {
		return preg_replace( '/(\.(jpg|gif|png))$/i', '-thumb$1', $filename );
	}

	/**
	 * Returns the full path (directory and filename) to where on disk the thumbnail can be found
	 *
	 * @param string $filename  Original file name
	 *
	 * @return string
	 * @since 1.0
	 */
	private function get_thumb_full_path( $filename ) {
		return $this->get_thumbs_dir() . $this->get_thumb_filename( $filename );
	}

	/**
	 * Returns the full URL to where the thumbnail can be loaded
	 *
	 * @param string $filename  Original file name
	 *
	 * @return string
	 * @since 1.0
	 */
	private function get_thumb_url( $filename ) {
		return $this->get_thumbs_url() . $this->get_thumb_filename( $filename );
	}

	/**
	 * Returns the URL for the thumb for the filename specified
	 *
	 * @param string $filename  Original file name
	 *
	 * @return stdClass
	 * @since 1.0
	 */
	private function get_thumb( $filename ) {

		$full_thumb    = $this->get_thumb_full_path( $filename );
		$full_filename = $this->get_image_full_path( $filename );

		if ( file_exists( $full_thumb ) ) {
			$file_mtime  = filemtime( $full_filename );
			$thumb_mtime = filemtime( $full_thumb );

			if ( $file_mtime > $thumb_mtime ) {
				$this->create_thumb( $full_filename, $full_thumb, $this->get_thumbs_dir() );
			}
		} else {
			$this->create_thumb( $full_filename, $full_thumb, $this->get_thumbs_dir() );
		}

		$size = getimagesize( $full_thumb );

		$thumb         = new stdClass;
		$thumb->url    = $this->get_thumb_url( $filename );
		$thumb->width  = $size[ 0 ];
		$thumb->height = $size[ 1 ];

		return $thumb;
	}

	/**
	 * Creates a thumbnail for the file
	 *
	 * @param string $src          Full path to the source image file
	 * @param string $dst_file     Full path to the destination image file
	 * @param string $dst_dir      Directory where the thumbnail should be saved
	 *
	 * @return string    Returns the filename
	 * @since 1.0
	 */
	private function create_thumb( $src, $dst_file, $dst_dir ) {

		$width  = get_option( 'thumbnail_size_w' );
		$height = get_option( 'thumbnail_size_h' );

		$resize = image_resize( $src, $width, $height, false, 'thumb', $dst_dir );

		if ( is_wp_error( $resize ) ) {
			copy( $src, $dst_file );

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

		if ( !is_readable( PP_BILDER_DIRECTORY ) ) {
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

		$posts_query = array(
			'post_type'   => 'attachment',
			'numberposts' => - 1,
		);

		$posts = get_posts( $posts_query );

		// Iterate the directory and get/create thumbnails
		while ( $file = readdir( $dir ) ) {
			if ( is_dir( $file ) || $file == '.' || $file == '..' ) {
				continue;
			}
			switch ( strtolower( substr( $file, -4, 4 ) ) ) {
				case '.jpg':
				case '.png':
				case '.gif':

					$image = new stdClass;

					$image->filename = $file;
					$image->thumb    = $this->get_thumb( $file );
					$image->post_id  = false;

					$file = substr( $file, 0, -4 );

					foreach ( $posts as $post ) {
						if ( $post->post_name == $file ) {
							$image->post_id = $post->ID;
							break;
						}
					}

					$this->images[ ] = $image;
					break;
			}
		}

		$this->images_loaded = count( $this->images ) > 0;

		return $this->images_loaded;
	}

	/**
	 * Hook to add our addition to the post thumbnail box when requested using AJAX
	 * @since 1.0
	 * @return void
	 */
	public function wp_ajax_set_post_thumbnail() {

		$this->load_images();

		if ( $this->images_loaded ) {
			add_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ) );
		}
	}

	/**
	 * Loads images and if available, adds filter to add custom markup to the post thumbnail meta box
	 * @return void
	 * @since 1.0
	 */
	public function admin_head() {

		$this->load_images();

		if ( $this->images_loaded ) {
			add_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ) );

			wp_enqueue_script( $this->plugin_name, plugins_url( $this->plugin_name . '/js/script.js' ), array( 'jquery' ), false, true );
			wp_enqueue_script( 'set-post-thumbnail' );

			$css = file_get_contents( plugin_dir_path( __FILE__ ) . 'css/style.css' );
			$css = preg_replace( "/(\n|\t|  )/", '', $css );
			$css = str_replace( ';}', '}', $css );
			$css = str_replace( ': ', ':', $css );

			printf( '<style type="text/css">%s</style>', $css );
		}
	}

	/**
	 * Initializes the client side part of the plugin with nonce
	 * @return mixed
	 */
	public function admin_footer() {

		global $post;

		if ( !$this->images_loaded ) {
			return;
		}

		?>
	<script>jQuery(document).ready(function () {
		PPBilder.init('<?php echo wp_create_nonce( 'set_post_thumbnail-' . $post->ID ) ?>');
	});</script><?php
	}

	/**
	 * Ajax handler function when selecting an image
	 */
	public function ajax_import_image() {

		if ( !isset( $_POST[ 'filename' ] ) || strlen( $_POST[ 'filename' ] ) == 0 ) {
			die();
		}

		$filename = $_POST[ 'filename' ];
		$image    = false;

		$this->load_images();

		foreach ( $this->images as $img ) {
			if ( $img->filename == $filename ) {
				$image = $img;
			}
		}

		if ( !$image ) {
			die();
		}

		require 'lib/class.add-from-server.php';

		$add_from_server = new add_from_server( '' );

		$result = $add_from_server->handle_import_file( $this->get_image_full_path( $image->filename ) );

		if ( is_wp_error( $result ) ) {
			die( '0' );
		}

		die( '' . $result );
	}

	/**
	 * Adds our addition to the featured image meta box
	 *
	 * @param string $content    The markup generated by WordPress
	 *
	 * @since 1.0
	 * @return string
	 */
	public function admin_post_thumbnail_html( $content ) {

		ob_start();

		?>
	<a title="Hämta bild från bildbanken" href="#TB_inline?height=auto&width=auto&inlineId=pp-bilder-container"
	   class="thickbox">Hämta bild från bildbanken</a>

	<div id="pp-bilder-container">
		<div id="pp-bilder">

			<h1>Bildbanken</h1>

			<p>Detta är gemensamma bilder som du kan välja ifrån när du ska publicera ett nytt inlägg. Klicka på bilden
				för att välja den som utvald bild för det här inlägget. De bilder du väljer importeras automatiskt till
				mediabiblioteket så om du har valt en bild en gång går den att välja genom det vanliga
				mediabiblioteket.</p>

			<?php foreach ( $this->images as $image ) : ?>

			<img src="<?php echo $image->thumb->url ?>"
				 width=<?php echo $image->thumb->width ?>
					 height=<?php echo $image->thumb->height ?>
				 alt=''

			<?php if ( $image->post_id ) : ?>
				data-post-id=<?php echo $image->post_id ?>
				<?php else : ?>
				data-filename="<?php echo $image->filename ?>"
				<?php endif ?>
			>

			<?php endforeach ?>
		</div>
	</div>
	<?php

		return $content . ob_get_clean();
	}
}

$pp_bilder = new PP_Bilder();