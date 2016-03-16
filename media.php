<?php

function cms_with_tms_add_stylesheets()
{
  if(!wp_style_is('cms_with_tms', 'registered') ) {
    wp_register_style("cms_with_tms", CMS_WITH_TMS_PLUGIN_URL . "/media/css/style.css");
  }

  if (did_action('wp_print_styles')) {
    wp_print_styles('cms_with_tms');
	} else {
		wp_enqueue_style("cms_with_tms");
	}
}
add_action('login_head', 'cms_with_tms_add_stylesheets');
add_action('wp_head', 'cms_with_tms_add_stylesheets');
add_action('admin_head', 'cms_with_tms_add_stylesheets');


function cms_with_tms_add_javascripts()
{
  if(!wp_script_is('cms_with_tms', 'registered') ) {
    wp_register_script("cms_with_tms", CMS_WITH_TMS_PLUGIN_URL . "/media/js/cms_with_tms.js");
  }

  // commented out check below as then the JS files are just not emitted, not sure why
//  if (did_action('wp_print_scripts')) {    
    wp_print_scripts("cms_with_tms");
//	} else {
//    wp_enqueue_script("cms_with_tms");
//  }
}
add_action('login_head', 'cms_with_tms_add_javascripts');
add_action('wp_head', 'cms_with_tms_add_javascripts');
add_action('admin_head', 'cms_with_tms_add_javascripts');

?>
