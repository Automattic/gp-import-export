<?php

class GP_Route_Import_Export extends GP_Route_Main {

	function __construct() {
		$this->template_path = dirname( dirname( __FILE__ ) ) . '/templates/';
	}

	function exporter_get( $project_path ) {
		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		$can_write = $this->can( 'bulk-export', 'project', $project->id );

		if ( class_exists( 'GP_Views' ) ) {
			$views = GP_Views::get_instance();
			$views->set_project_id( $project->id );
			if ( $views = $views->views ) {
				$views_for_select = array( '' => __( '&mdash; Select &mdash;' ) );
				foreach ( $views as $id => $view ) {
					$views_for_select[ $id ] = $view->name;
				}
				unset( $views );
			}
		}


		$translation_sets = GP::$translation_set->by_project_id( $project->id );

		$values = array_map( function( $set ) { return $set->id; }, $translation_sets );
		$labels = array_map( function( $set ) { return $set->name_with_locale(); }, $translation_sets );
		$sets_for_select = apply_filters( 'exporter_translations_sets_for_select', array_combine( $values, $labels ), $project->id );
		$sets_for_select =  array( '' => __('&mdash; All &mdash;') ) +  $sets_for_select;

		$this->tmpl( 'exporter', get_defined_vars() );
	}

	function exporter_do_get( $project_path ) {
		@ini_set( 'memory_limit', '256M' );

		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		$format = gp_array_get( GP::$formats, gp_get( 'export-format', 'po' ), null );

		if ( ! $format ) {
			$this->die_with_404();
		}


		if ( class_exists( 'GP_Views' ) ) {
			$views = GP_Views::get_instance();
			$views->set_project_id( $project->id );
			$current_view = $views->current_view;
		}

		$project_for_slug = isset( $current_view ) ? $project_path . '/' . $current_view : $project_path;
		$filters = gp_get('filters');
		if ( isset( $filters['status'] ) && $filters['status'] != 'current_or_waiting_or_fuzzy_or_untranslated' ) {
			$project_for_slug .= '/' . $filters['status'];
		}
		$filters['priority'] = array_map( 'intval', $filters['priority'] );

		$slug = str_replace( '/', '-', $project_for_slug ) . '-' . date( 'Y-d-m-Hi' );

		$working_path = '/tmp/' . $slug ;

		// Make sure we have a fresh working directory.
		if ( file_exists( $working_path ) ) {
			GP_Import_Export::rrmdir( $working_path );
		}

		mkdir( $working_path );

		$translations_sets = apply_filters( 'exporter_translations_sets_for_processing', gp_get( 'translation_sets' ), $project->id );
		if ( ! array_filter( $translations_sets ) || in_array( '0', $translations_sets ) ){
			$_translation_sets = GP::$translation_set->by_project_id( $project->id );
			$translations_sets = array_map( function( $set ) { return $set->id; }, $_translation_sets );
		};

		$export_created = false;
		$empty = 0;
		foreach ( $translations_sets as $set_id ) {
			$translation_set = GP::$translation_set->get( $set_id );
			if ( ! $translation_set ) {
				$this->errors[] = 'Translation set not found'; //TODO: do something with this
				continue;
			}
			$locale = GP_Locales::by_slug( $translation_set->locale );
			$filename = $working_path . '/' . sprintf(  '%s-%s.' . $format->extension, str_replace( '/', '-', $project_for_slug ), $locale->slug );
			$entries = GP::$translation->for_export( $project, $translation_set, gp_get( 'filters' ) );
			if ( empty( $entries ) ) {
				$empty++;
				continue;
			}
			file_put_contents(  $filename, $format->print_exported_file( $project, $locale, $translation_set, $entries ) );
			$export_created = true;
		}

		if ( ! $export_created ) {
			if ( count( $translations_sets ) == count( $empty ) ) {
				$this->notices[] = 'No matches for your selection';
				$this->redirect();
			} else {
				$this->die_with_error( 'Error creating export files' );
			}
		}

		$tempdir = sys_get_temp_dir();
		$archive_file =  $tempdir . '/' . $slug . '.zip';

		$zip = new ZipArchiveExtended;
		if ( $zip->open( $archive_file, ZipArchiveExtended::CREATE | ZipArchiveExtended::OVERWRITE ) ) {
			$zip->addDir( $slug, $tempdir );
			$zip->close();
		}

		$this->headers_for_download( $archive_file );
		readfile( $archive_file );

		GP_Import_Export::rrmdir( $working_path );
		unlink( $archive_file );
	}

