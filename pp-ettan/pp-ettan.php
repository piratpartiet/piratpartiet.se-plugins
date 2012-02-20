<?php
/*
Plugin Name: Piratpartiet startsida
Plugin URI: http://www.piratpartiet.se
Description: Hämta inlägg från underbloggar och visa på startsidan
Version: 1.0
Author: Rickard Andersson
Author URI: https://0x539.se
*/

/**
 * This is where you add comments for your plugin class
 * @author Rickard Andersson
 */
class PP_ettan {

	private $plugin_name = "pp-ettan";

	/**
	 * The constructor is executed when the class is instatiated and the plugin gets loaded
	 * @since 1.0
	 */
	function __construct() {
		// Add the options menu
		add_action('admin_menu', array($this,'init_admin_menu'));

		// Load posts periodically
		add_action('pp_ettan_load_posts', array($this, 'load_posts'));

		// Filters for loading the rss content instead
		add_filter('get_comments_number', array($this, 'get_comments_number'));
		add_filter('the_tags', array($this, 'the_tags'), 10, 4);
	}

	/**
	 * Load posts from the RSS sites
	 * @since 1.0
	 * @return void
	 */
	function load_posts() {

		$sites = get_option('pp-ettan-sites');
		$posts = array();

		// Iterate all the sites
		foreach ( $sites as $key => $site ) {

			$sites[ $key ]->lastupdate = date("Y-m-d H:i:s");

			// Create the URI for the feed
			$uri = $site->url;
			$uri .= substr($uri, -1, 1) != '/' ? '/' : '';
			$uri .= '?feed=rss2';

			// Try to load the RSS feed
			$feed = fetch_feed($uri);

			// Update the status to "Error" if loading fails
			if ( is_wp_error($feed) ) {
				$sites[ $key ]->status    = "Error";
				$sites[ $key ]->lastbuild = "";
				$sites[ $key ]->posts     = 0;
 			} else {
				// Fetch the channel and items for easier access
				$channel = $feed->data['child']['']['rss'][0]['child']['']['channel'][0]['child'][''];
				$items   = $channel['item'];

				foreach ( $items as $item ) {
					$post = new stdClass;

					// GUID + ID
					$guid = $item['child']['']['guid'][0]['data'];
					preg_match("/\?p=([0-9]+)/", $guid, $matches);
					$ID = $matches[1];

					// Tags
					$tags = $item['child']['']['category'];
					$post->tags = array();

					foreach ( $tags as $tag ) {
						$post->tags[] = $tag['data'];
					}

					// General simple attributes
					$post->ID            = "$key:$ID";
					$post->title         = $item['child']['']['title'][0]['data'];
					$post->permalink     = $item['child']['']['link'][0]['data'];
					$post->excerpt       = $item['child']['']['description'][0]['data'];
					$post->post_date     = $item['child']['']['pubDate'][0]['data'];
					$post->comment_count = $item['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'];
					$post->class         = 'class="post-'. $post->ID .' post type-post status-publish format-standard hentry"';

					$posts[] = $post;
				}

				// Update some status fields for the site
				$sites[ $key ]->status    = "OK";
				$sites[ $key ]->posts     = count($items);
				$sites[ $key ]->lastbuild = $channel['lastBuildDate'][0]['data'];
			}
		}

		// Resort the posts array to show the latest posts at the top
		usort($posts, function($a, $b) {
			$time_a = strtotime($a->post_date);
			$time_b = strtotime($b->post_date);

			if ( $time_a == $time_b )
				return 0;

			// Backwards, sort descending
			return ( $time_a > $time_b ) ? -1 : 1;
		});

		update_option('pp-ettan-posts', $posts);
		update_option('pp-ettan-sites', $sites);
	}

	/**
	 * Get current posts from cache
	 * @since 1.0
	 * @return mixed|void
	 */
	function get_posts() {
		$posts = get_option('pp-ettan-posts');
		return $posts ? $posts : array();
	}

