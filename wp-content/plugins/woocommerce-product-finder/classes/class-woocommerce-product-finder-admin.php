<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce Product Finder Admin class
 */
class WooCommerce_Product_Finder_Admin {

	public function settings( $settings ) {
		global $woocommerce;

		$settings[] = array( 'name' => __( 'Product Finder', 'woocommerce' ), 'type' => 'title', 'desc' => __( 'Select which product criteria (category/attributes) will be included in your site\'s Product Finder and which one will be selected by default.' , 'woocommerce' ), 'id' => 'advanced_search' );

		$settings[] = array(
			'title' 		=> __( 'Attrbutes to be included:' , 'woocommerce' ),
			'desc' 		=> __( 'Product Category' , 'woocommerce' ),
			'tip' 		=> '',
			'id' 		=> 'advanced_search_atts_product_cat',
			'std' 		=> '0',
			'type' 		=> 'checkbox',
		);

		$default_options = array(
			'none' => __( 'None' , 'woocommerce' ),
			'product_cat' => __( 'Product Category' , 'woocommerce' )
		);

		$att_list = $woocommerce->get_attribute_taxonomies();

		if( $att_list && is_array( $att_list ) && count( $att_list ) > 0 ) {

			foreach( $att_list as $att ) {
				
				if( isset( $att->attribute_name ) && strlen( $att->attribute_name ) > 0 ) {

					$tax_name = $woocommerce->attribute_taxonomy_name( $att->attribute_name );
					$tax_label = $woocommerce->attribute_label( $tax_name );

					$settings[] = array(
						'title' 	=> '',
						'desc' 		=> $tax_label,
						'tip' 		=> '',
						'id' 		=> 'advanced_search_atts_' . $tax_name,
						'std' 		=> '0',
						'type' 		=> 'checkbox',
					);

					$default_options[ $tax_name ] = $tax_label;
					
				}
			}

		}

		$settings[] = array(
			'title' 	=> __( 'Attribute selected by default:' , 'woocommerce' ),
			'default'   => 'none',
			'id' 		=> 'advanced_search_default',
			'type' 		=> 'select',
			'desc_tip'	=> __( 'The attribute chosen here will be selected by default on the Product Finder form.', 'woocommerce' ),
			'options'	=> $default_options
		);

		$settings[] = array( 'type' => 'sectionend', 'id' => 'product_search');

		return $settings;
	}

}

add_filter( 'woocommerce_catalog_settings' , array( 'WooCommerce_Product_Finder_Admin' , 'settings' ) );