<?php if ( !defined("ABSPATH") ) die();
/**
 * Namespaced functions for rendering the rss, atom and rdf feeds. This method is used so that the templates are easy
 * to update whenever WordPress updates their own templates.
 *
 * @author Rickard Andersson <rickard@0x539.se>
 * @since 1.0
 */

// Declaring the namespace 'piratpartiet' will override the native WordPress functions with our own
namespace piratpartiet;

/**
 * Helper class to remember state in the iteration. This class fetches all the information needed to render the rss,
 * atom and rdf feeds. It could also be used in the main loop with some additions and refactoring.
 *
 * @author Rickard Andersson <rickard@0x539.se>
 * @since 1.0
 */
class RSS_Posts {

	/**
	 * An array holding all the post objects
	 * @var array
	 * @since 1.0
	 */
	private $posts;

	/**
	 * How many posts is in the $posts array
	 * @var int
	 * @since 1.0
	 */
	private $post_count;

	/**
	 * The current post being read
	 * @var object
	 * @since 1.0
	 */
	private $current_post;

	/**
	 * Internal post iterator counter. Initially set to -1 so that the first call to the_post()
	 * will increment the counter to 0 and return the first post.
	 * @var int
	 * @since 1.0
	 */
	private $counter = -1;

	/**
	 * Singleton instance
	 * @var RSS_Posts
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * Get the current singleton instance
	 * @static
	 * @return RSS_Posts
	 * @since 1.0
	 */
	static function getInstance() {

		if ( !isset(self::$instance) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Initialize the class, loads the posts from cache.
	 * @since 1.0
	 */
	private function __construct() {

		global $ettan;

		$this->posts      = $ettan->get_posts();
		$this->post_count = count($this->posts);
	}

	/**
	 * Returns true if there is more posts to be read
	 * @return bool
	 * @since 1.0
	 */
	function have_posts() {

		if ( $this->counter + 1 == $this->post_count ) {
			$this->counter = -1;
			return false;
		} else if ( $this->post_count == 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Move to the next post
	 * @since 1.0
	 */
	function the_post() {
		$this->current_post = $this->posts[ ++$this->counter ];
	}

	/**
	 * Returns the post title
	 * @return string
	 * @since 1.0
	 */
	function get_the_title_rss() {
		return str_replace('&', '&amp;', $this->current_post->title );
	}

	/**
	 * Returns the post permalink
	 * @return string
	 * @since 1.0
	 */
	function get_the_permalink_rss() {
		return $this->current_post->permalink;
	}

	/**
	 * Returns the post date in $format
	 * @param $format
	 * @return string
	 * @since 1.0
	 */
	function get_post_time($format) {
		return date( $format, strtotime($this->current_post->post_date) );
	}

	/**
	 * We're using rss2 as feed type when reading the posts so the modified date isn't available. Just use the
	 * post date will be sufficient.
	 * @param string $format
	 * @return string
	 * @since 1.0
	 */
	function get_post_modified_time($format) {
		return $this->get_post_time($format);
	}

	/**
	 * Returns the excerpt
	 * @return string
	 * @since 1.0
	 */
	function get_the_excerpt_rss() {
		return $this->current_post->excerpt;
	}

	/**
	 * Returns the number of comments
	 * @return int
	 * @since 1.0
	 */
	function get_comments_number() {
		return $this->current_post->comment_count;
	}

	/**
	 * Returns the url to the comments
	 * @return string
	 * @since 1.0
	 */
	function comments_link_feed() {
		return $this->current_post->comment_url;
	}

	/**
	 * Returns the author of the post
	 * @return string
	 * @since 1.0
	 */
	function get_the_author() {
		return $this->current_post->author;
	}

	/**
	 * Returns the tags and categories for this post
	 * @param string $type
	 * @return string
	 * @since 1.0
	 */
	function get_the_category_rss($type = null) {

		$result = "";

		foreach ( $this->current_post->tags as $tag ) {
			if ( 'rdf' == $type )
				$result .= "<dc:subject><![CDATA[ $tag ]]></dc:subject>";
			elseif ( 'atom' == $type )
				$result .= sprintf( '<category scheme="%1$s" term="%2$s" />', esc_attr( apply_filters( 'get_bloginfo_rss', get_bloginfo( 'url' ) ) ), esc_attr( $tag ) );
			else
				$result .= "<category><![CDATA[" . @html_entity_decode( $tag, ENT_COMPAT, get_option('blog_charset') ) . "]]></category>";
		}

		return $result;
	}

	/**
	 * Returns the guid for this post
	 * @return string
	 * @since 1.0
	 */
	function get_the_guid() {
		return $this->current_post->guid;
	}

	/**
	 * Returns the content of the post
	 * @param string $feed_type
	 * @return string
	 * @since 1.0
	 */
	function get_the_content_feed($feed_type = null) {
		if ( !$feed_type )
			$feed_type = get_default_feed();

		$content = apply_filters('the_content', $this->current_post->post_content);
		$content = str_replace(']]>', ']]&gt;', $content);
		return apply_filters('the_content_feed', $content, $feed_type);
	}

	/**
	 * Returns the url to the comments feed
	 * @param string $type
	 * @return string
	 * @since 1.0
	 */
	function get_post_comments_feed_link($type) {

		if ( 'rss2' == $type ) {
			return $this->current_post->comment_rss;
		} else {

			if ( substr($this->current_post->comment_rss, -6, 6) == '/feed/' ) {
				return $this->current_post->comment_rss . $type . '/';
			} else {
				return str_replace("feed=rss2", "feed=$type", $this->current_post->comment_rss);
			}

		}
	}
}

/**
 * Helper function overriding the native WordPress function
 * @return bool
 * @since 1.0
 */
function have_posts() {
	$rss_posts = RSS_Posts::getInstance();
	return $rss_posts->have_posts();
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_post() {
	$rss_posts = RSS_Posts::getInstance();
	$rss_posts->the_post();
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_title_rss() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_title_rss();
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_permalink_rss() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_permalink_rss();
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function comments_link_feed() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->comments_link_feed();
}

/**
 * Helper function overriding the native WordPress function
 * @param string $format
 * @param bool $gmt
 * @return string
 * @since 1.0
 */
function get_post_time($format, $gmt = null) {
	$rss_posts = RSS_Posts::getInstance();
	unset($gmt); // not used, supresses editor warning
	return $rss_posts->get_post_time($format);
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_author() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_author();
}

/**
 * Helper function overriding the native WordPress function
 * @param string $type
 * @since 1.0
 */
function the_category_rss($type = null) {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_category_rss($type);
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_guid() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_guid();
}

/**
 * Helper function overriding the native WordPress function
 * @since 1.0
 */
function the_excerpt_rss() {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_excerpt_rss();
}

/**
 * Helper function overriding the native WordPress function
 * @param null $type
 * @since 1.0
 */
function the_content_feed($type = null) {
	$rss_posts = RSS_Posts::getInstance();
	echo $rss_posts->get_the_content_feed($type);
}

/**
 * Helper function overriding the native WordPress function
 * @param int $post_id
 * @param string $feed
 * @return string
 * @since 1.0
 */
function get_post_comments_feed_link($post_id = 0, $feed = '') {
	unset($post_id); // unused, supresses editor warning
	$rss_posts = RSS_Posts::getInstance();
	return $rss_posts->get_post_comments_feed_link($feed);
}

/**
 * Helper function overriding the native WordPress function
 * @return int
 * @since 1.0
 */
function get_comments_number() {
	$rss_posts = RSS_Posts::getInstance();
	return $rss_posts->get_comments_number();
}

/**
 * Helper function overriding the native WordPress function
 * @param string $format
 * @return string
 * @since 1.0
 */
function get_post_modified_time($format) {
	$rss_posts = RSS_Posts::getInstance();
	return $rss_posts->get_post_modified_time($format);
}