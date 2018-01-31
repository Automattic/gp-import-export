<?php
gp_title( sprintf( __('Bulk Export Translations &lt; %s &lt; GlotPress'), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	__('Bulk Export')
) );

if ( ! function_exists( 'gp_multiselect' ) ) {
	function gp_multiselect( $name_and_id, $options, $selected_key = array(), $attrs = array() ) {
		$attrs['multiple'] = 'multiple';
		$attributes = gp_html_attributes( $attrs );
		$attributes = $attributes? " $attributes" : '';
		$res = "<select name='" . esc_attr( $name_and_id ) . "' id='" . esc_attr( $name_and_id ) . "' $attributes>\n";
		if ( ! is_array($selected_key) ) $selected_key = array( $selected_key ) ;
		foreach( $options as $value => $label ) {
			$selected = in_array( $value, $selected_key ) ? " selected='selected'" : '';
			$res .= "\t<option value='".esc_attr( $value )."' $selected>" . esc_html( $label ) . "</option>\n";
		}
		$res .= "</select>\n";
		return $res;
	}
}

gp_tmpl_header();
?>
<style type="text/css">
#selected-translationsets {
	position: absolute;
	margin-left: 15em;
	width: 20em;
	max-height: 10em;
	overflow: auto;
}
</style>

	<h2><?php _e('Bulk Export Translations'); ?></h2>
	<form id="export-filters" class="export-filters" action="<?php echo esc_url( gp_url_current() ); ?>/-do" method="get" accept-charset="utf-8" style="padding: 1em">
		<dl>
			<dt><label>Translation Sets</label></dt>
			<dd>
			<div id="selected-translationsets"></div>
				<?php echo gp_multiselect( 'translation_sets[]', $sets_for_select, gp_post('translation_sets'), array( 'size' => '12', 'style' =>'height:auto;' ) ); ?>
				<?php foreach ( $translation_set_selectors as $name => $sets ) {
					?><br /><a href="" onclick="selectTranslationSets([<?php echo implode(',', $sets); ?>]);return false">Select <?php echo esc_html( $name ); ?></a><?php
				} ?>
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
			<dt><label><?php _e( 'Translations added between:' ); ?></label></dt>
			<dd>
			<?php /* translators: %1$s: from date, %2$s: to date */ printf( __( '%1$s - %2$s' ), '<input type="date" name="filters[before_date_added]" placeholder="YYYY-MM-DD"/>', '<input type="date" name="filters[after_date_added]" placeholder="YYYY-MM-DD" />' ); ?>
				<a href="" id="last-month"><?php _e( 'last month' ); ?></a>
				<a href="" id="clear-dates"><?php _e( 'clear' ); ?></a>
			</dd>
		</dl>
		<p><input type="submit" value="<?php echo esc_attr( __( 'Export' ) ); ?>" name="export" /></p>
	</form>


<script type="text/javascript">

var updateSelectedTranslationSets = function(  ) {
	var count = jQuery( '#translation_sets\\[\\] option:selected' ).length;
	jQuery( '#selected-translationsets' ).html( (count === 1 ? '1 translation set selected' : count + ' translation sets selected') + '<br/>' + jQuery( '#translation_sets\\[\\] option:selected' ).clone().not(":last").append(", ").end().first().end().text() );
};
updateSelectedTranslationSets();
jQuery( '#translation_sets\\[\\]' ).on( 'change click', updateSelectedTranslationSets );

var selectTranslationSets = function( sets ) {
	var select = jQuery( '#translation_sets\\[\\] option' ).map( function() {
		this.selected = jQuery.inArray( Number( this.value ), sets) > -1;
	});
	updateSelectedTranslationSets();
};

jQuery( '#last-month' ).on( 'click', function() {
	var d = new Date;
	d.setDate( 1 );
	d.setMonth( d.getMonth() - 1 );
	jQuery( 'input[name=filters\\[before_date_added\\]]' ).val( getMySQLDate( d ) );
	d = new Date;
	d.setDate( 0 );
	jQuery( 'input[name=filters\\[after_date_added\\]]' ).val( getMySQLDate( d ) );
	return false;
});

jQuery( '#clear-dates' ).on( 'click', function() {
	jQuery( 'input[name=filters\\[before_date_added\\]], input[name=filters\\[after_date_added\\]]' ).val( '' );
	return false;
});

function getMySQLDate( date ) {
	var day = date.getDate(),
		month = date.getMonth() + 1,
		year = date.getYear();

	if ( year < 2000 ) {
		year += 1900;
	}
	if ( month < 10 ) {
		month = '0' + String( month );
	}
	if ( day < 10 ) {
		day = '0' + String( day );
	}
	return year + '-' + month + '-' + day;
}
</script>

<?php gp_tmpl_footer();
