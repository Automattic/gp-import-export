<?php

class GP_Route_Import_export extends GP_Route_Main {

	function __construct() {
		$this->template_path = dirname( dirname( __FILE__ ) ) . '/templates/';
	}

	function exporter_get( $project_path ) {
		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		$can_write = $this->can( 'write', 'project', $project->id );

		if ( isset( GP::$plugins->views ) ) {
			GP::$plugins->views->set_project_id( $project->id );
			if ( $views = GP::$plugins->views->views ) {
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
		@ini_set('memory_limit', '256M');

		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		$format = gp_array_get( GP::$formats, gp_get( 'format', 'po' ), null );

		if ( ! $format ) {
			$this->die_with_404();
		}


		if ( isset( GP::$plugins->views ) ) {
			GP::$plugins->views->set_project_id( $project->id );
			$current_view = GP::$plugins->views->current_view;
		}

		$project_for_slug = isset( $current_view ) ? $project_path . '/' . $current_view : $project_path;
		$filters = gp_get('filters');
		if ( isset( $filters['status'] ) && $filters['status'] != 'current_or_waiting_or_fuzzy_or_untranslated' ) {
			$project_for_slug .= '/' . $filters['status'];
		}
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
				continue;
			}
			file_put_contents(  $filename, $format->print_exported_file( $project, $locale, $translation_set, $entries ) );
			$export_created = true;
		}

		if ( ! $export_created ) {
			$this->die_with_error( 'Error creating export files' );
		}

		$archive_name = $slug . '.zip';
		$archive_file =  sys_get_temp_dir() . '/' . $archive_name;

		$cwd = getcwd();
		chdir( sys_get_temp_dir() );
		$zip_command = "zip -r " . escapeshellarg( $archive_file ) . ' ' . escapeshellarg( $slug );
		$zip_output = array();
		$zip_status = null;
		exec( $zip_command, $zip_output, $zip_status );
		chdir( $cwd );
		if ( 0 !== $zip_status ) {
			//TODO: error
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

		if ( $this->cannot_and_redirect( 'write', 'project', $project->id ) ) {
			return;
		}

		$this->tmpl( 'importer', get_defined_vars() );
	}

	function importer_post( $project_path ) {
		$project = GP::$project->by_path( $project_path );

		if ( ! $project ) {
			$this->die_with_404();
		}

		if ( $this->cannot_and_redirect( 'write', 'project', $project->id ) ) {
			return;
		}

		$step = gp_post( 'importer-step', '1' );


		switch( $step ) {
			case 1:
			default:
				$this->process_archive_file( $project );
				break;
			case 2:
				$this->confirm_selections( $project );
				break;
			case 3:
				$this->process_imports( $project );
				break;
		}

	}

	function process_archive_file( $project ) {
		$sets = GP::$translation_set->by_project_id( $project->id );
		$sets_for_select = array_combine(
			array_map( function( $s ){ return $s->id; }, $sets ),
			array_map( function( $s ){ return $s->name; }, $sets )
		);

		$sets_for_select = array( '0' => __('&mdash; Translation Set &mdash;' ) ) + $sets_for_select;
		unset( $sets );

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
		$working_path = sys_get_temp_dir() . $working_directory;

		// Make sure we have a fresh working directory.
		if ( file_exists( $working_path ) ) {
			GP_Import_Export::rrmdir( $working_path );
		}

		mkdir( $working_path );
		system("unzip -j -qq {$_FILES['import-file']['tmp_name']} *.po -d $working_path");

		$pofiles = glob("$working_path/*.po");
		if ( empty( $pofiles) ) {
			GP_Import_Export::rrmdir( $working_path );
			$this->redirect_with_error( __( 'No PO files found in zip archive' ) );
		}

		$this->tmpl( 'importer-files', get_defined_vars() );
	}

	function confirm_selections( $project ) {

		$working_directory = gp_post( 'working-directory' );
		$working_path = sys_get_temp_dir() . $working_directory;

		if ( $working_path !== realpath( $working_path ) ) {
			$this->die_with_error( 'Error.' );
		}

		$to_import = array();
		$pofiles = glob( "$working_path/*.po" );
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

	function process_imports( $project ) {

		$working_directory = gp_post( 'working-directory' );
		$working_path = '/tmp' . $working_directory;

		if ( $working_path !== realpath( $working_path ) ) {
			$this->die_with_error( 'Error.' );
		}

		if ( 'no' == gp_post( 'overwrite', 'yes' ) ) {
			add_filter( 'translation_set_import_over_existing', '__return_false' );
		}

		if ( 'waiting' == gp_post( 'status', 'current' ) ) {
			add_filter( 'translation_set_import_status', function( $status ) {
				return 'waiting';
			});
		}

		$pofiles = glob( "$working_path/*.po" );
		foreach( $pofiles as $po_file ) {
			$target_set = gp_post( basename( $po_file, '.po') );
			if ( $target_set  ) {

				$translation_set = GP::$translation_set->get( $target_set );

				if ( ! $translation_set ) {
					$this->errors[] = sprintf( __( 'Couldn&#8217;t find translation set id %d!' ), $target_set );
				}

				$format = gp_array_get( GP::$formats, 'po', null );
				$translations = $format->read_translations_from_file( $po_file, $project );
				if ( ! $translations ) {
					$this->errors[] = sprintf( __( 'Couldn&#8217;t load translations from file %s!' ), basename( $po_file ) );
					continue;
				}
				$translations_added = $translation_set->import( $translations );
				$this->notices[] = sprintf( __( '%s translations were added from %s' ), $translations_added, basename( $po_file ) );
			}
		}
		$this->notices = array( implode('<br>', $this->notices ) );
		$this->errors = array( implode('<br>', $this->errors ) );

		//cleanup
		GP_Import_Export::rrmdir( $working_path );

		$this->redirect();
	}


	function headers_for_download( $filename ) {
		$this->header("Pragma: public");
		$this->header("Expires: 0");
		$this->header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		$this->header("Cache-Control: public");
		$this->header("Content-Description: File Transfer");
		$this->header("Content-type: application/octet-stream");
		$this->header("Content-Disposition: attachment; filename=\"" . basename( $filename ) . "\"");
		$this->header("Content-Transfer-Encoding: binary");
		$this->header("Content-Length: ".filesize( $filename ) );
	}

}