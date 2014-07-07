<?php

function gp_importer_rrmdir( $dir ) {
	foreach ( glob( $dir . '/{,.}*', GLOB_BRACE ) as $file ) { // Also look for hidden files

		if (  $file ==  $dir . '/.' || $file == $dir . '/..' ) { // but ignore dot directories
			continue;
		}

		if ( is_dir( $file ) ) {
			gp_importer_rrmdir( $file );
		} else {
			unlink( $file );
		}
	}

	rmdir( $dir );
}