	function importer_get( $project_path ) {
		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		if ( $this->cannot_and_redirect( 'bulk-import', 'project', $project->id ) ) {
			return;
		}

		$this->tmpl( 'importer', get_defined_vars() );
	}

	function importer_post( $project_path ) {
		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		if ( $this->cannot_and_redirect( 'bulk-import', 'project', $project->id ) ) {
			return;
		}

		$step = gp_post( 'importer-step', '1' );

		// process the archive file upon each step because it is uploaded on every step because of the server-farm infrastructure
		$pofiles = $this->process_archive_file( $project );

		switch( $step ) {
			case 1:
			default:
				$this->show_selections( $project, $pofiles );
				break;
			case 2:
				$this->confirm_selections( $project, $pofiles );
				break;
			case 3:
				$this->process_imports( $project, $pofiles );
				break;
		}

	}

	/**
	 * extract the uploaded ZIP file
	 * @param  object $project the project derived from the URL
	 * @return array           the filenames of the extracted .po files
	 */
	function process_archive_file( $project ) {

		if ( ! is_uploaded_file( $_FILES['import-file']['tmp_name'] ) ) {
			$this->redirect_with_error( __( 'Error uploading the file.' ) );
			return;
		} else {
			if ( ! gp_endswith( $_FILES['import-file']['name'], '.zip' ) || ! in_array( $_FILES['import-file']['type'], array( 'application/octet-stream', 'application/zip' ) ) ) {
				$upload_error = true;
				$this->redirect_with_error( __( 'Please upload a zip file.' ) );
			}
		}

		$filename = preg_replace("([^\w\s\d\-_~,;:\[\]\(\].]|[\.]{2,})", '',  $_FILES['import-file']['name'] );
		$slug = preg_replace( '/\.zip$/', '', $filename );

		$working_directory = '/bulk-importer-' . $slug;
		$this->working_path = sys_get_temp_dir() . $working_directory;

		// Make sure we have a fresh working directory.
		if ( file_exists( $this->working_path ) ) {
			GP_Import_Export::rrmdir( $this->working_path );
		}

		mkdir( $this->working_path );

		$zip = new ZipArchiveExtended;
		if ( $zip->open( $_FILES['import-file']['tmp_name'] ) ) {
			$zip->extractToFlatten( $this->working_path );
			$zip->close();
		}

		$pofiles = glob( $this->working_path . '/*.po' );
		if ( empty( $pofiles ) ) {
			GP_Import_Export::rrmdir( $this->working_path );
			$this->redirect_with_error( __( 'No PO files found in zip archive' ) );
		}

		return $pofiles;
	}

	/**
	 * Step 1: extract the uploaded ZIP file
	 * @param  object $project the project derived from the URL
	 * @param  array $pofiles the filenames of the extracted .po files
	 */
	function show_selections( $project, $pofiles ) {
		$sets = GP::$translation_set->by_project_id( $project->id );
		$sets_for_select = array_combine(
			array_map( function( $s ){ return $s->id; }, $sets ),
			array_map( function( $s ){ return $s->locale . ' - ' . $s->name; }, $sets )
		);

		$sets_for_select = array( '0' => __('&mdash; Translation Set &mdash;' ) ) + $sets_for_select;
		unset( $sets );

		$this->tmpl( 'importer-files', get_defined_vars() );
	}


	/**
	 * Step 2: Confirm the locale mappings by the user and select
	 * @param  object $project the project derived from the URL
	 * @param  array $pofiles the filenames of the extracted .po files
	 */
	function confirm_selections( $project, $pofiles ) {

		$to_import = array();
		foreach( $pofiles as $po_file ) {
			$target_set = gp_post( basename( $po_file, '.po') );
			if ( $target_set  ) {

				$translation_set = GP::$translation_set->get( $target_set );

				if ( ! $translation_set ) {
					$this->errors[] = sprintf( __( 'Couldn&#8217;t find translation set id %d!' ), $target_set );
				}

				$to_import[ basename( $po_file ) ] = array( 'id' => $translation_set->id, 'name' => $translation_set->name_with_locale() );
			}
		}

		if ( empty( $to_import ) ) {
			$this->redirect_with_error( __( 'Please select a matching translation set for each PO file.' ) );
		}

		$this->tmpl( 'importer-confirmation', get_defined_vars() );
	}

