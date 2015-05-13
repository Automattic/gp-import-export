<?php
gp_title( sprintf( __('Bulk Export Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Export')
) );

gp_tmpl_header();
?>
<style type="text/css">
#predefines {
	border: 1px #ccc solid;
	padding: .5em;
	float: right;
	width: 300px;
}

#predefines ul {
	margin-left: 0;
	padding-left: 1em;
}

#predefines ul li {
	display: block;
	margin-left: 0;
	padding-left: 0;
}

#predefines ul li a.delete {
	background-color: transparent;
	color: #c99;
	margin-right: 1em;
	font-size: 8pt;
}

#predefines h4 {
	margin-top: 0;
}

#predefines .options {
	font-size: 80%;
	float: right;
}
#save-predefined {
	float: right;
}
#import-predefined, #export-predefined {
	color: #999;
}
#export-predefined {
	display: none;
}
</style>
<h2><?php _e('Bulk Export Translations'); ?></h2>
<form id="export-filters" class="export-filters" action="<?php echo esc_url( gp_url_current() ); ?>/-do" method="get" accept-charset="utf-8">
	<div id="predefines">
		<span class="options">
			<a href="" id="import-predefined"><?php _e( 'Import' ); ?></a>
			<a href="" id="export-predefined"><?php _e( 'Export' ); ?></a>
		</span>
		<h4><?php _e( 'Restore previous form data' ); ?></h4>
		<ul></ul>
		<a href="" id="save-predefined"><?php _e( 'Save current form data' ); ?></a>
	</div>

	<dl>
		<dt><label>Translation Sets</label></dt>
		<dd>
			<?php echo gp_select( 'translation_sets[]', $sets_for_select, null, array( 'multiple' => 'multiple', 'size' => '12', 'style' =>'height:auto;' ) ); ?>
		</dd>
		<dt><label><?php _e( 'Translations status:' ); ?></label></dt>
		<dd>
			<?php
			echo gp_radio_buttons( 'filters[status]',
				array(
					'current_or_waiting_or_fuzzy_or_untranslated' => __('Current/waiting/fuzzy + untranslated (All)'),
					'current' => __('Current only'),
					'old' => __('Approved, but obsoleted by another string'),
					'waiting' => __('Waiting approval'),
					'fuzzy' => __('Fuzzy'),
					'rejected' => __('Rejected'),
					'untranslated' => __('Without current translation'),
					'either' => __('Any'),
				), 'current_or_waiting_or_fuzzy_or_untranslated' );
			?>
		</dd>

		<dt><label><?php _e('Originals priority:'); ?></label></dt>
		<dd>
			<?php
			$valid_priorities = GP::$original->get_static( 'priorities' );
			krsort( $valid_priorities);
			if ( ! isset( $can_write) || ! $can_write ) {
				//Non admins can't see hidden strings
				unset( $valid_priorities['-2'] );
			}
			foreach ( $valid_priorities as $priority => $label ):
				?>
				<input checked="checked" type="checkbox" name="filters[priority][]" value="<?php echo esc_attr( $priority );?>" id="priority[<?php echo esc_attr( $label );?>]"><label for='priority[<?php echo esc_attr( $label );?>]'><?php echo esc_html( $label );?></label><br />
			<?php endforeach; ?>
		</dd>
		<?php if( isset( $views_for_select ) ): ?>
		<dt><?php _e( 'Predefined View' ); ?></dt>
		<dd>
			<?php echo gp_select('filters[view]', $views_for_select, null ); ?>
		</dd>
		<?php endif; ?>
		<dt><label><?php _e( 'Format:' ); ?></label></dt>
		<dd>
			<?php
			$format_options = array();
			foreach ( GP::$formats as $slug => $format ) {
				$format_options[$slug] = $format->name;
			}
			echo gp_select( 'export-format', $format_options, 'po' );
			?>
		</dd>
	</dl>
	<p><input type="submit" value="<?php echo esc_attr( __( 'Export' ) ); ?>" name="export" /></p>
</form>

<script type="text/javascript">
var name_question = '<?php _e( 'Enter a name for this configuration' ); ?>';
var delete_question = '<?php _e( 'Do you want to delete "%s"?' ); ?>';
var copy_data = '<?php _e( 'Copy the export data below:' ); ?>';
var paste_data = '<?php _e( 'Paste your import data below:' ); ?>';
var invalid_data = '<?php _e( 'Unfortunately, the data you have entered is not valid JSON.' ); ?>';

jQuery( '#save-predefined' ).on( 'click', function() {
	var predefs,
		data = jQuery( this ).closest( 'form' ).serialize(),
		name = prompt( name_question );
	if ( ! name ) {
		return false;
	}

	predefs = getPredefData();
	predefs[ name ] = data;
	localStorage.setItem( 'predefs', JSON.stringify( predefs ) );
	updatePredefs();
	return false;
} );
jQuery( '#import-predefined' ).on( 'click', function() {
	var data = prompt( paste_data );

	try {
		JSON.parse( localStorage.getItem( 'predefs' ) );
	} catch ( e ) {
		alert( invalid_data );
		return false;
	}

	localStorage.setItem( 'predefs', data );
	updatePredefs();
	return false;
});

jQuery( '#export-predefined' ).on( 'click', function() {
	prompt( copy_data, localStorage.getItem( 'predefs' ) );
	return false;
});

jQuery( '#predefines' ).on( 'click', 'li span a', function() {
	var data = jQuery( this ).closest( 'li' ).data( 'form' ),
		form = jQuery( '#predefines' ).closest( 'form' );
	form.unserialize( data );
	return false;
} );

