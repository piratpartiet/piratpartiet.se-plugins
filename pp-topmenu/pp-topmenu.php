<?php if ( ! defined( 'ABSPATH' ) ) die();
/*
Plugin Name: Piratpartiet toppmeny
Plugin URI: http://www.piratpartiet.se/
Description: Gemensam toppmenu för piratpartiet.se
Version: 1.0
Author: Rickard Andersson
Author URI: https://0x539.se/
*/

DEFINE( 'PP_TOPMENU_NAME', 'pp-topmenu' );
DEFINE( 'PP_TOPMENU_URL', 'http://wp02.exz.nu/pp/?render_menu=1&html5=1' );

class PP_Topmenu {

	/**
	 * On the main site the local menu will be rendered, on the other sites the menu will be
	 * fetched by an API call and rendered.
	 * @var bool
	 */
	private $main_site = true;

	/**
	 *
	 * Enter description here ...
	 */
	function __construct()
	{

		add_action( 'init', array( $this, $this->main_site ? 'init_main' : 'init_sub' ) );
		add_action( 'wp_footer', array( $this, 'footer' ) );

		add_action( 'pp-topmenu', array( $this, 'render' ) );
	}

	function footer()
	{
		?>
	<script>jQuery( '#topmenu .sub-menu .sub-menu' ).each( function () {
		jQuery( this ).css( 'marginLeft', jQuery( this ).parent().outerWidth() );
	} ); </script><?php
	}

	/**
	 *
	 * Enter description here ...
	 */
	public function init_main()
	{

		$this->init_assets();
		$this->init_menus();

		if ( isset( $_GET[ 'render_menu' ] ) ) {
			$this->handle_api_call();
		}
	}

	private function handle_api_call()
	{

		$container = isset( $_GET[ 'html5' ] ) ? 'nav' : 'div';
		$walker    = new MV_Cleaner_Walker_Nav_Menu();

		ob_start( array( $this, 'compress_html' ) );

		$this->render_css();
		$this->render_main( $walker, $container );
		die();
	}

	private function init_assets()
	{
		wp_enqueue_style( PP_TOPMENU_NAME . '-style', plugins_url() . '/' . PP_TOPMENU_NAME . '/style.css' );
	}

	private function init_menus()
	{
		register_nav_menu( PP_TOPMENU_NAME, __( 'PP Gemensam meny' ) );
	}

	/**
	 *
	 * Enter description here ...
	 */
	public function init_sub()
	{
	}

	private function render_css()
	{
		$stylesheet = sprintf( '/%s/style.css', dirname( plugin_basename( __FILE__ ) ) );

		printf( '<style type="text/css">%s</style>', file_get_contents( $stylesheet ) );
	}

	/**
	 *
	 * Enter description here ...
	 */
	public function render()
	{

		if ( $this->main_site === true ) {
			$walker = new PP_Topmenu_Walker();

			$this->render_main( $walker );
		} else {
			$this->render_sub();
		}
	}

	/**
	 *
	 * Enter description here ...
	 *
	 * @param object $walker
	 * @param string $container optional default: nav
	 */
	private function render_main( $walker, $container = 'nav' )
	{

		$args = array(
			'theme_location' => PP_TOPMENU_NAME,
			'container'      => $container,
			'walker'         => $walker,
		);

		wp_nav_menu( $args );

		?>
	<script>(function () {

		"use strict";

		document.getElementById( 'expander' ).addEventListener( 'click', function () {
			var tm = document.getElementById( 'topmenu' ),
				classes = tm.className.split( / / ),
				index = classes.indexOf( 'expanded' );

			if ( index !== -1 ) {
				delete classes[index];
			} else {
				classes.push( 'expanded' );
			}

			tm.className = classes.join( ' ' );
		} );
	}());</script><?php
	}

	/**
	 *
	 * Enter description here ...
	 */
	private function render_sub()
	{

		echo file_get_contents( PP_TOPMENU_URL );
	}

	function compress_html( $html )
	{
		// remove new line
		$html = str_replace( "\r\n", '', $html );
		$html = str_replace( "\n", '', $html );

		// remove tab
		$html = str_replace( "\t", ' ', $html );

		// remove unneccessary whitespace
		$html = preg_replace( '/(\s){2,}/', ' ', $html );

		// remove HTML comments
		$html = preg_replace( '/<!--([a-z0-9#\-_\s<>&;.=\/"\'])+-->/i', '', $html );

		// remove unneccessary quotes
		$html = preg_replace( '/([a-z]+)=[\'"]([0-9a-z\-_]+)["\']/i', '$1=$2', $html );

		// Replace self closing tags
		$html = preg_replace( '/\s?\/\s?>/', '>', $html );

		// remove empty attributes
		return preg_replace( '/[a-z]+=["\']{2}/i', '', $html );
	}
}

class PP_Topmenu_Walker extends Walker_Nav_Menu {

	private $first = true;

	function start_el( &$output, $item, $depth, $args )
	{

		if ( $this->first === true ) {
			$output .= '<li id="mainlink"><a href="http://www.piratpartiet.se/" title="Piratpartiet">Piratpartiet</a></li>';
			$output .= '<li id="expander"><button>≡</button></li>';

			$this->first = false;
		}

		return parent::start_el( $output, $item, $depth, $args );
	}
}

/**
 * @see http://www.mattvarone.com/wordpress/cleaner-output-for-wp_nav_menu/
 *      Enter description here ...
 *
 */
class MV_Cleaner_Walker_Nav_Menu extends Walker {
	var $tree_type = array( 'post_type', 'taxonomy', 'custom' );
	var $db_fields = array( 'parent' => 'menu_item_parent', 'id' => 'db_id' );

	private $first = true;

	function start_lvl( &$output, $depth )
	{
		$indent = str_repeat( "\t", $depth );

		$output .= "\n$indent<ul class=\"sub-menu\">\n";
	}

	function end_lvl( &$output, $depth )
	{
		$indent = str_repeat( "\t", $depth );

		$output .= "$indent</ul>\n";
	}

	function start_el( &$output, $item, $depth, $args )
	{

		if ( $this->first === true ) {
			$output .= '<li id="mainlink"><a href="http://www.piratpartiet.se/" title="Piratpartiet">Piratpartiet</a></li>';
			$output .= '<li id="expander"><button>≡</button></li>';

			$this->first = false;
		}

		global $wp_query;
		$indent      = ( $depth ) ? str_repeat( "\t", $depth ) : '';
		$class_names = '';
		$classes     = array();
		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
		if ( $class_names )
			$class_names = ' class="' . esc_attr( $class_names ) . '"';
		$id = apply_filters( 'nav_menu_item_id', '', $item, $args );
		$id = strlen( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';

		$output .= $indent . '<li' . $id . $class_names . '>';

		$attributes = ! empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';

		$attributes .= ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
		$attributes .= ! empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
		$attributes .= ! empty( $item->url ) ? ' href="' . esc_attr( $item->url ) . '"' : '';

		$item_output = $args->before;

		$item_output .= '<a' . $attributes . '>';
		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
		$item_output .= '</a>';
		$item_output .= $args->after;

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	function end_el( &$output, $item, $depth )
	{
		$output .= "</li>\n";
	}
}

$pptopmenu = new PP_Topmenu();