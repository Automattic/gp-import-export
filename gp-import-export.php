<?php

/* GP_Import_Export
 * This plugin adds "Bulk Import" and "Bulk Export" project actions
 *  - Import a zip archive with multiple PO files to different sets
 *  - Export a zip archive of translations based on filters.
 */

require_once( dirname(__FILE__) .'/includes/router.php' );

class GP_Import_Export extends GP_Plugin {

	var $id = 'importer';

	public function __construct() {
		parent::__construct();
		$this->add_routes();
		$this->add_filter( 'gp_project_actions', array( 'args' => 2 ) );
	}

	function add_routes() {
		$path = '(.+?)';

		GP::$router->add( "/importer/$path", array( 'GP_Route_Import_Export', 'importer_get' ), 'get' );
		GP::$router->add( "/importer/$path", array( 'GP_Route_Import_Export', 'importer_post' ), 'post' );

		GP::$router->add( "/exporter/$path/-do", array( 'GP_Route_Import_Export', 'exporter_do_get' ), 'get' );
		GP::$router->add( "/exporter/$path", array( 'GP_Route_Import_Export', 'exporter_get' ), 'get' );
	}

	function gp_project_actions( $actions, $project ) {

		if ( GP::$user->current()->can( 'bulk-import', 'project', $project->id ) ) {
			$actions[] = gp_link_get( gp_url( '/importer/' . $project->path ), __( 'Bulk Import' ) );
		}

		if ( GP::$user->current()->can( 'bulk-export', 'project', $project->id ) ) {
			$actions[] = gp_link_get( gp_url( '/exporter/' . $project->path ), __( 'Bulk Export' ) );
		}

		return $actions;
	}

	public static function rrmdir( $dir ) {
		foreach ( glob( $dir . '/{,.}*', GLOB_BRACE ) as $file ) { // Also look for hidden files

			if (  $file ==  $dir . '/.' || $file == $dir . '/..' ) { // but ignore dot directories
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

GP::$plugins->import_export = new GP_Import_Export;
