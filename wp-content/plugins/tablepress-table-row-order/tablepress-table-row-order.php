<?php
/*
Plugin Name: TablePress Extension: Table Row Order
Plugin URI: http://tablepress.org/extensions/table-row-order/
Description: Custom Extension for TablePress that allows to change the row order with a Shortcode parameter
Version: 1.2
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

// Usage:
// [table id=123 row_order=random /]
// [table id=123 row_order=reverse /]
// [table id=123 row_order=sort row_order_sort_column=A row_order_sort_direction=DESC /]
// [table id=123 row_order=manual row_order_manual_order=1-3,6,3 /]

// Prohibit direct script loading
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

/**
 * Init TablePress_Row_Order
 */
add_action( 'tablepress_run', array( 'TablePress_Row_Order', 'init' ) );

/**
 * Class that contains the TablePress Row Order functionality
 * @author Tobias Bäthge
 * @since 1.1
 */
class TablePress_Row_Order {

	/**
	 * Column number that shall be sorted on
	 *
	 * @since 1.1
	 *
	 * @var int
	 */
	protected static $sort_column = false;

	/**
	 * Register necessary plugin filter hooks
	 *
	 * @since 1.1
	 */
	public static function init() {
		// Only do something on frontend
		if ( is_admin() )
			return;

		add_filter( 'tablepress_shortcode_table_default_shortcode_atts', array( __CLASS__, 'shortcode_attributes' ) );
		add_filter( 'tablepress_table_render_options', array( __CLASS__, 'turn_off_caching' ), 10, 2 );
		add_filter( 'tablepress_table_evaluate_data', array( __CLASS__, 'row_order_after_evaluate_processing' ), 10, 3 );
		add_filter( 'tablepress_table_render_data', array( __CLASS__, 'row_order_after_render_processing' ), 10, 3 );
	}

	/**
	 * Add the Extension's parameters as valid [[table /]] Shortcode attributes
	 *
	 * @since 1.1
	 *
	 * @param array $default_atts Default attributes for the TablePress [[table /]] Shortcode
	 * @return array Extended attributes for the Shortcode
	 */
	public static function shortcode_attributes( $default_atts ) {
		$default_atts['row_order'] = 'default';
		$default_atts['row_order_sort_column'] = false;
		$default_atts['row_order_sort_direction'] = 'ASC';
		$default_atts['row_order_manual_order'] = 'all';
		return $default_atts;
	}

	/*
	 * Deactivate Table Output caching, if row order is changed
	 *
	 * @since 1.1
	 *
	 */
	public function turn_off_caching( $render_options, $table ) {
		if ( 'random' == $render_options['row_order'] )
			$render_options['cache_table_output'] = false;

		return $render_options;
	}

	/*
	 * Sort function
	 *
	 * @since 1.1
	 *
	 */
	public function compare_rows( $a, $b ) {
		return strnatcasecmp( $a[ self::$sort_column ], $b[ self::$sort_column ] );
    }

	/*
	 * Change the order of the rows, for "sort" order
	 *
	 * @since 1.1
	 *
	 */
	public function row_order_after_evaluate_processing( $table, $orig_table, $render_options ) {

		switch ( $render_options['row_order'] ) {
			case 'sort':
				$array_to_sort = $table['data'];
				if ( $render_options['table_head'] )
					$first_row = array_shift( $array_to_sort );
				if ( $render_options['table_foot'] )
					$last_row = array_pop( $array_to_sort );

				if ( false !== $render_options['row_order_sort_column'] ) {
					if ( ! is_numeric( $render_options['row_order_sort_column'] ) )
						$render_options['row_order_sort_column'] = TablePress::letter_to_number( $render_options['row_order_sort_column'] );
					self::$sort_column = $render_options['row_order_sort_column'] - 1;
					usort( $array_to_sort, array( __CLASS__, 'compare_rows' ) );
					if ( 'DESC' == $render_options['row_order_sort_direction'] )
						$array_to_sort = array_reverse( $array_to_sort );
				}

				if ( $render_options['table_head'] )
					array_unshift( $array_to_sort, $first_row );
				if ( $render_options['table_foot'] )
					array_push( $array_to_sort, $last_row );
				$table['data'] = $array_to_sort;
				break;

			case 'default':
			default:
				break;
		}

		return $table;
	}

	/*
	 * Change the order of the rows, for "random", "reverse", and "manual" order
	 *
	 * @since 1.1
	 *
	 */
	public function row_order_after_render_processing( $table, $orig_table, $render_options ) {
		if ( 'default' == $render_options['row_order'] )
			return $table;

		switch ( $render_options['row_order'] ) {
			case 'random':
				$array_to_shuffle = $table['data'];
				if ( $render_options['table_head'] )
					$first_row = array_shift( $array_to_shuffle );
				if ( $render_options['table_foot'] )
					$last_row = array_pop( $array_to_shuffle );
				shuffle( $array_to_shuffle );
				if ( $render_options['table_head'] )
					array_unshift( $array_to_shuffle, $first_row );
				if ( $render_options['table_foot'] )
					array_push( $array_to_shuffle, $last_row );
				$table['data'] = $array_to_shuffle;
				break;

			case 'reverse':
				$array_to_reverse = $table['data'];
				if ( $render_options['table_head'] )
					$first_row = array_shift( $array_to_reverse );
				if ( $render_options['table_foot'] )
					$last_row = array_pop( $array_to_reverse );
				$array_to_reverse = array_reverse( $array_to_reverse );
				if ( $render_options['table_head'] )
					array_unshift( $array_to_reverse, $first_row );
				if ( $render_options['table_foot'] )
					array_push( $array_to_reverse, $last_row );
				$table['data'] = $array_to_reverse;
				break;

			case 'manual':
				$num_rows = count( $table['data'] );
				$row_order_orig = $render_options['row_order_manual_order'];
				// we have a list of rows (possibly with ranges in it)
				$row_order_orig = explode( ',', $row_order_orig );
				$row_order = array();
				foreach ( $row_order_orig as $key => $value ) {
					// add all columns to array if "all" value set for the columns parameter
					if ( 'all' == $value )
						$value = '1-' . $num_rows;
					else if ( 'reverse' == $value )
						$value = $num_rows . '-1';
					$range_dash = strpos( $value, '-' );
					if ( false !== $range_dash ) {
						// Range
						$start = substr( $value, 0, $range_dash );
						$end = substr( $value, $range_dash + 1 );
						$value = range( $start, $end );
					} else {
						// No range
						$value = array( $value );
					}
					$row_order = array_merge( $row_order, $value );
				}
				// Build new table
				$table_data = array();
				foreach ( $row_order as $idx => $row_number ) {
					$row_number = absint( $row_number ) - 1; // Convert numbers to indices
					if ( isset( $table['data'][ $row_number ] ) )
						$table_data[] = $table['data'][ $row_number ];
				}
				$table['data'] = $table_data;
				break;

			default:
				break;
		}

		return $table;
	}

} // class TablePress_Row_Order