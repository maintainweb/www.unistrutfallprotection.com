<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Sortable_Model_Comment extends CAC_Sortable_Model {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {
		parent::__construct( $storage_model );

		// handle sorting request
		add_filter( 'comments_clauses', array( $this, 'handle_sorting_request'), 10, 2 );

		// register sortable headings
		add_filter( "manage_edit-comments_sortable_columns", array( $this, 'add_sortable_headings' ) );

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
			'comment',

			// Custom Columns
			'column-comment_id',
			'column-author',
			'column-author_ip',
			'column-author_url',
			'column-author_email',
			'column-reply_to',
			'column-approved',
			'column-date',
			'column-agent',
			'column-excerpt',
			'column-date_gmt',
			'column-meta'
		);

		return $column_names;
	}

	/**
	 * Orderby Comments column
	 *
	 * @since 1.0
	 *
	 * @param array $pieces SQL pieces
	 * @param array $ref_comment Comment Query
	 * @return array SQL pieces
	 */
	public function handle_sorting_request( $pieces, $ref_comment ) {

		global $pagenow;

		// fire only when we are in the admins edit-comments
		if ( 'edit-comments.php' !== $pagenow )
			return $pieces;

		$vars = array (
			'orderby' 	=> $ref_comment->query_vars['orderby'],
			'order'		=> $pieces['order']
		);

		// apply sorting preference
		$this->apply_sorting_preference( $vars );

		// Column
		$column = $this->get_column_by_orderby( $vars['orderby'] );

		if ( empty( $column ) )
			return $pieces;

		switch ( $column->properties->type ) :

			// WP Default Columns
			case 'comment' :
				$pieces['orderby'] = 'comment_content';
				break;

			// Custom Columns
			case 'column-comment_id' :
				$pieces['orderby'] = 'comment_ID';
				break;

			case 'column-author' :
				$pieces['orderby'] = 'comment_author';
				break;

			case 'column-author_ip' :
				$pieces['orderby'] = 'comment_author_IP';
				break;

			case 'column-author_url' :
				$pieces['orderby'] = 'comment_author_url';
				break;

			case 'column-author_email' :
				$pieces['orderby'] = 'comment_author_email';
				break;

			case 'column-reply_to' :
				$pieces['orderby'] = 'comment_parent';
				break;

			case 'column-approved' :
				$pieces['orderby'] = 'comment_approved';
				break;

			case 'column-date' :
				$pieces['orderby'] = 'comment_date';
				break;

			case 'column-agent' :
				$pieces['orderby'] = 'comment_agent';
				break;

			case 'column-excerpt' :
				$pieces['orderby'] = 'comment_content';
				break;

			case 'column-date_gmt' :
				$pieces['orderby'] = 'comment_date_gmt'; // this the default for Comment Query
				break;

			case 'column-meta' :
				global $wpdb;
				$pieces['join'] 	= $pieces['join'] . " JOIN $wpdb->commentmeta cm ON $wpdb->comments.comment_ID = cm.comment_id";
				$pieces['orderby'] 	= "cm.meta_value";
				$pieces['where'] 	= $pieces['where']. $wpdb->prepare( " AND cm.meta_key=%s", $column->options->field );
				break;

		endswitch;

		// set order
		$pieces['order'] = $vars['order'];

		return $pieces;
	}
}