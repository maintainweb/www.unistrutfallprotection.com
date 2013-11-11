<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Sortable_Model_Link extends CAC_Sortable_Model {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {
		parent::__construct( $storage_model );

		// handle sorting request
		add_filter( 'get_bookmarks', array( $this, 'handle_sorting_request' ), 10, 2 );

		// register sortable headings
		add_filter( "manage_{$storage_model->page}_sortable_columns", array( $this, 'add_sortable_headings' ) );

		// add reset button
		add_action( 'restrict_manage_comments', array( $this, 'add_reset_button' ) );
	}

	/**
	 * Get sortables
	 *
	 * @see CAC_Sortable_Model::get_sortables()
	 * @since 1.0
	 */
	function get_sortables() {

		$column_names = array(

			// WP default columns
			'rel',

			// Custom Columns
			'column-link_id',
			'column-owner',
			'column-length',
			'column-target',
			'column-description',
			'column-notes',
			'column-rss'
		);

		return $column_names;
	}

	/**
	 * Admin requests for orderby column
	 *
	 * Only works for WP_Query objects ( such as posts and media )
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars
	 * @return array Vars
	 */
	public function handle_sorting_request( $results, $vars ) {
		global $pagenow;

		if ( 'link-manager.php' !== $pagenow )
			return $results;

		// apply sorting preference
		$this->apply_sorting_preference( $vars );

		// no sorting
		if ( empty( $vars['orderby'] ) )
			return $results;

		// Column
		$column = $this->get_column_by_orderby( $vars['orderby'] );

		if ( empty( $column ) )
			return $results;

		// unsorted Posts
		$posts = array();

		// var
		$length = '';

		switch ( $column->properties->type ) :

			// WP Default Columns
			case 'rel':
				$vars['orderby'] = 'link_rel';
				break;

			// Custom columns
			case 'column-link_id':
				$vars['orderby'] = 'link_id';
				break;

			case 'column-owner':
				$vars['orderby'] = 'link_owner';
				break;

			case 'column-length':
				$vars['orderby'] = 'length';
				$length = ", CHAR_LENGTH(link_name) AS length";
				break;

			case 'column-target':
				$vars['orderby'] = 'link_target';
				break;

			case 'column-description':
				$vars['orderby'] = 'link_description';
				break;

			case 'column-notes':
				$vars['orderby'] = 'link_notes';
				break;

			case 'column-rss':
				$vars['orderby'] = 'link_rss';
				break;

			default :
				$vars['orderby'] = '';

		endswitch;

		// get bookmarks by orderby vars
		if ( $vars['orderby'] ) {
			global $wpdb;

			$vars['order'] 		= mysql_escape_string( $vars['order'] );
			$vars['orderby'] 	= mysql_escape_string( $vars['orderby'] );
			$bookmarks			= $wpdb->get_results( "SELECT * {$length} FROM {$wpdb->links} WHERE 1=1 ORDER BY {$vars['orderby']} {$vars['order']}" );

			// check for errors
			if ( ! is_wp_error( $bookmarks ) ) {
				$results = $bookmarks;

			}
		}

		return $results;
	}
}