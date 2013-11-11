<?php 
/*
Plugin Name: Gravity Forms multilingual
Plugin URI: http://wpml.org/documentation/related-projects/gravity-forms-multilingual/
Description: Add multilingual support for Gravity Forms
Author: ICanLocalize
Author URI: http://wpml.org
Version: 0.9.3
*/

if(defined('GRAVITYFORMS_MULTILINGUAL_VERSION')) return;

define('GRAVITYFORMS_MULTILINGUAL_VERSION', '0.9.3');
define('GRAVITYFORMS_MULTILINGUAL_PATH', dirname(__FILE__));

require GRAVITYFORMS_MULTILINGUAL_PATH . '/inc/gravity_forms_multilingual.class.php';

$gravity_forms_multilingual = new Gravity_forms_multilingual();
?>
