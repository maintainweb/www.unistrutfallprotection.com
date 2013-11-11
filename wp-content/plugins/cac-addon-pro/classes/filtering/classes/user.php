<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Filtering_Model_User extends CAC_Filtering_Model {

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
		add_action( 'pre_user_query', array( $this, 'handle_filter_requests'), 2 );

		// add dropdowns
		add_action( 'restrict_manage_users', array( $this, 'add_filtering_dropdown' ) );

		// clear cache
		add_action( 'profile_update', array( $this, 'clear_cache' ) );
	}

	/**
	 * Enable filtering
	 *
	 * @since 1.0
	 */
	function enable_filtering( $columns ) {

		$include_types = array(

			// WP default columns


			// Custom columns
			'column-user_commentcount',

		);

		foreach ( $columns as $column ) {
			if( in_array( $column->properties->type, $include_types ) ) {

				$column->set_properties( 'is_filterable', true );
			}
		}
	}

	/**
	 * Add filtering dropdown
	 *
	 * @since 1.0
	 * @todo: Add support for customfield values longer then 30 characters.
	 */
	function add_filtering_dropdown() {

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

					case 'column-user_commentcount' :

						//
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

	/**
	 * Handle filter request
	 *
	 * @since 1.0
	 */
	public function handle_filter_requests( $user_query ) {

		if ( empty( $_REQUEST['cpac_filter'] ) )
			return $user_query;

		// go through all filter requests per column
		foreach ( $_REQUEST['cpac_filter'] as $name => $value ) {

			if ( ! $value )
				continue;

			// get column
			if ( ! $column = $this->storage_model->get_column_by_name( $name ) )
				continue;

			switch ( $column->properties->type ) :

				case 'column-comment_count' :
						// @todo
					break;

			endswitch;

		}

		//$user_query->query_vars = array_merge( $user_query->query_vars, $vars );

		return $user_query;
	}


}