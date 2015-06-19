<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Import')
) );
gp_tmpl_header();

function get_key_to_be_selected( $name_and_id, $options ) {
	$selected_locale = '';

	foreach( $options as $value => $label ) {
		$locale = substr( $label, 0, strpos( $label, ' - ' ) );

		// we don't stop after the first match but continue the search because of cases like:
		// pt-br: br == Breton, but we want Brasilian Portuguese
		// zh vs. zh-cn vs zh-tw
		// In general: Take the longest possible match

		if ( strlen( $locale ) > strlen( $selected_locale ) ) {
			if ( preg_match( "#\b$locale\b#", $name_and_id ) ) {
				$selected_key = $value;
				$selected_locale = $locale;
			}
		}
	}
	return $selected_key;
}

?>

	<h2><?php _e('Bulk Import Translations'); ?></h2>
	<form action="" method="post" enctype="multipart/form-data" id="step2">
		<input type="hidden" name="importer-step" value="2">
		<dl>
			<dt><?php _e( 'Select Sets' ); ?></dt>
			<dd>
			<?php foreach ( $pofiles as $po ) : ?>
			<?php $po_name = basename( $po, '.po' ); ?>
				<p>
					<label for="<?php echo $po_name; ?>"><?php echo basename( $po ) ?> &rarr;</label>
					<?php echo gp_select( $po_name, $sets_for_select, get_key_to_be_selected( $po_name, $sets_for_select ) ); ?>
				</p>
			<?php endforeach; ?>
			</dd>
			<dt><input type="submit" value="<?php echo esc_attr( __('Review') ); ?>"></dt>
		</dl>
	</form>

	<script type="text/javascript">
		parent.document.getElementById('step1').style.display = 'none';
		parent.document.getElementById('step2').innerHTML = document.getElementById('step2').innerHTML;
	</script>

<?php gp_tmpl_footer();