jQuery( '#predefines' ).on( 'click', 'li a.delete', function() {
	var li = jQuery( this ).closest( 'li' ),
		predefs = getPredefData();
	if ( ! confirm( delete_question.replace( '%s', li.data( 'name' )) ) ) {
		return false;
	}
	delete predefs[ li.data( 'name' ) ];
	localStorage.setItem( 'predefs', JSON.stringify( predefs ) );

	updatePredefs();
	return false;
} );

var addPredefItem = function( name, data ) {
	var li = jQuery( '<li><a href="" class="delete">X</a><span><a href="#">' );
	li.data( 'form', data ).data( 'name', name ).find( 'span a' ).text( name );
	jQuery( '#predefines ul' ).append( li );

};
var getPredefData = function() {
	try {
		return JSON.parse( localStorage.getItem( 'predefs' ) ) || {};
	} catch ( e ) {
		return {};
	}
};
var updatePredefs = function() {
	jQuery( '#predefines ul' ).html( '' );
	var name,
		predefs = getPredefData();
	for ( name in predefs ) {
		addPredefItem( name, predefs[ name ] );
	}

	if ( jQuery( '#predefines ul li' ).length ) {
		jQuery( '#export-predefined' ).show();
	} else {
		jQuery( '#export-predefined' ).hide();
	}
};
// Run when document is ready
jQuery( updatePredefs );

function decodeUriIfNecessary( str ) {
	if ( str.match( /%[0-9a-fA-F]{2}/ ) ) {
		try {
			return decodeURIComponent( str );
		} catch (e) {
			return str;
		}
	}
	return str;
}
jQuery.unserialize = function( str ) {
	var arr, i, parts,
		items = str.split( '&' ),
		ret = "{",
		arrays = [],
		index = "";

	for ( i = 0; i < items.length; i++ ) {
		parts = items[ i ].split( /=/ );
		if ( parts[ 0 ].substr( -6 ) === '%5B%5D' ) {
			// Process arrays without a key
			index = decodeUriIfNecessary( parts[ 0 ].substr( 0, parts[ 0 ].length - 6 ) );
			if ( arrays[ index ] === undefined ) {
				arrays[ index ] = [];
			}
			arrays[ index ].push( decodeUriIfNecessary( parts[ 1 ].replace( /\+/g, " " ) ) );
		} else {
			if ( parts.length > 1 ) {
				ret += "\"" + decodeUriIfNecessary( parts[ 0 ] ) + "\": \"" + decodeUriIfNecessary( parts[ 1 ].replace( /\+/g, " " ) ).replace( /\n/g, "\\n" ).replace( /\r/g, "\\r" ) + "\", ";
			}
		}
	}

	ret = ( ret != "{" ) ? ret.substr( 0, ret.length - 2 ) + "}" : ret + "}";
	ret = JSON.parse( ret );

	// Process the arrays without key
	for ( arr in arrays ) {
		ret[ arr ] = arrays[ arr ];
	}
	return ret;
};

jQuery.fn.unserialize = function( param ) {
	var i, val, parts,
		items = jQuery.unserialize( param ),
		applyString = function( index, item ) {
			var val, i;
			item = jQuery( item );
			if ( parts[ 1 ] instanceof Array ) {
				for ( i in parts[ 1 ] ) {
					val = "" + parts[ 1 ][ i ];
					if ( item.val() == decodeUriIfNecessary( val.replace( /\+/g, " " ) ) ) {
						item.prop( "checked", true );
					} else {
						if ( jQuery.inArray( item.val(), parts[ 1 ] ) < 0 ) {
							item.prop( "checked", false );
						}
					}
				}
			} else {
				val = "" + parts[ 1 ];
				if ( item.val() == decodeUriIfNecessary( val.replace( /\+/g, " " ) ) ) {
					item.prop( "checked", true );
				} else {
					item.prop( "checked", false );
				}
			}
		};

	for ( i in items ) {
		parts = ( items instanceof Array ) ? items[ i ].split( /=/ ) : [ i, ( items[ i ] instanceof Array ) ? items[ i ] : "" + items[ i ] ];
		parts[ 0 ] = decodeUriIfNecessary( parts[ 0 ] );

		if ( parts[ 0 ].indexOf( "[]" ) == -1 && parts[ 1 ] instanceof Array ) {
			parts[ 0 ] += "[]";
		}

		obj = this.find( '[name=\'' + parts[ 0 ] + '\']' );
		if ( obj.length == 0 ) {
			try {
				obj = this.parent().find( '[name=\'' + parts[ 0 ] + '\']' );
			} catch ( e ) {}
		}

		if ( typeof obj.attr( "type" ) == "string" && ( obj.attr( "type" ).toLowerCase() == "radio" || obj.attr( "type" ).toLowerCase() == "checkbox" ) ) {
			obj.each( applyString );
		} else if ( obj.length > 0 && obj[ 0 ].tagName == "SELECT" && parts[ 1 ] instanceof Array && obj.prop( "multiple" ) ) {
			// Here, i have an array for a multi-select.
			obj.val( parts[ 1 ] );
		} else {
			// When the value is an array, we join without delimiter
			val = ( parts[ 1 ] instanceof Array ) ? parts[ 1 ].join( "" ) : parts[ 1 ];
			// when the value is an object, we set the value to ""
			val = ( ( typeof val == "object" ) || ( typeof val == "undefined" ) ) ? "" : val;

			obj.val( decodeUriIfNecessary( val.replace( /\+/g, " " ) ) );
		}
	}
	return this;
};

</script>
<?php gp_tmpl_footer();
