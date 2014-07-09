<?php

/* GP_Importer
 * This plugin adds a "Bulk Import" project action that allows admins to import
 * a zip archive with multiple PO files to different sets
 */

require_once( dirname(__FILE__) .'/includes/router.php' );

class GP_Importer extends GP_Plugin {

	var $id = 'importer';

	public function __construct() {
		parent::__construct();
		$this->add_routes();
		$this->add_filter( 'gp_project_actions', array( 'args' => 2 ) );
	}

	function add_routes() {
		$path = '(.+?)';

		GP::$router->add( "/importer/$path", array( 'GP_Route_Importer', 'importer_get' ), 'get' );
		GP::$router->add( "/importer/$path", array( 'GP_Route_Importer', 'importer_post' ), 'post' );

	}

	function gp_project_actions( $actions, $project ) {
		$actions[] = gp_link_get( gp_url( '/importer/' . $project->path ), __( 'Bulk Import Translations' ) );
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

GP::$plugins->importer = new GP_Importer;
