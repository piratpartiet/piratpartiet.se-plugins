<?php if ( ! defined( 'ABSPATH' ) ) die();
/*
Plugin Name: Piratpartiet startsida
Plugin URI: http://www.piratpartiet.se
Description: Hämta inlägg från underbloggar och visa på startsidan
Version: 1.1
Author: Rickard Andersson
Author URI: https://0x539.se

1.0: Initial release
1.1:
- Added support for multiple streams
- Added hooks to get the post thumbnail with regular WordPress functions
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

		// Multiple stream handling
		add_action( 'init', array( $this, 'init_taxonomies' ) );

		// Filters for loading the rss values instead
		add_filter( 'get_comments_number', array( $this, 'get_comments_number' ) );
		add_filter( 'the_permalink', array( $this, 'the_permalink' ) );
		add_filter( 'the_permalink_rss', array( $this, 'the_permalink' ) );
		add_filter( 'post_link', array( $this, 'the_permalink' ) );
		add_filter( 'get_the_guid', array( $this, 'the_permalink' ) );
		add_filter( 'the_author', array( $this, 'the_author' ) );
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
		add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail_html' ), 10, 2 );
		add_filter( 'get_post_metadata', array( $this, 'get_post_metadata'), 10, 3 );
		add_filter( 'the_excerpt', array( $this, 'the_excerpt' ) );
	}

	/**
	 * Initialize taxonomies
	 *
	 * @since 1.1
	 */
	public function init_taxonomies() {

		$labels = array(
			'name'              => _x( 'Stream', 'taxonomy general name' ),
			'singular_name'     => _x( 'Stream', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Streams' ),
			'all_items'         => __( 'All Streams' ),
			'parent_item'       => __( 'Parent Stream' ),
			'parent_item_colon' => __( 'Parent Stream:' ),
			'edit_item'         => __( 'Edit Stream' ),
			'update_item'       => __( 'Update Stream' ),
			'add_new_item'      => __( 'Add New Stream' ),
			'new_item_name'     => __( 'New Stream Name' ),
			'menu_name'         => __( 'Stream' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => 'stream',
			'rewrite'           => array( 'slug' => 'stream' ),
		);

		register_taxonomy( 'pp_stream', 'post', $args );
	}

	/**
	 * Render remote sidebar if requested
	 * @since 1.0
	 */
	public function remote_sidebar() {
		if ( isset( $_GET['pp_remote_sidebar'] ) ) {
			dynamic_sidebar( 'sidebar-master' );
			die();
		}
	}

	/**
	 * Initialize master sidebar
	 * @since 1.0
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
					$posts[intval( $item->meta_value )] = intval( $item->post_id );
					break;

				case 'pp-ettan-checksum':
					$checksums[intval( $item->post_id )] = intval( $item->meta_value );
					break;
			}
		}

		// Free up some memory
		unset( $meta );

		// Sort the sites array to begin with the site which has waited the longest to be updated
		usort( $sites, function ( $a, $b ) {
			return strtotime( $a->lastupdate ) - strtotime( $b->lastupdate );
		} );

		// Set feed cache time to one second to get fresh results
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'wp_feed_cache_transient_lifetime' ) );

		// Iterate all the sites
		foreach ( $sites as $key => $site ) {
			$sites[$key]->lastupdate = date( 'Y-m-d H:i:s' );

			$uri = '';

			// Create the URI for the feed
			$uri .= $site->url;
			$uri .= substr( $uri, - 1, 1 ) != '/' ? '/' : '';
			$uri .= '?feed=rss2';

			// Try to load the RSS feed
			$feed = fetch_feed( $uri );

			// Update the status to 'Error' if loading fails
			if ( is_wp_error( $feed ) ) {
				$sites[$key]->status    = 'Error';
				$sites[$key]->lastbuild = '';
				$sites[$key]->posts     = 0;
			}
			else {
				// Fetch the channel and items for easier access
				$channel = $feed->data['child']['']['rss'][0]['child']['']['channel'][0]['child'][''];
				$items   = $channel['item'];

				// If the feed is empty, fall back to an empty array to be type safe
				if ( ! $items ) {
					$items = array();
				}

				foreach ( $items as $item ) {
					// GUID and post_key/checksum
					$guid     = $item['child']['']['guid'][0]['data'];
					$post_key = crc32( $guid );
					$checksum = crc32( serialize( $item ) );
					$post_id  = false;

					if ( isset( $posts[$post_key] ) ) {
						$post_id = $posts[$post_key];
					}

					// If the checksum calculated from $item matches the stored value, no update is necessary
					if ( $checksums[$post_id] === $checksum ) {
						continue;
					}

					// Tags
					$_tags = $item['child']['']['category'];
					$tags  = array();

					if ( is_array( $_tags ) ) {
						foreach ( $_tags as $tag ) {

							if ( in_array( $tag['data'], $skip_tags ) ) {
								continue;
							}

							$tags[] = $tag['data'];
						}
					}

					$post_time = strtotime( $item['child']['']['pubDate'][0]['data'] );

					$post = array(
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
						'post_content'   => $item['child']['http://purl.org/rss/1.0/modules/content/']['encoded'][0]['data'],
						'post_date'      => date( 'Y-m-d H:i:s', $post_time ),
						'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $post_time ),
						'post_excerpt'   => strip_tags($item['child']['']['description'][0]['data']),
						'post_status'    => 'publish',
						'post_title'     => $item['child']['']['title'][0]['data'],
						'post_type'      => 'post',
						'tags_input'     => join( ',', $tags ),
						'tax_input'      => array( 'pp_stream' => array( $site->stream ) ),
					);

					if ( $post_id ) {
						$post['ID'] = $post_id;
					}

					$post_id = wp_insert_post( $post );

					update_post_meta( $post_id, 'comment_count', $item['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] );
					update_post_meta( $post_id, 'permalink', $item['child']['']['link'][0]['data'] );
					update_post_meta( $post_id, 'pp-ettan-post-key', $post_key );
					update_post_meta( $post_id, 'pp-ettan-checksum', $checksum );
					update_post_meta( $post_id, 'pp-ettan-site-name', $site->name );
					update_post_meta( $post_id, 'pp-ettan-site-url', $site->url );
				}

				// Update some status fields for the site
				$sites[$key]->status    = 'OK';
				$sites[$key]->posts     = count( $items );
				$sites[$key]->lastbuild = $channel['lastBuildDate'][0]['data'];
			}

			// Update the sites option after each iteration
			$sites[] = rand(); // Ever heard of a ugly hack?
			update_option( 'pp-ettan-sites', $sites );
			array_pop( $sites ); // http://core.trac.wordpress.org/ticket/22233
			update_option( 'pp-ettan-sites', $sites );
		}

		// Remove the filter again
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'wp_feed_cache_transient_lifetime' ) );
	}

	/**
	 * @return array
	 * @since 1.1
	 */
	protected function get_streams() {
		$terms = get_terms( 'pp_stream', array( 'hide_empty' => false ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Will set $wp_query to a query for the stream with the given name
	 *
	 * @param string $name Term slug
	 * @param int $max Max number of posts
	 *
	 * @since 1.1
	 */
	public function query_stream($name, $max) {

		global $wp_query;

		$args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'pp_stream',
					'field' => 'slug',
					'terms' => $name,
				),
			),
			'posts_per_page' => $max,
		);

		$wp_query = new WP_query($args);

	}

	/**
	 * @return array
	 * @since 1.1
	 */
	protected function get_sites() {
		$sites = get_option( 'pp-ettan-sites' );

		return $sites ? $sites : array();
	}

	/**
	 * Handler function for the options page, handles actions from the form and renders the result
	 * @since 1.0
	 * @return void
	 */
	function options_page_ettan() {

		// Fetch current sites from options
		$sites    = $this->get_sites();
		$errors   = array();
		$messages = array();

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'pp-ettan-sites' ) ) {

			$post_sites = isset( $_POST['sites'] ) ? $_POST['sites'] : array();

			foreach ( $post_sites as $key => $values ) {
				$sites[$key]->stream = $values['stream'];
			}

			$remove = isset( $_POST['remove'] ) ? $_POST['remove'] : array();

			foreach ( $remove as $remove_key ) {
				$messages[] = $sites[$remove_key]->name . " borttagen";
				unset( $sites[$remove_key] );
			}

			update_option( 'pp-ettan-sites', $sites );
			$sites = get_option( 'pp-ettan-sites' );

			$messages[] = 'Sparade inställningar';
		}

		// If any of the buttons in the 'other' section was used
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'pp-ettan-other' ) ) {
			// The submit value holds the action
			switch ( $_POST['submit'] ) {
				case 'Hämta inlägg':
					$this->load_posts();
					$sites      = get_option( 'pp-ettan-sites' );
					$messages[] = 'Hämtning från underbloggar genomförd';
					break;
				case 'Uppdatera flöde':
					$this->reset_stream();
					$messages[] = 'Uppdatering av flöde på inlägg genomförd';
					break;
			}
		}

		// If the add site form was submitted
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'pp-ettan-add-site' ) ) {
			$uri = '';

			// Construct the feed URL
			$uri .= $_POST['url'];
			$uri .= substr( $uri, - 1, 1 ) != '/' ? '/' : '';
			$uri .= '?feed=rss2';

			// Validate the resulting URL
			if ( ! filter_var( $uri, FILTER_VALIDATE_URL ) ) {
				$errors[] = 'Ogiltig adress "' . filter_var( $_POST['url'], FILTER_SANITIZE_URL ) . '"';
			}
			else {
				// Fetch the feed
				$feed = fetch_feed( $uri );

				if ( is_wp_error( $feed ) ) {
					$errors[] = 'Ett fel uppstod när innehåll skulle hämtas: "' . $feed->get_error_message() . '"';
				}
				else {
					// Get the generator attribute from the feed
					$generator = $feed->data['child']['']['rss'][0]['child']['']['channel'][0]['child']['']['generator'][0]['data'];

					// ..and chech that it's WordPress
					if ( substr( $generator, 0, 24 ) != 'http://wordpress.org/?v=' ) {
						$errors[] = 'Adressen verkar inte peka mot en WordPress blogg, generator rapporterar "' . $generator . '"';
					}
				}
			}

			// If everything went fine, create a new object and add it to current sites
			if ( count( $errors ) == 0 ) {
				$site             = new stdClass;
				$site->url        = $_POST['url'];
				$site->name       = $_POST['name'];
				$site->error      = false;
				$site->lastupdate = false;
				$site->lastbuild  = false;
				$site->status     = 'Unknown';
				$site->posts      = 0;
				$site->stream     = $_POST['stream'];

				$sites[] = $site;

				update_option( 'pp-ettan-sites', $sites );

				$messages[] = 'Sajt tillagd.';
			}
		}

		require 'pages/options-page-ettan.php';
	}

	/**
	 * Iterate all the posts and re-set the stream
	 *
	 * @since 1.1
	 */
	protected function reset_stream() {

		$sites = $this->get_sites();

		$sites_by_url = array();

		foreach ( $sites as $site ) {
			$sites_by_url[$site->url] = $site;
		}

		$posts = get_posts( array(
			'numberposts' => -1,
		) );

		foreach ( $posts as $post ) {
			if ( ! $this->is_rss_post( $post->ID ) ) {
				continue;
			}

			$site_url = get_post_meta( $post->ID, 'pp-ettan-site-url', true );

			$site = isset( $sites_by_url[$site_url] ) ? $sites_by_url[$site_url] : '';

			if ( ! $site ) {
				continue;
			}

			wp_set_post_terms( $post->ID, $site->stream, 'pp_stream' );
		}
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
		if ( is_feed() ) {
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
	 * @since 1.0
	 */
	function post_class( $classes, $class, $post_id ) {

		$ettan_post = ! ! get_post_meta( $post_id, 'pp-ettan-post-key', true );

		if ( $ettan_post ) {
			$classes[] = 'ettan';
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
	 * Attached to the filter 'post_thumbnail_html' to return the markup from the thumbnail included inside the contents of the RSS post
	 *
	 * @param $html
	 * @param $post_id
	 *
	 * @return string
	 * @since 1.1
	 */
	function post_thumbnail_html($html, $post_id) {

		if (!$html && $this->get_post_thumbnail_html($post_id)) {
			return $this->get_post_thumbnail_html($post_id);
		}

		return $html;
	}

	/**
	 * Attached to the filter 'get_post_metadata' to return true if the RSS post contains a thumbnail
	 *
	 * @param mixed $null Seems unused by WP?
	 * @param int $object_id  Post ID
	 * @param string $meta_key   Name of the meta key
	 *
	 * @return int|null
	 * @since 1.1
	 */
	function get_post_metadata($null, $object_id, $meta_key) {
		if ($meta_key !== '_thumbnail_id') {
			return null;
		}

		if (!$this->is_rss_post($object_id)) {
			return null;
		}

		if (!$this->get_post_thumbnail_html($object_id)) {
			return null;
		}

		// Just return something and let post_thumbnail_hook figure out the correct HTML
		return 1;
	}

	/**
	 * Will load the post and try to find the post thumbnail from the RSS post if available
	 *
	 * @param $object_id
	 *
	 * @return bool|string
	 * @since 1.1
	 */
	protected function get_post_thumbnail_html($object_id) {

		if (!$this->is_rss_post($object_id)) {
			return false;
		}

		$post = get_post($object_id);

		if (substr($post->post_content, 0, strlen('<figure class="alignleft">')) !== '<figure class="alignleft">') {
			return false;
		}

		$img_start = strpos($post->post_content, '<img');

		if (!$img_start) {
			return false;
		}

		$img_end = strpos($post->post_content, '>', $img_start);

		if (!$img_end) {
			return false;
		}

		return substr($post->post_content, $img_start, $img_end - $img_start + 1);
	}

	/**
	 * Attached to the filter 'the_excerpt' and strips HTML since old (pre 1.1) posts could contain excerpts with HTML
	 *
	 * Previous to 1.1 the thumbnail would get outputted within the excerpt. As of 1.1 this is handled by hooking
	 * into the post thumbnail functions in WordPress.
	 *
	 * @param string $excerpt
	 *
	 * @return string
	 * @since 1.1
	 */
	public function the_excerpt($excerpt) {
		return strip_tags($excerpt);
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
