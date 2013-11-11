<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Sortable_Model_User extends CAC_Sortable_Model {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {
		parent::__construct( $storage_model );

		// handle sorting request
		add_action( 'pre_user_query', array( $this, 'handle_sorting_request'), 1 );

		// register sortable headings
		add_filter( "manage_users_sortable_columns", array( $this, 'add_sortable_headings' ) );

		// add reset button
		add_action( 'restrict_manage_users', array( $this, 'add_reset_button' ) );
	}

	/**
	 * @see CAC_Sortable_Model::get_sortables()
	 * @since 1.0
	 */
	function get_sortables() {

		$column_names = array(

			// WP default columns
			'role',

			// Custom Columns
			'column-user_id',
			'column-user_registered',
			'column-nickname',
			'column-first_name',
			'column-last_name',
			'column-user_url',
			'column-user_description',
			'column-user_commentcount',
			'column-user_postcount',
			'column-meta'
		);

		return $column_names;
	}

	/**
	 * Get Users
	 *
	 * @since 1.0
	 */
	function get_user_ids() {

		return get_users( array( 'fields' => 'ID' ) );
	}

	/**
	 * Admin requests for orderby column
	 *
	 * Only works for WP_Query objects ( such as posts and media )
	 *
	 * @since 1.0
	 *
	 * @param array $vars
	 * @return array Vars
	 */
	public function handle_sorting_request( $user_query ) {
		$vars = $user_query->query_vars;

		// prevent looping because this filter is trigered by get_users();
		if ( 'ID' == $vars['fields'] )
			return $user_query;

		// sorting event?
		if ( empty( $vars['orderby'] ) )
			return $user_query;

		// apply sorting preference
		$this->apply_sorting_preference( $vars );

		// Column
		$column = $this->get_column_by_orderby( $vars['orderby'] );

		if ( empty( $column ) )
			return $user_query;

		// unsorted Users
		$users = array();

		switch ( $column->properties->type ) :

			// WP Default Columns
			case 'role' :
				$sort_flag = SORT_REGULAR;
				foreach ( $this->get_user_ids() as $id ) {
					$u = get_userdata( $id );
					$role = ! empty( $u->roles[0] ) ? $u->roles[0] : '';
					if ( $role ) {
						$users[ $id ] = $this->prepare_sort_string_value( $role );
					}
				}
				break;

			// Custom Columns
			case 'column-user_id' :
				$user_query->query_orderby = "ORDER BY ID {$vars['order']}";
				$vars['orderby'] = 'ID';
				break;

			case 'column-user_registered' :
				$user_query->query_orderby = "ORDER BY user_registered {$vars['order']}";
				$vars['orderby'] = 'registered';
				break;

			case 'column-nickname' :
				$sort_flag = SORT_REGULAR;
				break;

			case 'column-first_name' :
				$sort_flag = SORT_REGULAR;
				break;

			case 'column-last_name' :
				$sort_flag = SORT_REGULAR;
				break;

			case 'column-user_url' :
				$sort_flag = SORT_REGULAR;
				break;

			case 'column-user_description' :
				$sort_flag = SORT_REGULAR;
				break;

			case 'column-user_commentcount' :
				$sort_flag = SORT_NUMERIC;
				break;

			case 'column-user_postcount' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_user_ids() as $id ) {
					$users[ $id ] = $column->get_count( $id );
				}
				break;

			case 'column-meta' :
				$sort_flag = SORT_REGULAR;
				if ( in_array( $column->options->field_type, array( 'numeric', 'library_id' ) ) )
					$sort_flag = SORT_NUMERIC;

				if ( 'checkmark' == $column->options->field_type ) {
					foreach ( $this->get_user_ids() as $id ) {
						$value = $column->get_value( $id );
						$users[ $id ] = $this->prepare_sort_string_value( $value ? '1' : '0' );
					}
				}
				if ( in_array( $column->options->field_type, array( 'image', 'library_id' ) ) ) {
					$sort_flag = SORT_NUMERIC;
					foreach ( $this->get_user_ids() as $id ) {
						$thumbs = $column->get_thumbnails( $column->get_meta_by_id( $id ) );
						$users[ $id ] = $thumbs ? count( $thumbs ) : 0;
					}
				}
				break;

		endswitch;

		if ( isset( $sort_flag ) ) {

			// set sorting value
			if ( empty( $users ) ) {
				foreach ( $this->get_user_ids() as $id ) {
					$users[ $id ] = $this->prepare_sort_string_value( $column->get_value( $id ) );
				}
			}

			// sorting
			if ( 'ASC' == $vars['order'] )
				asort( $users, $sort_flag );
			else
				arsort( $users, $sort_flag );

			// alter orderby SQL
			if ( ! empty( $users ) ) {
				global $wpdb;

				$column_names = implode( ',', array_keys( $users ) );
				$user_query->query_where 	.= " AND {$wpdb->prefix}users.ID IN ({$column_names})";
				$user_query->query_orderby 	= "ORDER BY FIELD({$wpdb->prefix}users.ID,{$column_names})";
			}

			// cleanup the vars we dont need
			$vars['order']	 = '';
			$vars['orderby'] = '';
		}

		$user_query->query_vars = array_merge( $user_query->query_vars, $vars );

		return $user_query;
	}
}