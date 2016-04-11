<?php
/**
 * Plugin name: GlotPress: Bulk Import and Export
 * Plugin author: Automattic
 * Version: 1.0
 *
 * Description: This plugin adds "Bulk Import" and "Bulk Export" project actions
 *  - Import a zip archive with multiple PO files to different sets
 *  - Export a zip archive of translations based on filters.
 */

require_once( dirname(__FILE__) .'/includes/router.php' );

class GP_Import_Export {

	public $id = 'importer';
	public static $instance = null;

	public static function init() {
		self::get_instance();
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );
		add_filter( 'gp_project_actions', array( $this, 'gp_project_actions' ), 5, 2 );
	}

	function register_routes() {
		$path = '(.+?)';

		GP::$router->add( "/importer/$path", array( 'GP_Route_Import_Export', 'importer_get' ), 'get' );
		GP::$router->add( "/importer/$path", array( 'GP_Route_Import_Export', 'importer_post' ), 'post' );

		GP::$router->add( "/exporter/$path/-do", array( 'GP_Route_Import_Export', 'exporter_do_get' ), 'get' );
		GP::$router->add( "/exporter/$path", array( 'GP_Route_Import_Export', 'exporter_get' ), 'get' );
	}

	function gp_project_actions( $actions, $project ) {

		if ( GP::$permission->current_user_can( 'bulk-import', 'project', $project->id ) ) {
			$actions[] = gp_link_get( gp_url( '/importer/' . $project->path ), __( 'Bulk Import' ) );
		}

		if ( GP::$permission->current_user_can( 'bulk-export', 'project', $project->id ) ) {
			$actions[] = gp_link_get( gp_url( '/exporter/' . $project->path ), __( 'Bulk Export' ) );
		}

		return $actions;
	}

	public static function rrmdir( $dir ) {
		if ( trim( str_replace( array( '/', '.' ), '', $dir) ) == '' ) {
			// prevent empty argument, thus deleting more than wanted
			return false;
		}

		foreach ( glob( str_replace( '*', '', $dir ) . '/{,.}*', GLOB_BRACE ) as $file ) { // Also look for hidden files

			if (  $file == $dir . '/.' || $file == $dir . '/..' ) { // but ignore dot directories
				continue;
			}

			if ( is_dir( $file ) ) {
				self::rrmdir( $file );
			} else {
				unlink( $file );
			}
		}

		rmdir( $dir );
	}
}

add_action( 'gp_init', array( 'GP_Import_Export', 'init' ) );
