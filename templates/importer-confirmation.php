<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb_project( $project );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data">
	<input type="hidden" name="importer-step" value="3">
	<input type="hidden" name="working-directory" value="<?php echo esc_attr( $working_directory );?>">
		<dl>
			<dt><?php _e( 'Select Sets' ); ?></dt>
			<dd>
			<?php foreach ( $to_import as $po => $destination ) : ?>
				<p>
					<?php echo basename( $po ); ?> &rarr; <?php echo $destination['name']; ?>
					<input type="hidden" name="<?php echo esc_attr( basename( $po, '.po' ) ) ; ?>" value="<?php echo absint( $destination['id'] );?>">
				</p>
			<?php endforeach; ?>
			</dd>
			<dt><?php _e( 'Overwrite Translations' ); ?></dt>
			<dd>
				<?php echo gp_radio_buttons( 'overwrite', array(
					'yes' => 'Overwrite existing translations',
					'no' => 'Only import new translations',
				), 'yes' ); ?>
			</dd>
			<dt><input type="submit" value="<?php echo esc_attr( __( 'Import' ) ); ?>"></dt>
		</dl>
	</form>

<?php gp_tmpl_footer();