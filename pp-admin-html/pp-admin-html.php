<?php if ( ! defined( 'ABSPATH' ) ) die();
/*
 Plugin Name: Piratpartiet admin html
 Plugin URI: http://www.piratpartiet.se
 Description: Ändrar så att även administratörer har rätt att publicera innehåll med HTML
 Version: 1.0
 Author: Rickard Andersson
 Author URI: https://0x539.se
 */

add_action( 'init', function () {
	$role = get_role( 'administrator' );
	$role->add_cap( 'unfiltered_html' );
} );