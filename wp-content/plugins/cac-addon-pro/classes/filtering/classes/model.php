<?php

/**
 * Addon class
 *
 * @since 1.0
 */
abstract class CAC_Filtering_Model {

	protected $storage_model;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct( $storage_model ) {

		$this->storage_model = $storage_model;
	}

	/**
	 * Clear Cache
	 *
	 * @since 1.0
	 */
	function clear_cache() {
		foreach( $this->storage_model->get_columns() as $column ) {
			$column->delete_cache( 'filtering' );
		}
	}

	/**
	 * Indents any object as long as it has a unique id and that of its parent.
	 *
	 * @since 1.0
	 *
	 * @param type $array
	 * @param type $parentId
	 * @param type $parentKey
	 * @param type $selfKey
	 * @param type $childrenKey
	 * @return array Indented Array
	 */
	protected function indent( $array, $parentId = 0, $parentKey = 'post_parent', $selfKey = 'ID', $childrenKey = 'children' ) {
		$indent = array();

        // clean counter
        $i = 0;

		foreach( $array as $v ) {

			if ( $v->$parentKey == $parentId ) {
				$indent[$i] = $v;
				$indent[$i]->$childrenKey = $this->indent( $array, $v->$selfKey, $parentKey, $selfKey );

                $i++;
			}
		}

		return $indent;
	}

	/**
	 * Applies indenting markup for taxonomy dropdown
	 *
	 * @since 1.0
	 *
	 * @param array $array
	 * @param int $level
	 * @param array $ouput
	 * @return array Output
	 */
	protected function apply_indenting_markup( $array, $level = 0, $output = array() ) {
        foreach( $array as $v ) {

            $prefix = '';
            for( $i=0; $i<$level; $i++ ) {
                $prefix .= '&nbsp;&nbsp;';
            }

            $output[$v->slug] = $prefix . $v->name;

            if ( ! empty( $v->children ) ) {
                $output = $this->apply_indenting_markup( $v->children, ( $level + 1 ), $output );
            }
        }

        return $output;
    }

	/**
	 * Create dropdown
	 *
	 * @since 1.0
	 *
	 * @param string $name Attribute Name
	 * @param string $label Label
	 * @param array $options Array with options
	 * @param string $selected Current item
	 * @param bool $add_empty_option Add two options for filtering on 'EMPTY' and 'NON EMPTY' values
	 * @return string Dropdown HTML select element
	 */
	protected function dropdown( $name, $options = array(), $view_all_label = '', $add_empty_option = false ) {

		if ( ! $name || ( empty( $options ) && ! $add_empty_option ) )
			return false;

		$current = isset( $_GET['cpac_filter'] ) && isset( $_GET['cpac_filter'][ $name ] ) ? $_GET['cpac_filter'][ $name ] : '';

		?>

		<select class="postform" name="cpac_filter[<?php echo $name; ?>]">
			<option value=""><?php _e( 'All', CAC_FC_TEXTDOMAIN ); ?> <?php echo $view_all_label; ?></option>
			<?php if ( $add_empty_option ) : ?>
			<option value="cpac_empty" <?php selected( 'cpac_empty', $current ); ?>><?php _e( 'Empty', CAC_FC_TEXTDOMAIN ); ?></option>
			<option value="cpac_not_empty" <?php selected( 'cpac_not_empty', $current ); ?>><?php _e( 'Not empty', CAC_FC_TEXTDOMAIN ); ?></option>
			<?php endif; ?>
			<?php foreach( $options as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $current ); ?>><?php echo $label; ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}