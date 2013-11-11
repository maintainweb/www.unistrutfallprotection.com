<?php

/**
 * Addon class
 *
 * @since 1.0
 *
 */
abstract class CAC_Sortable_Model {

	protected $storage_model;

	abstract function get_sortables();

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {
		$this->storage_model = $storage_model;

		// enable sorting per column
		add_action( "cac/columns/registered/default/storage_key={$this->storage_model->key}", array( $this, 'enable_sorting' ) );
		add_action( "cac/columns/registered/custom/storage_key={$this->storage_model->key}", array( $this, 'enable_sorting' ) );

		// handle reset request
		add_action( 'admin_init', array( $this, 'handle_reset' ) );
	}

	/**
	 * Add reset button
	 *
	 * Which resets the sorting to it's default.
	 *
	 * @since 1.0
	 */
	function add_reset_button() {
		global $post_type_object, $pagenow;

		if (
			// corrrect page?
			( $this->storage_model->page . '.php' !== $pagenow ) ||
			// posttype?
			( isset( $post_type_object->name ) && $post_type_object->name !== $this->storage_model->key )
			)
			return false;

		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {

				jQuery('.tablenav.top .actions:last').append('<a href="javascript:;" id="cpac-reset-sorting" class="cpac-edit add-new-h2"><?php _e( 'Reset sorting', CAC_SC_TEXTDOMAIN ); ?></a>');
				jQuery('#cpac-reset-sorting').click( function(){
					jQuery('#post-query-submit').trigger('mousedown'); // reset bulk actions
					jQuery('<input>').attr({
					    type: 'hidden',
					    name: 'reset-sorting',
					    value: '<?php echo $this->storage_model->key; ?>'
					}).appendTo(this);
					jQuery(this).closest('form').submit();
				});
			});
		</script>
		<?php
	}

	/**
	 * Handle reset request
	 *
	 * @since 1.0
	 */
	function handle_reset() {
		global $pagenow;

		if ( $this->storage_model->page . '.php' != $pagenow || empty( $_REQUEST['reset-sorting'] ) || $_REQUEST['reset-sorting'] != $this->storage_model->key )
			return false;

		$options = get_user_meta( get_current_user_id(), 'cpac_sorting_preference', true );

		if ( isset( $options[ $_REQUEST['reset-sorting'] ] ) )
			unset( $options[ $_REQUEST['reset-sorting'] ] );

		update_user_meta( get_current_user_id(), 'cpac_sorting_preference', $options );

		$admin_url = trailingslashit( admin_url() ) . $this->storage_model->page . '.php';

		if ( 'post' == $this->storage_model->type )
			$admin_url = $admin_url . '?post_type=' . $this->storage_model->key;

		wp_safe_redirect( $admin_url );
		exit;
	}

	/**
	 * Enable sorting
	 *
	 * @since 1.0
	 */
	function enable_sorting( $columns ) {

		foreach ( $columns as $column ) {

			if( ! in_array( $column->properties->type, $this->get_sortables() ) )
				continue;

			// enable sorting
			$column->set_properties( 'is_sortable', true );

			// set sort default to 'on'
			$column->set_options( 'sort', 'on' );
		}
	}

	/**
	 * Get column by orderby
	 *
	 * Returns column object based on which column heading is sorted.
	 *
	 * @since 1.0
	 *
	 * @param string $orderby
	 * @param string $type
	 * @return array Column
	 */
	protected function get_column_by_orderby( $orderby ) {

		$column = false;

		if ( $columns = $this->storage_model->get_columns() ) {
			foreach ( $columns as $_column ) {
				if ( $orderby == $_column->get_sanitized_label() ) {
					$column = $_column;
				}
			}
		}

		return apply_filters( 'cac/column/by_orderby', $column, $orderby, $this->storage_model->key );
	}

	/**
	 * Apply sorting preference
	 *
	 * @since 1.0
	 *
	 * @param array &$vars
	 * @param string $type
	 */
	protected function apply_sorting_preference( &$vars ) {

		$type = $this->storage_model->key;

		// user has not sorted
		if ( empty( $_GET['orderby'] ) ) {

			$options = get_user_meta( get_current_user_id(), 'cpac_sorting_preference', true );

			// did the user sorted this column some other time?
			if ( ! empty( $options[ $type ] ) ) {
				$vars['orderby'] = $options[ $type ]['orderby'];
				$vars['order'] 	 = $options[ $type ]['order'];

				// to make sure we got correct pagination on the list table. ( normally this argument is passed on a manual sort request )
				// @todo: could have a second look to see if there is more elegant solution.
				if ( 'post' == $this->storage_model->type ) {
					$per_page = (int) get_user_option( "edit_{$this->storage_model->key}_per_page" );
					$vars['posts_per_archive_page'] = $per_page ? $per_page : 20 ;
				}
			}
		}

		// save the order preference
		if ( ! empty( $vars['orderby'] ) ) {

			$options = get_user_meta( get_current_user_id(), 'cpac_sorting_preference', true );

			$options[ $type ] = array(
				'orderby'	=> $vars['orderby'],
				'order'		=> $vars['order']
			);

			update_user_meta( get_current_user_id(), 'cpac_sorting_preference', $options );
		}

		return $vars;
	}

	/**
	 * Prepare the value for being by sorting
	 *
	 * Removes tags and only get the first 20 chars and force lowercase.
	 *
	 * @since 1.0
	 *
	 * @param string $string
	 * @return string String
	 */
	protected function prepare_sort_string_value( $string ) {

		return strtolower( substr( trim( strip_tags( $string ) ), 0, 20 ) );
	}

	/**
	 * Set post__in for use in WP_Query
	 *
	 * This will order the ID's asc or desc and set the appropriate filters.
	 *
	 * @since 1.0
	 *
	 * @param array &$vars
	 * @param array $sortposts
	 * @param const $sort_flags
	 * @return array Posts Variables
	 */
	protected function get_vars_post__in( $vars, $unsorted, $sort_flag = SORT_REGULAR ) {

		if ( $vars['order'] == 'asc' )
			asort( $unsorted, $sort_flag );
		else
			arsort( $unsorted, $sort_flag );

		$vars['orderby']	= 'post__in';
		$vars['post__in']	= array_keys( $unsorted );

		return $vars;
	}

	/**
	 * Add sortable headings
	 *
	 * @since 1.0
	 *
	 * @param array $columns
	 * @return array Column name | Sanitized Label
	 */
	public function add_sortable_headings( $columns ) {

		// get columns from storage model.
		// columns that are active and have enabled sort will be added to the sortable headings.
		if ( $_columns = $this->storage_model->get_columns() ) {

			foreach ( $_columns as $column ) {

				if ( $column->properties->is_sortable ) {

					if ( 'on' == $column->options->sort ) {
						$columns[ $column->properties->name ] = $column->get_sanitized_label();
					}

					if ( 'off' == $column->options->sort ) {
						unset( $columns[ $column->properties->name ] );
					}
				}
			}
		}

		return $columns;
	}
}