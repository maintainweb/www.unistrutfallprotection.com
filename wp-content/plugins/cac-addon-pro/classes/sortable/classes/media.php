<?php

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Sortable_Model_Media extends CAC_Sortable_Model {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {
		parent::__construct( $storage_model );

		// handle sorting request
		add_filter( 'request', array( $this, 'handle_sorting_request'), 1 );

		// register sortable headings
		add_filter( "manage_upload_sortable_columns", array( $this, 'add_sortable_headings' ) );

		// add reset button
		add_action( 'restrict_manage_posts', array( $this, 'add_reset_button' ) );
	}

	/**
	 * @see CAC_Sortable_Model::get_sortables()
	 * @since 1.0
	 */
	function get_sortables() {

		$column_names = array(

			// WP default columns

			// Custom Columns
			'column-mediaid',
			'column-width',
			'column-height',
			'column-dimensions',
			'column-caption',
			'column-description',
			'column-mime_type',
			'column-file_name',
			'column-alternate_text',
			'column-file_size',
			'column-available_sizes',
			'column-meta',
			'column-exif_data',
		);

		return $column_names;
	}

	/**
	 * Get attachments
	 *
	 * @since 1.0
	 *
	 * @param string $post_type
	 * @return array Posts
	 */
	public function get_attachments() {

		$any_posts = (array) get_posts( array(
			'numberposts'	=> -1,
			'post_status'	=> 'any',
			'post_type'		=> 'attachment',
			'fields'		=> 'ids'
		));

		// trash posts are not included in the posts_status 'any' by default
		$trash_posts = (array) get_posts( array(
			'numberposts'	=> -1,
			'post_status'	=> 'trash',
			'post_type'		=> 'attachment',
			'fields'		=> 'ids'
		));

		return array_unique( array_merge( $any_posts, $trash_posts ) );
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
	public function handle_sorting_request( $vars ) {

		global $pagenow;

		// only trigger on upload page
		if ( 'upload.php' != $pagenow )
			return $vars;

		// apply sorting preference
		$this->apply_sorting_preference( $vars );

		// no sorting
		if ( empty( $vars['orderby'] ) )
			return $vars;

		// Column
		$column = $this->get_column_by_orderby( $vars['orderby'] );

		if ( empty( $column ) )
			return $vars;

		// unsorted Attachments
		$posts = array();

		switch ( $column->properties->type ) :

			// WP Default Columns

			// Custom Columns
			case 'column-mediaid' :
				$vars['orderby'] = 'ID';
				break;

			case 'column-width' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_attachments() as $id ) {
					$meta 	= wp_get_attachment_metadata( $id );
					$width 	= ! empty( $meta['width'] ) ? $meta['width'] : 0;
					if ( $width )
						$posts[ $id ] = $width;
				}
				break;

			case 'column-height' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_attachments() as $id ) {
					$meta 	= wp_get_attachment_metadata( $id );
					$height = ! empty( $meta['height'] ) ? $meta['height'] : 0;
					if ( $height )
						$posts[ $id ] = $height;
				}
				break;

			case 'column-dimensions' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_attachments() as $id ) {
					$meta 	= wp_get_attachment_metadata( $id );
					$height = ! empty( $meta['height'] ) ? $meta['height'] : 0;
					$width 	= ! empty( $meta['width'] ) ? $meta['width'] : 0;
					$surface = $height * $width;

					if ( $surface )
						$posts[ $id ] = $surface;
				}
				break;

			case 'column-caption' :
				$sort_flag = SORT_STRING;
				break;

			case 'column-description' :
				$sort_flag = SORT_STRING;
				break;

			case 'column-mime_type' :
				$sort_flag = SORT_STRING;
				break;

			case 'column-file_name' :
				$sort_flag = SORT_STRING;
				foreach ( $this->get_attachments() as $id ) {
					$meta 	= get_post_meta( $id, '_wp_attached_file', true );
					$file	= ! empty( $meta ) ? strtolower( basename( $meta ) ) : '';
					if ( $file ) {
						$posts[ $id ] = $file;
					}
				}
				break;

			case 'column-alternate_text' :
				$sort_flag = SORT_STRING;
				break;

			case 'column-file_size' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_attachments() as $id ) {
					$file = wp_get_attachment_url( $id );
					if ( $file ) {
						$abs			= str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );
						$posts[ $id ] 	= $this->prepare_sort_string_value( filesize( $abs ) );
					}
				}
				break;

			case 'column-available_sizes' :
				$sort_flag = SORT_NUMERIC;
				foreach ( $this->get_attachments() as $id ) {
					$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
					if ( isset( $meta['sizes'] ) )
						$posts[ $id ] = count( array_intersect( array_keys( $meta['sizes'] ), get_intermediate_image_sizes() ) );
				}
				break;

			case 'column-meta' :
				$field_type = 'meta_value';
				if ( in_array( $column->options->field_type, array( 'numeric', 'library_id') ) )
					$field_type = 'meta_value_num';

				$vars = array_merge( $vars, array(
					'meta_key' 	=> $column->options->field,
					'orderby' 	=> $field_type
				));
				break;

			case 'column-exif_data' :
				$sort_flag = SORT_STRING;
				break;

		endswitch;

		// we will add the sorted post ids to vars['post__in'] and remove unused vars
		if ( isset( $sort_flag ) ) {

			// orderby column value
			if ( ! $posts ) {
				foreach ( $this->get_attachments() as $id ) {
					$posts[ $id ] = $this->prepare_sort_string_value( $column->get_value( $id ) );
				}
			}

			// set post__in vars
			$vars = $this->get_vars_post__in( $vars, $posts, $sort_flag );
		}

		return $vars;

	}
}