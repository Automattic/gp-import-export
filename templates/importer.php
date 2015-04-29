<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Import')
) );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data"<?php if ( GP::$plugins->import_export->use_iframe ) echo ' target="tempframe"'; ?> id="step1form">
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

	<?php if ( GP::$plugins->import_export->use_iframe ): ?>
		<iframe name="tempframe" width="200" height="200" style="display: none"></iframe>
		<script type="text/javascript">
		setInterval( function() {
			var error = tempframe.jQuery('.error');
			if ( error.length > 0 ) {
				jQuery('#inner-error').html( error.html() ).show();
				jQuery('html, body').animate({
				    scrollTop: jQuery("#inner-error").offset().top
				}, 200);
			} else {
				jQuery('#inner-error').hide();
			}
		}, 1000); </script>
	<?php endif; ?>

<?php gp_tmpl_footer();
