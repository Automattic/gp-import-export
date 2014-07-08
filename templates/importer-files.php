<?php

gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb_project( $project );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data">
	<input type="hidden" name="importer-step" value="2">
	<input type="hidden" name="working-directory" value="<?php echo esc_attr( $working_directory );?>">
		<dl>
			<dt><?php _e( 'Select Sets' ); ?></dt>
			<dd>
			<?php foreach ( $pofiles as $po ) : ?>
				<p>
					<label for="<?php echo basename( $po, '.po' ); ?>"><?php echo basename( $po ) ?> &rarr;</label>
					<?php echo gp_select( basename( $po, '.po' ), $sets_for_select, '' ); //TODO: try and match locale for file name ?>
				</p>
			<?php endforeach; ?>
			</dd>
			<dt><input type="submit" value="<?php echo esc_attr( __('Review') ); ?>"></dt>
		</dl>
	</form>

<?php gp_tmpl_footer();