<?php if ( ! defined( 'ABSPATH' ) ) die();
/*
Plugin Name: Piratpartiet startsida
Plugin URI: http://www.piratpartiet.se
Description: Hämta inlägg från underbloggar och visa på startsidan
Version: 1.0
Author: Rickard Andersson
Author URI: https://0x539.se
*/

/**
 * Render RSS fetched posts, and more.
 * @author Rickard Andersson
 * @since  1.0
 */
class PP_ettan {

	/**
	 * Plugin name
	 * @var string
	 * @since 1.0
	 */
	private $plugin_name = 'pp-ettan';

	/**
	 * The constructor is executed when the class is instantiated and the plugin gets loaded
	 * @since 1.0
	 */
	function __construct() {

		// Every update calls wp_insert_post() which updates the post to a new revision. Disable revisions. Now.
		if ( WP_POST_REVISIONS ) {
			die( "Running PP-ettan with <code>WP_POST_REVISIONS</code> set to <code>true</code> is not something you generally want to do, unless you'd like to clean up the post revisions manually." );
		}

		// Add the options menu
		add_action( 'admin_menu', array( $this, 'init_admin_menu' ) );

		// Load posts periodically
		add_action( 'pp_ettan_load_posts', array( $this, 'load_posts' ) );

		// Redirect the user to the remote page for posts
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );

		// Sidebar handling
		add_action( 'init', array( $this, 'init_sidebars' ) );
		add_action( 'init', array( $this, 'remote_sidebar' ) );

