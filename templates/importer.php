<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Import')
) );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data">
		<input type="hidden" name="importer-step" value="1">
		<dl>
			<dt><label for="import-file"><?php _e( 'Import File:' ); ?></label></dt>
			<dd><input type="file" name="import-file" id="import-file" /></dd>
			<dt><input type="submit" value="<?php echo esc_attr( __( 'Import' ) ); ?>"></dt>
		</dl>
	</form>

<?php gp_tmpl_footer();