<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:
 
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'bootstrap','font-ionicons','animate-elements','style','style','dynamic-main','widget_cart_search_scripts' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css' );

// END ENQUEUE PARENT ACTION


// Include forms file for Gravity Forms
include_once(get_stylesheet_directory() . '/lib/forms.php');

add_action( 'wp_enqueue_scripts', 'bspf_add_stylesheet' );
function bspf_add_stylesheet() {
  wp_enqueue_style( 'bspf', get_stylesheet_directory_uri() . '/css/bspf.css', false, '1.0', 'all' );
}

add_action( 'wp_enqueue_scripts', 'bspf_add_script' );
function bspf_add_script() {
  wp_enqueue_script("jquery-effects-core");
  wp_enqueue_script( 'bspf', get_stylesheet_directory_uri() . '/js/bspf.js', array('jquery') );
}

function bspf_change_image_name($filename, $alttext, $galleryid) {
  // This is only a condition that works for the general galleries and not
  // for the final galleries.
  if ($galleryid != 229 && $galleryid != 228) return $alttext;
  
  $filename_new = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
  if ($filename_new != $alttext) return esc_attr($alttext);
  $alttext = preg_replace('/[0-9]+/', '', $alttext);
  return esc_attr(ucwords(str_replace(array('-', '_', '.', 'jpg', 'JPG'), array(' ', ' ', '', '', ''), $alttext)));
}

function bspf_change_image_name_gallery($title) {
  $pos = strpos($title, '_', 5);
  $name = substr($title, $pos);
  return ucwords(str_replace('_', ' ', $name));
}
