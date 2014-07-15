<?php
gp_title( sprintf( __('Bulk Import Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Export')
) );

gp_tmpl_header();
?>
	<h2><?php _e('Bulk Export Translations'); ?></h2>
	<form id="export-filters" class="export-filters" action="<?php echo esc_url( gp_url_current() ); ?>/-do" method="get" accept-charset="utf-8">
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

<?php gp_tmpl_footer();