	/**
	 * Step 3: Do the import
	 * @param  object $project the project derived from the URL
	 * @param  array $pofiles the filenames of the extracted .po files
	 */

	function process_imports( $project, $pofiles ) {

		if ( 'no' == gp_post( 'overwrite', 'yes' ) ) {
			add_filter( 'gp_translation_set_import_over_existing', '__return_false' );
		}

		if ( 'waiting' == gp_post( 'status', 'current' ) ) {
			add_filter( 'gp_translation_set_import_status', function( $status ) {
				return 'waiting';
			});
		}

		$last = end( $pofiles ); $processed = false;
		foreach( $pofiles as $po_file ) {
			$target_set = gp_post( basename( $po_file, '.po') );
			if ( $target_set  ) {
				$processed = $po_file;
				$id = basename( $po_file, '.po' );

				$translation_set = GP::$translation_set->get( $target_set );

				if ( ! $translation_set ) {
					$status = sprintf( __( 'Couldn&#8217;t find translation set id %d!' ), $target_set );
					break;
				}

				$format = gp_array_get( GP::$formats, 'po', null );
				$translations = $format->read_translations_from_file( $po_file, $project );
				if ( ! $translations ) {
					$status = sprintf( __( 'Couldn&#8217;t load translations from file %s!' ), basename( $po_file ) );
					break;
				}
				$translations_added = $translation_set->import( $translations );
				$status = sprintf( __( '%s translations were added from %s' ), $translations_added, basename( $po_file ) );
			}
		}

		// cleanup
		if ( $this->working_path && $processed === $last ) {
			GP_Import_Export::rrmdir( $this->working_path );
		}

		$this->tmpl( 'importer-report-status', get_defined_vars() );
	}


	function headers_for_download( $filename, $last_modified = '' ) {
		$this->header( 'Pragma: public' );
		$this->header( 'Expires: 0' );
		$this->header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		$this->header( 'Cache-Control: public' );
		$this->header( 'Content-Description: File Transfer' );
		$this->header( 'Content-type: application/octet-stream' );
		$this->header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
		$this->header( 'Content-Transfer-Encoding: binary' );
		$this->header( 'Content-Length: ' . filesize( $filename ) );
	}

}

if ( class_exists( 'ZipArchive' ) ) {
	class ZipArchiveExtended extends ZipArchive {

		public function addDir( $path, $basedir ) {
			$cwd = getcwd();
			chdir( $basedir );

			$this->addEmptyDir( $path );
			$nodes = glob( $path . '/*' );
			foreach ( $nodes as $node ) {
				if ( is_dir( $node ) ) {
					$this->addDir( $node );
				} else if ( is_file( $node ) )  {
					$this->addFile( $node );
				}
			}

			chdir( $cwd );
			return true;
		}

		public function extractToFlatten( $path ) {
			for ( $i = 0; $i < $this->numFiles; $i++ ) {

				$entry = $this->getNameIndex( $i );
				if ( substr( $entry, -1 ) == '/' ) continue; // skip directories to flatten the file structure

				$fp = $this->getStream( $entry );
				if ( $fp ) {
					$ofp = fopen( $path . '/' . basename( $entry ), 'w' );
					while ( ! feof( $fp ) ) {
						fwrite( $ofp, fread( $fp, 8192 ) );
					}

					fclose( $fp );
					fclose( $ofp );
				}
			}

			return $path;
		}
	}
} else {
	class ZipArchiveExtended {
		const CREATE = 0;
		const OVERWRITE = 0;

		private $archive_file;
		public function open( $archive_file, $flags = false ) {
			$this->archive_file = $archive_file;
			return true;
		}

		public function addDir( $path, $basedir ) {
			$cwd = getcwd();
			chdir( $basedir );

			$zip_command = 'zip -r ' . escapeshellarg( $this->archive_file ) . ' ' . escapeshellarg( $path );
			$zip_output = array();
			$zip_status = null;

			exec( $zip_command, $zip_output, $zip_status );

			if ( 0 !== $zip_status ) {
				return false;
			}

			return true;
		}

		public function extractToFlatten( $path ) {
			system( 'unzip -j -qq ' . $this->archive_file . ' *.po -d ' . escapeshellarg( $path ) );
			return $path;
		}

		public function close() {
		}

	}
}
