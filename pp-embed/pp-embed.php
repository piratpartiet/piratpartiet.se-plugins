<?php if ( ! defined( 'ABSPATH' ) ) die();
/*
 Plugin Name: Piratpartiet embeds
 Plugin URI: http://www.piratpartiet.se
 Description: Möjlighet att embedda data från data.piratpartiet.se, pirateweb.net och data.ungpirat.se
 Version: 1.0
 Author: Rickard Andersson
 Author URI: https://0x539.se
 */

/**
 * Class to hold plugin functionality
 */
class PP_Embed {

	/**
	 * Initializes the plugin
	 */
	public function __construct() {

		// Register handler for data.piratpartiet.se
		wp_embed_register_handler(
			'data.piratpartiet.se',
			'/https?:\/\/data.piratpartiet.se\/?(\S+)?/i',
			array(
				 $this,
				 'embed_pp'
			)
		);

		// Register handler for pirateweb.net
		wp_embed_register_handler(
			'pirateweb.net',
			'/https?:\/\/pirateweb.net\/pages\/Public\/PhpIncludes\/?(\S+)?/i',
			array(
				 $this,
				 'embed_pw'
			)
		);

		// Register handler for data.ungpirat.se
		wp_embed_register_handler(
			'data.ungpirat.se',
			'/https?:\/\/data.ungpirat.se\/(\S+)?/i',
			array(
				 $this,
				 'embed_up'
			)
		);
	}

	/**
	 * Handler function for data.ungpirat.se
	 *
	 * @param array  $matches
	 * @param array  $attr
	 * @param string $url
	 * @param array  $rawattr
	 *
	 * @return string
	 */
	public function embed_up( $matches, $attr, $url, $rawattr ) {
		return $this->load( $url );
	}

	/**
	 * Handler function for pirateweb.net
	 *
	 * @param array  $matches
	 * @param array  $attr
	 * @param string $url
	 * @param array  $rawattr
	 *
	 * @return string
	 */
	public function embed_pw( $matches, $attr, $url, $rawattr ) {
		return $this->load( $url );
	}

	/**
	 * Handler function for data.piratpartiet.se
	 *
	 * @param array  $matches
	 * @param array  $attr
	 * @param string $url
	 * @param array  $rawattr
	 *
	 * @return string
	 */

	public function embed_pp( $matches, $attr, $url, $rawattr ) {
		return $this->load( $url );
	}

	/**
	 * Fetches the contents of a URL
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function load( $url ) {

		$transient_key = 'pp-embed-' . crc32( $url );

		$content = get_transient( $transient_key );

		if ( ! $content ) {
			$content = @file_get_contents( $url );

			if ( ! $content ) {
				$content = $url;
			}

			set_transient( $transient_key, $content, 60 * 60 );
		}

		return $content;
	}
}

// Initialize plugin
$pp_embed = new PP_Embed();