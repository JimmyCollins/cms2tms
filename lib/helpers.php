<?php

function gs_display_messages($gs_messages)
{  
  if(isset($gs_messages)) {
    if(is_array($gs_messages)) {
      if(!empty($gs_messages)) {
        echo "<div class='notice'>";
        foreach($gs_messages as $msg) {
          echo "<p>$msg</p>";
        }
        echo "</div>";
      }
    } elseif ($gs_messages != '') {
      echo "<div class='notice'>$gs_messages</div>";
    }
  } 
}

function gs_get_mapped_locales()
{
  global $wpdb;

  // exclude default language
  $def_language =  get_option("cms_with_tms_def_lang");
  
  $gs_locales = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gs_languages where
    gs_lang is not NULL and gs_lang <> '' and cwt_lang <> '%s'", $def_language));

  return $gs_locales;
}

function gs_get_jobs()
{
  global $wpdb;

  $gs_jobs_table_name = $wpdb->prefix . 'gs_jobs';
  $gs_jobs = $wpdb->get_results("SELECT * FROM $gs_jobs_table_name order by id desc");

  return $gs_jobs;
}

function gs_get_jobs_in_progress($gs_jobs = null)
{
  if(is_null($gs_jobs)) {
    $gs_jobs = gs_get_jobs();
  }

  $cwt_jobs_in_progress = array();
  foreach($gs_jobs as $gs_job) {
    $cwt_jobs_in_progress []= $gs_job->cwt_job_id;
  }

  return $cwt_jobs_in_progress;
}

function gs_get_gs_job_by_cwt_job($cwt_job_id)
{
  global $wpdb;

  $gs_jobs_table_name = $wpdb->prefix . 'gs_jobs';

  return $wpdb->get_row($wpdb->prepare("select * from $gs_jobs_table_name where cwt_job_id = %d", $cwt_job_id));
}

function gs_starts_with($haystack,$needle,$case=true) {
    if($case){return (strcmp(substr($haystack, 0, strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, 0, strlen($needle)),$needle)===0);
}

function gs_ends_with($haystack,$needle,$case=true) {
    if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

function gs_save_language_mappings()
{
  global $wpdb;

  $cwt_lang_codes = gs_get_cwt_lang_codes();
  
  foreach($cwt_lang_codes as $cwt_lang_code) {
    $gs_locale = isset($_POST['lang_mapping_' . $cwt_lang_code])? $_POST['lang_mapping_' . $cwt_lang_code] : '';
    if($gs_locale != '') {
      if($wpdb->get_var($wpdb->prepare("select cwt_lang from {$wpdb->prefix}gs_languages where cwt_lang = %s", $cwt_lang_code))) {
        $wpdb->update($wpdb->prefix . 'gs_languages', array('gs_lang' => $gs_locale), array('cwt_lang' => $cwt_lang_code));
      } else {
        $wpdb->insert($wpdb->prefix . 'gs_languages', array('gs_lang' => $gs_locale, 'cwt_lang' => $cwt_lang_code));
      }
    }
  }
}

function gs_get_gs_locale_by_cwt_lang($cwt_lang)
{
  global $wpdb;

  return $wpdb->get_var($wpdb->prepare("select gs_lang from {$wpdb->prefix}gs_languages where cwt_lang = %s", $cwt_lang));
}

function gs_get_cwt_lang_by_gs_locale($gs_locale)
{
  global $wpdb;
  
  return $wpdb->get_var($wpdb->prepare("select cwt_lang from {$wpdb->prefix}gs_languages where gs_lang = %s", $gs_locale));
}

function cmswithtms_send_autoconfig_email($to)
{
  $base_url = get_bloginfo('url');
  $site_name = get_bloginfo('name');
  $site_email = get_bloginfo('admin_email');

  $message = "Site Name: {$site_name}\nSite URL: {$base_url}\nAdmin Email: {$site_email}";
  $message = wordwrap($message, 70);
  mail($to, 'GlobalSight autoconfig notification (Wordpress)', $message);
}


?>
