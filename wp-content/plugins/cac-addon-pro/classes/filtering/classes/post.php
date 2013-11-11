<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Filtering_Model_Post extends CAC_Filtering_Model {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {

		$this->storage_model = $storage_model;

		// enable filtering per column
		add_action( "cac/columns/registered/default/storage_key={$this->storage_model->key}", array( $this, 'enable_filtering' ) );
		add_action( "cac/columns/registered/custom/storage_key={$this->storage_model->key}", array( $this, 'enable_filtering' ) );

		// handle filtering request
		add_filter( 'request', array( $this, 'handle_filter_requests' ), 2 );

		// add dropdowns
		add_action( 'restrict_manage_posts', array( $this, 'add_filtering_dropdown' ) );

		// clear cache
		add_action( 'save_post', array( $this, 'clear_cache' ) );
	}

	/**
	 * Enable filtering
	 *
	 * @since 1.0
	 */
	function enable_filtering( $columns ) {

		$include_types = array(

			// WP default columns
			'categories',

			// Custom columns
			'column-sticky',
			'column-roles',
			'column-page_template',
			'column-ping_status',
			'column-post_formats',
			'column-excerpt',
			'column-comment_count',
			'column-before_moretag',
			'column-author_name',
			'column-featured_image',
			'column-comment_status',
			'column-status',
			'column-taxonomy',
			'column-meta',

			// @todo
			//'column-attachment',
			//'column-attachment_count',
		);

		foreach ( $columns as $column ) {
			if( in_array( $column->properties->type, $include_types ) ) {

				$column->set_properties( 'is_filterable', true );
			}
		}
	}

	/**
	 * Add where clause to WP_Query
	 *
	 * @since 1.0
	 */
	function posts_where_comment_status( $where ) {
		global $wpdb, $filter_values;
		return "{$where} AND $wpdb->posts.comment_status = '{$filter_values['column-comment_status']}'";
	}
	function posts_where_author_name( $where ) {
		global $wpdb, $filter_values;
		return "{$where} AND $wpdb->posts.post_author = '{$filter_values['column-author_name']}'";
	}
	function posts_where_before_moretag( $where ) {
		global $wpdb, $filter_values;
		return "{$where} AND $wpdb->posts.post_content" . $this->get_sql_value( $filter_values['column-before_moretag'], '<!--more-->' );
	}
	function posts_where_comment_count( $where ) {
		global $wpdb, $filter_values;
		$val = $filter_values['column-comment_count'];
		$sql_val = ' = ' . $val;
		if( 'cpac_not_empty' == $val )
			$sql_val = ' != 0';
		if( 'cpac_empty' == $val )
			$sql_val = ' = 0';
		return "{$where} AND $wpdb->posts.comment_count" . $sql_val;
	}
	function posts_where_excerpt( $where ) {
		global $wpdb, $filter_values;
		$val = $filter_values['column-excerpt'];
		if( 'cpac_not_empty' == $val )
			$sql_val = " != ''";
		if( 'cpac_empty' == $val )
			$sql_val = " = ''";
		return "{$where} AND $wpdb->posts.post_content" . $sql_val;
	}
	function posts_where_ping_status( $where ) {
		global $wpdb, $filter_values;
		return "{$where} AND $wpdb->posts.ping_status = '{$filter_values['column-ping_status']}'";
	}
	function posts_where_sticky( $where ) {
		global $wpdb, $filter_values;
		$val = $filter_values['column-sticky'];
		if ( ! $stickies = get_option( 'sticky_posts' ) )
			return $where;
		if( 'cpac_not_empty' == $val )
			$sql_val = " IN ('" . implode( "','", $stickies ) . "')";
		if( 'cpac_empty' == $val )
			$sql_val = " NOT IN ('" . implode( "','", $stickies ) . "')";
		return "{$where} AND $wpdb->posts.ID" . $sql_val;
	}

	/**
	 * Get SQL compare
	 *
	 * @since 1.0
	 *
	 * @param string $filter_value Selected filter value
	 * @param string $value_to_match_empty Overwrite the filter value
	 * @return string SQL compare
	 */
	private function get_sql_value( $filter_value, $value_to_match_empty = '' ) {
		$sql_query_compare = " = '{$filter_value}'";

		if( 'cpac_not_empty' == $filter_value ) {
			$val = $value_to_match_empty ? $value_to_match_empty : $filter_value;
			$sql_query_compare = " LIKE '%{$val}%'";
		}
		if( 'cpac_empty' == $filter_value ) {
			$val = $value_to_match_empty ? $value_to_match_empty : $filter_value;
			$sql_query_compare = " NOT LIKE '%{$val}%'";
		}

		return $sql_query_compare;
	}

	/**
	 * Handle filter request
	 *
	 * @since 1.0
	 */
	public function handle_filter_requests( $vars ) {

		global $pagenow;

		// used for passing values to the callback function triggered by posts_where filter.
		global $filter_values;

		// continue?
		if ( 'edit.php' != $pagenow || empty( $_REQUEST['cpac_filter'] ) || empty( $vars['post_type'] ) || $vars['post_type'] !== $this->storage_model->key )
			return $vars;

		// go through all filter requests per column
		foreach ( $_REQUEST['cpac_filter'] as $name => $value ) {

			if ( ! $value )
				continue;

			// get column
			if ( ! $column = $this->storage_model->get_column_by_name( $name ) )
				continue;

			// add the value to the global var so we can use it in the 'post_where' callback
			$filter_values[ $column->properties->type ] = $value;

			// meta arguments
			$meta_value 		= in_array( $value, array( 'cpac_empty', 'cpac_not_empty' ) ) ? '' : $value;
			$meta_query_compare = 'cpac_not_empty' == $value ? '!=' : '=';

			switch ( $column->properties->type ) :

				// @todo: solution sql subquery?
				//case 'column-attachment' :
				//case 'column-attachment_count' :
				//	add_filter( 'posts_where', array( $this, 'posts_where_attachment' ) );
				//	break;

				case 'column-sticky' :
					add_filter( 'posts_where', array( $this, 'posts_where_sticky' ) );
					break;

				case 'column-roles' :
					$user_ids = get_users( array( 'role' => $value, 'fields' => 'id' ));
					$vars['author'] = implode( ',', $user_ids );
					break;

				case 'column-page_template' :
					$vars['meta_query'][] = array(
						'key'		=> '_wp_page_template',
						'value' 	=> $meta_value,
						'compare'	=> $meta_query_compare
					);
					break;

				case 'column-ping_status' :
					add_filter( 'posts_where', array( $this, 'posts_where_ping_status' ) );
					break;

				case 'column-post_formats' :
					$vars['tax_query'][] = array(
						'taxonomy'	=> 'post_format',
						'field'		=> 'slug',
						'terms'		=> $value
					);
					break;

				case 'column-excerpt' :
					add_filter( 'posts_where', array( $this, 'posts_where_excerpt' ) );
					break;

				case 'column-comment_count' :
					add_filter( 'posts_where', array( $this, 'posts_where_comment_count' ) );
					break;

				case 'column-before_moretag' :
					add_filter( 'posts_where', array( $this, 'posts_where_before_moretag' ) );
					break;

				case 'column-author_name' :
					add_filter( 'posts_where', array( $this, 'posts_where_author_name' ) );
					break;

				case 'column-featured_image' :
					// check for keys that dont exist
					if ( 'cpac_empty' == $value )
						$meta_query_compare = 'NOT EXISTS';

					$vars['meta_query'][] = array(
						'key'		=> '_thumbnail_id',
						'value' 	=> $meta_value,
						'compare'	=> $meta_query_compare
					);
					break;

				case 'column-comment_status' :
					add_filter( 'posts_where', array( $this, 'posts_where_comment_status' ) );
					break;

				case 'column-status' :
					$vars['post_status'] = $value;
					break;

				case 'column-taxonomy' :
					$vars['tax_query'][] = array(
						'taxonomy'	=> $column->options->taxonomy,
						'field'		=> 'slug',
						'terms'		=> $value
					);
					break;

				case 'column-meta' :
					$vars['meta_query'][] = array(
						'key'		=> $column->options->field,
						'value' 	=> $meta_value,
						'compare'	=> $meta_query_compare
					);
					break;

			endswitch;

		}

		return $vars;
	}

	/**
	 * Get values by post field
	 *
	 * @since 1.0
	 */
	function get_values_by_post_field( $post_field ) {
		global $wpdb;

		$post_field = mysql_real_escape_string( $post_field );

		$sql = "
			SELECT DISTINCT $post_field
			FROM {$wpdb->posts}
			WHERE post_type = %s
			ORDER BY 1
		";

		$values = $wpdb->get_results( $wpdb->prepare( $sql, $this->storage_model->key ), ARRAY_N );

		if ( is_wp_error( $values ) || ! $values )
			return array();

		return $values;
	}

	/**
	 * Get values by meta key
	 *
	 * @since 1.0
	 */
	function get_values_by_meta_key( $meta_key ) {
		global $wpdb;

		$sql = "
			SELECT DISTINCT meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = %s
			AND pm.meta_key = %s
			AND pm.meta_value != ''
			ORDER BY 1
		";

		$values = $wpdb->get_results( $wpdb->prepare( $sql, $this->storage_model->key, $meta_key ), ARRAY_N );

		if ( is_wp_error( $values ) || ! $values )
			return array();

		return $values;
	}

	/**
	 * Add filtering dropdown
	 *
	 * @since 1.0
	 * @todo: Add support for customfield values longer then 30 characters.
	 */
	function add_filtering_dropdown() {

		global $post_type_object, $wpdb;

		// check if posttype matches current storage_model
		if ( ! isset( $post_type_object->name ) || $post_type_object->name !== $this->storage_model->key )
			return false;

		foreach ( $this->storage_model->get_columns() as $column ) {

			// column has filtering enabled?
			if ( ! $column->properties->is_filterable || 'on' != $column->options->filter )
				continue;

			$options = array();

			// this will add an empty and non-empty option to the dropdown filter menu.
			$empty_option = false;

			// cache available?
			if( $cache = $column->get_cache( 'filtering' ) ) {

				$options  		= $cache['options'];
				$empty_option 	= $cache['empty_option'];
			}

			// no caching, go fetch :)
			else {

				switch ( $column->properties->type ) :

					// @todo
					//case 'column-attachment' :
					//case 'column-attachment_count' :
					//	$empty_option = true;
					//	break;

					case 'column-sticky' :
						$empty_option = true;
						break;

					case 'column-roles' :
						global $wp_roles;
						foreach( $wp_roles->role_names as $role => $name ) {
							$options[ $role ] = $name;
						}
						break;

					case 'column-page_template' :
						if ( $values = $this->get_values_by_meta_key( '_wp_page_template' ) ) {
							foreach ( $values as $value ) {
								$page_template = $value[0];
								if ( $label = array_search( $page_template, get_page_templates() ) ) {
									$page_template = $label;
								}
								$options[ $value[0] ] = $page_template;
							}
						}
						break;

					case 'column-ping_status' :
						if ( $values = $this->get_values_by_post_field( 'ping_status' ) ) {
							foreach ( $values as $value ) {
								$options[ $value[0] ] = $value[0];
							}
						}
						break;

					case 'column-post_formats' :
						$empty_option = true;
						$options = $this->apply_indenting_markup( $this->indent( get_terms( 'post_format', array( 'hide_empty' => false ) ), 0, 'parent', 'term_id' ) );
						break;

					case 'column-excerpt' :
						$empty_option = true;
						break;

					case 'column-comment_count' :
						$empty_option = true;
						if ( $values = $this->get_values_by_post_field( 'comment_count' ) ) {
							foreach ( $values as $value ) {
								$options[ $value[0] ] = $value[0];
							}
						}
						break;

					case 'column-before_moretag' :
						$empty_option = true;
						break;

					case 'column-author_name' :
						if ( $values = $this->get_values_by_post_field( 'post_author' ) ) {
							foreach ( $values as $value ) {
								$options[ $value[0] ] = $column->get_display_name( $value[0] );
							}
						}
						break;

					case 'column-featured_image' :
						$empty_option = true;

						if ( $values = $this->get_values_by_meta_key( '_thumbnail_id' ) ) {
							foreach ( $values as $value ) {
								$options[ $value[0] ] = $value[0];
							}
						}
						break;

					case 'column-comment_status' :
						if ( $values = $this->get_values_by_post_field( 'comment_status' ) ) {
							foreach ( $values as $value ) {
								$options[ $value[0] ] = $value[0];
							}
						}
						break;

					case 'column-status' :
						if ( $values = $this->get_values_by_post_field( 'post_status' ) ) {
							foreach ( $values as $value ) {
								if ( 'auto-draft' != $value[0] ) {
									$options[ $value[0] ] = $value[0];
								}
							}
						}
						break;

					case 'column-taxonomy' :
						if ( $column->options->taxonomy ) {
							$options = $this->apply_indenting_markup( $this->indent( get_terms( $column->options->taxonomy ), 0, 'parent', 'term_id' ) );
						}
						break;

					case 'column-meta' :
						$empty_option = true;

						if ( $values = $this->get_values_by_meta_key( $column->options->field ) ) {

							foreach ( $values as $value ) {

								$field_value = $value[0];

								// is serialized?
								if ( is_serialized( $field_value ) )
									continue;

								// string longer then 30 characters will not be sorted.
								if ( strlen( $field_value ) > 30 )
									continue;

								switch ( $column->options->field_type ) :

									case "date" :
									case "user_by_id" :
									case "title_by_id" :
										$field_value = $column->get_value_by_meta( $field_value );
										break;

									case "checkmark" :
										if ( $field_value = $column->get_value_by_meta( $field_value ) )
											$field_value = '1';
										break;

								endswitch;

								// @todo: sort values by alpabet, date or numeric
								$options[ $value[0] ] = $field_value;
							}
						}
						break;

				endswitch;

				// update cache
				$column->set_cache( 'filtering', array( 'options' => $options, 'empty_option' =>  $empty_option ) );
			}

			if ( ! $options && ! $empty_option )
				continue;

			$this->dropdown( $column->properties->name, $options, $column->options->label, $empty_option );
		}
	}
}