<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Import')
) );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data" id="step1form">
		<div id="inner-error" class="error" style="display: none"></div>
		<input type="hidden" name="importer-step" value="1">
		<dl id="step1">
			<dt><label for="import-file"><?php _e( 'Import File:' ); ?></label></dt>
			<dd><input type="file" name="import-file" id="import-file" /></dd>
			<dt><input type="submit" value="<?php echo esc_attr( __( 'Import' ) ); ?>"></dt>
		</dl>
		<dl id="step2">
		</dl>
		<dl id="step3">
		</dl>
	</form>

	<div id="tempframe-holder" style="display: none"></div>

	<script type="text/javascript">
	var updateErrorsInterval;
	jQuery( function() {
		jQuery( '#tempframe-holder' ).html( '<iframe name="tempframe"></iframe>' );
		jQuery( '#step1form' ).attr( 'target', 'tempframe' );
		updateErrorsInterval = setInterval( updateErrors, 1000 );
	}) ;
	function updateErrors() {
		if ( typeof window.tempframe === 'undefined' || typeof window.tempframe.jQuery === 'undefined' ) {
			return false;
		}
		var error = window.tempframe.jQuery('.error');
		if ( error.length > 0 ) {
			jQuery('#inner-error').html( error.html() ).show();
			jQuery('html, body').animate({
			    scrollTop: jQuery("#inner-error").offset().top
			}, 200);
		} else {
			jQuery('#inner-error').hide();
		}
	}
	</script>

<?php gp_tmpl_footer();
