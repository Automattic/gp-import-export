<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Import')
) );
gp_tmpl_header();
?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data" id="step3" style="padding: 1em">
	<input type="hidden" name="importer-step" value="3">
		<dl>
			<dt><?php _e( 'Select Sets' ); ?></dt>
			<dd>
			<?php foreach ( $to_import as $po => $destination ) : ?>
				<p>
					<?php echo basename( $po ); ?> &rarr; <?php echo $destination['name']; ?>
					<input type="hidden" name="<?php echo esc_attr( basename( $po, '.po' ) ) ; ?>" value="<?php echo absint( $destination['id'] );?>" class="po-mapping" title="<?php echo basename( $po ); ?> &rarr; <?php echo $destination['name']; ?>" />
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
			<dt><?php _e( 'Translation Status' ); ?></dt>
			<dd>
				<?php echo gp_radio_buttons( 'status', array(
					'current' => 'Current',
					'waiting' => 'Waiting',
				), 'current' ); ?>
			</dd>
			<dt><input type="submit" value="<?php echo esc_attr( __( 'Import' ) ); ?>"></dt>
		</dl>
	</form>

	<script type="text/javascript">
		parent.document.getElementById('step2').style.display = 'none';
		parent.document.getElementById('step3').innerHTML = document.getElementById('step3').innerHTML;
	</script>


<?php gp_tmpl_footer();
