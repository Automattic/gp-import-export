<?php

class GP_Route_Importer extends GP_Route_Main {

	function __construct() {
		$this->template_path = dirname( dirname( __FILE__ ) ) . '/templates/';
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
		$working_path = '/tmp' . $working_directory;

		// Make sure we have a fresh working directory.
		if ( file_exists( $working_path ) ) {
			GP_Importer::rrmdir( $working_path );
		}

		mkdir( $working_path );
		system("unzip -qq {$_FILES['import-file']['tmp_name']} *.po -d $working_path && rm {$_FILES['import-file']['tmp_name']} ");

		$pofiles = glob("$working_path/*.po");
		if ( empty( $pofiles) ) {
			GP_Importer::rrmdir( $working_path );
			$this->redirect_with_error( __( 'No PO files found in zip archive' ) );
		}

		$this->tmpl( 'importer-files', get_defined_vars() );
	}

	function confirm_selections( $project ) {

		$working_directory = gp_post( 'working-directory' );
		$working_path = '/tmp' . $working_directory;

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
		GP_Importer::rrmdir( $working_path );

		$this->redirect();
	}

}