	/**
	 * Handler function for the options page, handles actions from the form and renders the result
	 * @since 1.0
	 * @return void
	 */
	function options_page_ettan() {

		// Fetch current sites from options
		$sites    = get_option('pp-ettan-sites');
		$errors   = array();
		$messages = array();

		if ( !$sites ) {
			$sites = array();
		}

		// If the 'delete' button was used
		if ( isset($_POST['key']) && is_numeric($_POST['key']) && wp_verify_nonce($_POST['_wpnonce'], 'pp-ettan-rm-site-' . $_POST['key']) ) {

			// Unset the site and update the option
			unset($sites[ $_POST['key'] ]);
			update_option('pp-ettan-sites', $sites);

			// Load posts from rss again and re-set the $sites array
			$this->load_posts();
			$sites = get_option('pp-ettan-sites');

			$messages[] = "Sajt borttagen, hämtade även manuellt från underbloggar";
		}

		// If any of the buttons in the "other" section was used
		if ( wp_verify_nonce($_POST['_wpnonce'], 'pp-ettan-other') ) {

			// The submit value holds the action
			switch ( $_POST['submit'] ) {
				case 'Hämta inlägg':
					$this->load_posts();
					$sites = get_option('pp-ettan-sites');
					$messages[] = "Hämtning från underbloggar genomförd";
					break;
			}
		}

		// If the add site form was submitted
		if ( wp_verify_nonce($_POST['_wpnonce'], 'pp-ettan-add-site') ) {

			// Construct the feed URL
			$uri = $_POST['url'];
			$uri .= substr($uri, -1, 1) != '/' ? '/' : '';
			$uri .= "?feed=rss2";

			// Validate the resulting URL
			if ( !filter_var( $uri, FILTER_VALIDATE_URL ) ) {
				$errors[] = "Ogiltig adress '" . filter_var( $_POST['url'], FILTER_SANITIZE_URL ) . "'";
			}

			else {

				// Fetch the feed
				$feed = fetch_feed( $uri );

				if ( is_wp_error($feed) ) {
					$errors[] = "Ett fel uppstod när innehåll skulle hämtas: '" . $feed->get_error_message() . "'";
				} else {

					// Get the generator attribute from the feed
					$generator = $feed->data['child']['']['rss'][0]['child']['']['channel'][0]['child']['']['generator'][0]['data'];

					// ..and chech that it's WordPress
					if ( substr($generator, 0, 24) != "http://wordpress.org/?v=" ) {
						$errors[] = "Adressen verkar inte peka mot en WordPress blogg, generator rapporterar '" . $generator . "'";
					}
				}
			}

			// If everything went fine, create a new object and add it to current sites
			if ( count($errors) == 0) {
				$site = new stdClass;
				$site->url        = $_POST['url'];
				$site->name       = $_POST['name'];
				$site->error      = false;
				$site->lastupdate = false;
				$site->lastbuild  = false;
				$site->status     = 'Unknown';
				$site->posts      = 0;

				$sites[] = $site;

				update_option('pp-ettan-sites', $sites);

				$messages[] = "Sajt tillagd.";
			}
		}

		require "pages/options_page_ettan.php";
	}

	/**
	 * Fetch the site object for a post
	 * @param $post_id
	 * @return object|bool
	 * @since 1.0
	 */
	function get_site($post_id) {

		list($site, $post) = explode(":", $post_id);
		$sites = get_option('pp-ettan-sites');

		return isset($sites[ $site ]) ? $sites[ $site ] : false;
	}


	/**
	 * Add the options page
	 * @since 1.0
	 * @return void
	 */
	function init_admin_menu() {
		add_options_page('Ettan', 'Ettan', 'manage_options', $this->plugin_name, array($this, 'options_page_ettan'));
	}

	/**
	 * Attached to the filter 'get_comments_number' and returns the number of comments from RSS
	 * @return int
	 * @since 1.0
	 */
	function get_comments_number() {
		global $post;
		return isset($post->comment_count) ? $post->comment_count : 0;
	}

	/**
	 * Attached to the filter 'the_tags' and returns the tags from RSS
	 * @param $terms
	 * @param $before
	 * @param $sep
	 * @param $after
	 * @return string
	 * @since 1.0
	 */
	function the_tags($terms, $before, $sep, $after) {
		unset($terms); // Removes variable unused warning
		global $post;
		return $before . implode($sep, $post->tags) . $after;
	}

	/**
	 * Activation function
	 * @static
	 * @since 1.0
	 */
	static function install() {
		wp_schedule_event(time(), 'hourly', 'pp_ettan_load_posts');
		wp_schedule_event(time() + 15*60, 'hourly', 'pp_ettan_load_posts');
		wp_schedule_event(time() + 30*60, 'hourly', 'pp_ettan_load_posts');
		wp_schedule_event(time() + 45*60, 'hourly', 'pp_ettan_load_posts');
	}

	/**
	 * Deactivation function
	 * @static
	 * @since 1.0
	 */
	static function uninstall() {
		wp_clear_scheduled_hook('pp_ettan_load_posts');
	}
}

register_activation_hook(__FILE__, array('PP_Ettan', 'install'));
register_deactivation_hook(__FILE__, array('PP_Ettan', 'uninstall'));

$ettan = new PP_ettan();