		// Filters for loading the rss values instead
		add_filter( 'get_comments_number', array( $this, 'get_comments_number' ) );
		add_filter( 'the_permalink', array( $this, 'the_permalink' ) );
		add_filter( 'the_permalink_rss', array( $this, 'the_permalink' ) );
		add_filter( 'post_link', array( $this, 'the_permalink' ) );
		add_filter( 'get_the_guid', array( $this, 'the_permalink' ) );
		add_filter( 'the_author', array( $this, 'the_author' ) );
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
	}

	/**
	 * Render remote sidebar if requested
	 */
	public function remote_sidebar() {
		if ( isset( $_GET[ 'pp_remote_sidebar' ] ) ) {
			dynamic_sidebar( 'sidebar-master' );
			die();
		}
	}

	/**
	 * Initialize master sidebar
	 */
	public function init_sidebars() {

		$args = array(
			'name'          => 'Gemensamt widget-utrymme',
			'id'            => 'sidebar-master',
			'description'   => 'Visas överst i sidospalten, full bredd och visas på alla underbloggar',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
			'before_title'  => '<h1>',
			'after_title'   => '</h1>'
		);

		register_sidebar( $args );
	}

	/**
	 * Will be attached to the wp_feed_cache_transient_lifetime filter when refreshing posts
	 *
	 * @param $seconds
	 *
	 * @return int
	 * @since 1.0
	 */
	function wp_feed_cache_transient_lifetime( $seconds ) {
		return ( $seconds * 0 ) + 1;
	}

	/**
	 * Load posts from the RSS sites
	 * @since 1.0
	 * @return void
	 */
	function load_posts() {

		/** @var WPDB $wpdb */
		global $wpdb;

		// Tag names which are skipped when reading incoming posts
		$skip_tags = array(
			'Okategoriserade',
			'Okategoriserat',
			'Uncategorized',
			'Okategoriserad'
		);

		$sites = get_option( 'pp-ettan-sites' );

		// Load post keys from the postmeta table
		$meta      = $wpdb->get_results( 'SELECT post_id, meta_value, meta_key FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key IN ("pp-ettan-post-key", "pp-ettan-checksum")' );
		$posts     = array();
		$checksums = array();

		// Transform the result into a lookup list
		foreach ( $meta as $item ) {
			switch ( $item->meta_key ) {
				case 'pp-ettan-post-key':
					$posts[ intval( $item->meta_value ) ] = intval( $item->post_id );
					break;

				case 'pp-ettan-checksum':
					$checksums[ intval( $item->post_id ) ] = intval( $item->meta_value );
					break;
			}
		}

		// Free up some memory
		unset( $meta );

		// Sort the sites array to begin with the site which has waited the longest to be updated
		usort( $sites, function ( $a, $b ) { return strtotime( $a->lastupdate ) - strtotime( $b->lastupdate ); } );

		// Set feed cache time to one second to get fresh results
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'wp_feed_cache_transient_lifetime' ) );

		// Iterate all the sites
		foreach ( $sites as $key => $site ) {
			$sites[ $key ]->lastupdate = date( 'Y-m-d H:i:s' );

			$uri = '';

			// Create the URI for the feed
			$uri .= $site->url;
			$uri .= substr( $uri, - 1, 1 ) != '/' ? '/' : '';
			$uri .= '?feed=rss2';

			// Try to load the RSS feed
			$feed = fetch_feed( $uri );

			// Update the status to 'Error' if loading fails
			if ( is_wp_error( $feed ) ) {
				$sites[ $key ]->status    = 'Error';
				$sites[ $key ]->lastbuild = '';
				$sites[ $key ]->posts     = 0;
			} else {
				// Fetch the channel and items for easier access
				$channel = $feed->data[ 'child' ][ '' ][ 'rss' ][ 0 ][ 'child' ][ '' ][ 'channel' ][ 0 ][ 'child' ][ '' ];
				$items   = $channel[ 'item' ];

				// If the feed is empty, fall back to an empty array to be type safe
				if ( ! $items ) {
					$items = array();
				}

				foreach ( $items as $item ) {
					// GUID and post_key/checksum
					$guid     = $item[ 'child' ][ '' ][ 'guid' ][ 0 ][ 'data' ];
					$post_key = crc32( $guid );
					$checksum = crc32( serialize( $item ) );
					$post_id  = false;

					if ( isset( $posts[ $post_key ] ) ) {
						$post_id = $posts[ $post_key ];
					}

					// If the checksum calculated from $item matches the stored value, no update is necessary
					if ( $checksums[ $post_id ] === $checksum ) {
						continue;
					}

					// Tags
					$_tags = $item[ 'child' ][ '' ][ 'category' ];
					$tags  = array();

					if ( is_array( $_tags ) ) {
						foreach ( $_tags as $tag ) {

							if ( in_array( $tag[ 'data' ], $skip_tags )
							) {
								continue;
							}

							$tags[ ] = $tag[ 'data' ];
						}
					}

					$post_time = strtotime( $item[ 'child' ][ '' ][ 'pubDate' ][ 0 ][ 'data' ] );

					$post = array(
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
						'post_content'   => $item[ 'child' ][ 'http://purl.org/rss/1.0/modules/content/' ][ 'encoded' ][ 0 ][ 'data' ],
						'post_date'      => date( 'Y-m-d H:i:s', $post_time ),
						'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $post_time ),
						'post_excerpt'   => $item[ 'child' ][ '' ][ 'description' ][ 0 ][ 'data' ],
						'post_status'    => 'publish',
						'post_title'     => $item[ 'child' ][ '' ][ 'title' ][ 0 ][ 'data' ],
						'post_type'      => 'post',
						'tags_input'     => join( ',', $tags ),
					);

					if ( $post_id ) {
						$post[ 'ID' ] = $post_id;
					}

					$post_id = wp_insert_post( $post );

					update_post_meta( $post_id, 'comment_count', $item[ 'child' ][ 'http://purl.org/rss/1.0/modules/slash/' ][ 'comments' ][ 0 ][ 'data' ] );
					update_post_meta( $post_id, 'permalink', $item[ 'child' ][ '' ][ 'link' ][ 0 ][ 'data' ] );
					update_post_meta( $post_id, 'pp-ettan-post-key', $post_key );
					update_post_meta( $post_id, 'pp-ettan-checksum', $checksum );
					update_post_meta( $post_id, 'pp-ettan-site-name', $site->name );
					update_post_meta( $post_id, 'pp-ettan-site-url', $site->url );
				}

				// Update some status fields for the site
				$sites[ $key ]->status    = 'OK';
				$sites[ $key ]->posts     = count( $items );
				$sites[ $key ]->lastbuild = $channel[ 'lastBuildDate' ][ 0 ][ 'data' ];
			}

			// Update the sites option after each iteration
			$sites[ ] = rand(); // Ever heard of a ugly hack?
			update_option( 'pp-ettan-sites', $sites );
			array_pop( $sites ); // http://core.trac.wordpress.org/ticket/22233
			update_option( 'pp-ettan-sites', $sites );
		}

		// Remove the filter again
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'wp_feed_cache_transient_lifetime' ) );
	}

	/**
	 * Handler function for the options page, handles actions from the form and renders the result
	 * @since 1.0
	 * @return void
	 */
	function options_page_ettan() {

		// Fetch current sites from options
		$sites    = get_option( 'pp-ettan-sites' );
		$errors   = array();
		$messages = array();

		if ( ! $sites ) {
			$sites = array();
		}

		// If the 'delete' button was used
		if ( isset( $_POST[ '_wpnonce' ] ) && isset( $_POST[ 'key' ] ) && is_numeric( $_POST[ 'key' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'pp-ettan-rm-site-' . $_POST[ 'key' ] ) ) {
			// Unset the site and update the option
			unset( $sites[ $_POST[ 'key' ] ] );
			update_option( 'pp-ettan-sites', $sites );

			// Load posts from rss again and re-set the $sites array
			$this->load_posts();
			$sites = get_option( 'pp-ettan-sites' );

			$messages[ ] = 'Sajt borttagen, hämtade även manuellt från underbloggar';
		}

		// If any of the buttons in the 'other' section was used
		if ( isset( $_POST[ '_wpnonce' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'pp-ettan-other' ) ) {
			// The submit value holds the action
			switch ( $_POST[ 'submit' ] ) {
				case 'Hämta inlägg':
					$this->load_posts();
					$sites       = get_option( 'pp-ettan-sites' );
					$messages[ ] = 'Hämtning från underbloggar genomförd';
					break;
			}
		}

		// If the add site form was submitted
		if ( isset( $_POST[ '_wpnonce' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'pp-ettan-add-site' ) ) {
			$uri = '';

			// Construct the feed URL
			$uri .= $_POST[ 'url' ];
			$uri .= substr( $uri, - 1, 1 ) != '/' ? '/' : '';
			$uri .= '?feed=rss2';

			// Validate the resulting URL
			if ( ! filter_var( $uri, FILTER_VALIDATE_URL ) ) {
				$errors[ ] = 'Ogiltig adress "' . filter_var( $_POST[ 'url' ], FILTER_SANITIZE_URL ) . '"';
			} else {
				// Fetch the feed
				$feed = fetch_feed( $uri );

				if ( is_wp_error( $feed ) ) {
					$errors[ ] = 'Ett fel uppstod när innehåll skulle hämtas: "' . $feed->get_error_message() . '"';
				} else {
					// Get the generator attribute from the feed
					$generator = $feed->data[ 'child' ][ '' ][ 'rss' ][ 0 ][ 'child' ][ '' ][ 'channel' ][ 0 ][ 'child' ][ '' ][ 'generator' ][ 0 ][ 'data' ];

					// ..and chech that it's WordPress
					if ( substr( $generator, 0, 24 ) != 'http://wordpress.org/?v=' ) {
						$errors[ ] = 'Adressen verkar inte peka mot en WordPress blogg, generator rapporterar "' . $generator . '"';
					}
				}
			}

			// If everything went fine, create a new object and add it to current sites
			if ( count( $errors ) == 0 ) {
				$site             = new stdClass;
				$site->url        = $_POST[ 'url' ];
				$site->name       = $_POST[ 'name' ];
				$site->error      = false;
				$site->lastupdate = false;
				$site->lastbuild  = false;
				$site->status     = 'Unknown';
				$site->posts      = 0;

				$sites[ ] = $site;

				update_option( 'pp-ettan-sites', $sites );

				$messages[ ] = 'Sajt tillagd.';
			}
		}

		require 'pages/options-page-ettan.php';
	}

	/**
	 * Fetch the site object for a post
	 *
	 * @param $post_id
	 *
	 * @return object|bool
	 * @since 1.0
	 */
	function get_site( $post_id ) {

		// If the post is a local post just return the blog url and name
		if ( ! self::is_rss_post( $post_id ) ) {
			$site = new stdClass;

			$site->url  = get_bloginfo( 'url' );
			$site->name = get_bloginfo( 'name' );

			return $site;
		}

		/** @var $site string */
		list( $site, $post ) = explode( ':', $post_id );
		$sites = get_option( 'pp-ettan-sites' );

		unset( $post ); // unused, supresses editor warning

		return isset( $sites[ $site ] ) ? $sites[ $site ] : false;
	}

	/**
	 * Add the options page
	 * @since 1.0
	 * @return void
	 */
	function init_admin_menu() {
		$callback = array( $this, 'options_page_ettan' );

		add_options_page( 'Ettan', 'Ettan', 'manage_options', $this->plugin_name, $callback );
	}

	/**
	 * Attached to the filter 'get_comments_number' and returns the number of comments from RSS
	 *
	 * @param int $comment_count
	 *
	 * @return int
	 * @since 1.0
	 */
	function get_comments_number( $comment_count ) {
		global $post;

		if ( $post->post_type == 'post' ) {
			$comment_count = get_post_meta( $post->ID, 'comment_count', true );
		}

		return $comment_count ? $comment_count : 0;
	}

	/**
	 * Attached to the filter 'the_permalink' and returns the permalink from the remote post
	 *
	 * @param $permalink
	 *
	 * @return mixed
	 * @since 1.0
	 */
	function the_permalink( $permalink ) {
		global $post;

		if ( ! $post ) {
			return $permalink;
		}

		if ( $post->post_type != 'post' ) {
			return $permalink;
		}

		return get_post_meta( $post->ID, 'permalink', true );
	}

	/**
	 * Attached to the filter 'the_author' and returns an empty string
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	function the_author() {
		if (is_feed()) {
			global $post;

			return get_post_meta( $post->ID, 'pp-ettan-site-name', true );
		}

		return '';
	}

	/**
	 * Attached to the filter 'post_class' and adds a custom class to posts fetched from RSS
	 *
	 * @param array   $classes
	 * @param string  $class
	 * @param integer $post_id
	 *
	 * @return array
	 */
	function post_class( $classes, $class, $post_id ) {

		$ettan_post = ! ! get_post_meta( $post_id, 'pp-ettan-post-key', true );

		if ( $ettan_post ) {
			$classes[ ] = 'ettan';
		}

		return $classes;
	}

	/**
	 * Attached to the action 'template_redirect' and redirects the user to the original site when visiting posts
	 *
	 * @since 1.0
	 */
	function template_redirect() {
		if ( ! is_single() ) {
			return;
		}

		global $post;

		$permalink = get_post_meta( $post->ID, 'permalink', true );

		if ( $permalink ) {
			wp_redirect( $permalink, 301 );
		}
	}

	/**
	 * Checks if a post is a rss post
	 *
	 * @static
	 *
	 * @param $post_id
	 *
	 * @return bool
	 *
	 * @since 1.0
	 */
	static function is_rss_post( $post_id ) {

		$permalink = get_post_meta( $post_id, 'permalink', true );

		return ! ! $permalink;
	}

	/**
	 * Activation function
	 * @static
	 * @since 1.0
	 */
	static function install() {
		wp_schedule_event( time(), 'hourly', 'pp_ettan_load_posts' );
		wp_schedule_event( time() + 15 * 60, 'hourly', 'pp_ettan_load_posts' );
		wp_schedule_event( time() + 30 * 60, 'hourly', 'pp_ettan_load_posts' );
		wp_schedule_event( time() + 45 * 60, 'hourly', 'pp_ettan_load_posts' );
	}

	/**
	 * Deactivation function
	 * @static
	 * @since 1.0
	 */
	static function uninstall() {
		wp_clear_scheduled_hook( 'pp_ettan_load_posts' );
	}
}

register_activation_hook( WP_CONTENT_DIR . '/plugins/pp-ettan/pp-ettan.php', array( 'PP_Ettan', 'install' ) );
register_deactivation_hook( WP_CONTENT_DIR . '/plugins/pp-ettan/pp-ettan.php', array( 'PP_Ettan', 'uninstall' ) );

$ettan = new PP_ettan();
