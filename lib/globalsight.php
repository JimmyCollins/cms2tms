<?php
require_once(dirname(__FILE__) . '/nusoap/nusoap.php');
require_once(GS_PLUGIN_PATH . '/lib/helpers.php');
require_once(dirname(__FILE__) . '/php-mo.php');

$gs_webservice = new nusoap_client(GS_WSDL_URL, true);
$gs_webservice->setEndpoint(get_option('globalsight-webservice-endpoint'));

function gs_download_translation_job($job_id)
{
  global $wpdb;
  global $gs_webservice;
  
  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;
      // get job status
      $table_name = $wpdb->prefix . 'gs_jobs';
      $job_name = $wpdb->get_var($wpdb->prepare("select job_name from $table_name where id = %d", $job_id));
      if (!$job_name) {
        return "";
      }
      $params = array('p_accessToken' => $access_token, 'p_jobName' => $job_name);
      $result = $gs_webservice->call('getStatus', $params);
      $xml = new SimpleXMLElement($result);
      $status = $xml->status;
      // update status in DB
      $wpdb->update($table_name, array('status' => $status), array('id' => $job_id));
      if($status != 'EXPORTED') {
        return "Job is currently in the $status status. Please try downloading later.";
      }


      /*

      $params = array('p_accessToken' => $access_token, 'p_jobName' => $job_name);
      $result = $gs_webservice->call("getLocalizedDocuments", $params);
      $xml = new SimpleXMLElement($result);
      $download_url_prefix = $xml->urlPrefix;
      $target_locale = $xml->targetLocale;
      $translated_post_title = file_get_contents($download_url_prefix . '/' . $target_locale . '/webservice/' . $job_id . '/post_title.txt');
      $translated_post_content = file_get_contents($download_url_prefix . '/' . $target_locale . '/webservice/' . $job_id . '/post_content.txt');

      */

      // Modfied to use getJobExportFiles as opposed to getLocalizedDocuments as in original implementation above

      $params = array('p_accessToken' => $access_token, 'p_jobName' => $job_name);
      $result = $gs_webservice->call("getJobExportFiles", $params);
      $xml = new SimpleXMLElement($result);
      $download_url_prefix = $xml->root;

      $content = $xml->{'paths'}[0];
      $title = $xml->{'paths'}{1};

      //echo 'Title URL: ' . $download_url_prefix . '/' . $title;
      //echo 'Content URL: ' . $download_url_prefix . '/' . $content;

      $translated_post_title = file_get_contents($download_url_prefix . '/' . $title);
      $translated_post_content = file_get_contents($download_url_prefix . '/' . $content);

      

      
      $wpdb->update($table_name, array('status' => 'COMPLETE'), array('id' => $job_id));
      $cwt_job_id = $wpdb->get_var($wpdb->prepare("select cwt_job_id from $table_name where id = %d", $job_id));
      $table_name = $wpdb->prefix . 'posts';

      $wpdb->update($table_name, array('post_content' => str_replace("<br/>", "\n", $translated_post_content), 'post_title' => $translated_post_title, 'post_status' => get_option('globalsight-download-status','draft')), array('id' => $cwt_job_id));

      return "Job translation was successfully downloaded";
    }
  }
}

function gs_download_theme_translation_job($locale)
{
  global $wpdb;
  global $gs_webservice;

  
  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;
      // get job status
      $theme_jobs = get_option('globalsight-theme-jobs', array());

      if(!array_key_exists($locale, $theme_jobs)) {
        return "Job does not exist: " . $locale;
      }
      
      $job_name = $theme_jobs[$locale]['job_name'];
      $params = array('p_accessToken' => $access_token, 'p_jobName' => $job_name);
      $result = $gs_webservice->call('getStatus', $params);
      $xml = new SimpleXMLElement($result);
      $status = (string)$xml->status;

      // update status
      $theme_jobs[$locale]['status'] = $status;
      update_option('globalsight-theme-jobs', $theme_jobs);


      if($status != 'EXPORTED') {
        return "Job is currently in the $status status: " . $locale;
      }
      
      $theme_jobs[$locale]['status'] = 'COMPLETE';
      update_option('globalsight-theme-jobs', $theme_jobs);

      $params = array('p_accessToken' => $access_token, 'p_jobName' => $job_name);
      $result = $gs_webservice->call("getLocalizedDocuments", $params);
      $xml = new SimpleXMLElement($result);
      $download_url_prefix = $xml->urlPrefix;
      $target_locale = $xml->targetLocale;

      $theme_dir = get_stylesheet_directory();
      $pot_file_name = basename($theme_dir);
      $translated_content = file_get_contents($download_url_prefix . '/' . $target_locale . '/webservice/' . $job_name . '/' . $pot_file_name . '.pot');

      $tmp_po_file = tempnam("/tmp", 'wp') . '.po';
      $tmp_mo_file = tempnam("/tmp", 'wp') . '.mo';
      file_put_contents($tmp_po_file, $translated_content);
      phpmo_convert($tmp_po_file, $tmp_mo_file);

      $languages_dir = $theme_dir . '/languages';
      $dest_po_file = $languages_dir . '/' . $locale . '.po';
      $dest_mo_file = $languages_dir . '/' . $locale . '.mo';
      if(is_writable($languages_dir)) {
        // move files into languages folder
        rename($tmp_po_file, $dest_po_file);
        rename($tmp_mo_file, $dest_mo_file);
      } else {
        return "Theme directory is not writable. Move $tmp_po_file to $dest_po_file and $tmp_mo_file to $dest_mo_file";
      }

      return 'Theme translated successfully: ' . $locale;
    }
  }
}

function gs_generate_job_name($cwt_job_id, $job_post, $source_lang_code)
{
  $hash = md5(site_url() . $cwt_job_id . time());
  
  if($source_lang_code == "en") {
    // use post title + hash
    $post_title = str_replace(array(" ", "\t", "\n", "\r"), "_", $job_post->post_title);
    $post_title = preg_replace("/[^A-Za-z0-9_]/", "", $post_title);
    $job_name = substr($post_title, 0, 200) . '_' . $hash;
  } else {
    $job_name = 'wp_' . $hash;
  }

  return $job_name;
}

function gs_generate_theme_job_name($locale)
{
  $hash = md5(site_url() . $locale . time());
  $job_name = "theme_" . $hash;
  
  return $job_name;
}

function gs_create_translation_job($cwt_job_id,$src_id,$from,$to)
{
  global $wpdb;
  global $gs_webservice;

  $table_name = $wpdb->prefix . 'posts';
  $job_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ID=%d",$src_id));
  
  $job_name = gs_generate_job_name($cwt_job_id, $job_post, $from);
  
  $wpdb->insert($wpdb->prefix . 'gs_jobs', array('cwt_job_id' => $cwt_job_id,
      'job_name' => $job_name, 'status' => 'INITIAL'));

  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;

      $file_profile_id = get_option('globalsight-file-profile-id');
      $target_locale = gs_get_gs_locale_by_cwt_lang($to);

      $params = array('accessToken' => $access_token, 'jobName' => $job_name,
          'filePath' => 'post_content.txt', 'fileProfileId' => $file_profile_id,
          'content' => base64_encode(str_replace("\n", "<br/>", $job_post->post_content)));
      $result = $gs_webservice->call('uploadFile', $params);

      $params = array('accessToken' => $access_token, 'jobName' => $job_name,
          'filePath' => 'post_title.txt', 'fileProfileId' => $file_profile_id,
          'content' => base64_encode($job_post->post_title));
      $result = $gs_webservice->call('uploadFile', $params);

      $params = array('accessToken' => $access_token, 'jobName' => $job_name,
          'comment' => 'WordPress GlobalSight Translation Plugin',
          'filePaths' => 'post_title.txt|post_content.txt', 'fileProfileIds' => $file_profile_id . '|'. $file_profile_id,
          'targetLocales' => $target_locale . '|' . $target_locale);
      $result = $gs_webservice->call('createJob', $params);


      return '';
    }
  }
}

function gs_create_theme_translation_job($target_locale)
{
  global $wpdb;
  global $gs_webservice;

  $job_name = gs_generate_theme_job_name($target_locale);
  $theme_dir = get_stylesheet_directory();
  $pot_file_name = basename($theme_dir) . '.pot';
  $pot_file_path = $theme_dir . '/languages/' . $pot_file_name;
  if(!file_exists($pot_file_path)) {
    return "POT file does not exist: " . $pot_file_path;
  }

  $content = file_get_contents($pot_file_path);
  
  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;

      $file_profile_id = get_option('globalsight-theme-profile-id');
      
      $params = array('accessToken' => $access_token, 'jobName' => $job_name,
          'filePath' => $pot_file_name, 'fileProfileId' => $file_profile_id,
          'content' => base64_encode($content));
      $result = $gs_webservice->call('uploadFile', $params);

      $params = array('accessToken' => $access_token, 'jobName' => $job_name,
          'comment' => 'WordPress GlobalSight Translation Plugin',
          'filePaths' => $pot_file_name, 'fileProfileIds' => $file_profile_id,
          'targetLocales' => $target_locale);
      $result = $gs_webservice->call('createJob', $params);

      $theme_jobs = get_option('globalsight-theme-jobs', array());
      $theme_jobs = array_merge($theme_jobs, array($target_locale => array('job_name' => $job_name, 'status' => 'INITIAL')));
      update_option('globalsight-theme-jobs', $theme_jobs);
      
      return '';
    }
  }
}

function gs_get_cwt_lang_codes()
{
  global $wpdb;
  $langCodes = array();
  
  $activeLangCodes = get_option("cms_with_tms_lang_codes");

  if ($activeLangCodes) {
  foreach($activeLangCodes as $lang) {
    $langCodes[] = $lang;
  }
  }

  return $langCodes;
}

function gs_get_gs_locales()
{
  global $gs_webservice;
  
  $fileProfileId = get_option('globalsight-file-profile-id');
  $locales = array();
  
  $access_token = gs_webservice_login();
  $params = array('p_accessToken' => $access_token);
  $result = $gs_webservice->call('getFileProfileInfoEx', $params);
  $profiles = simplexml_load_string($result);
  if ($profiles) {
  foreach($profiles->fileProfile as $profile) {
    if($profile->id == $fileProfileId) {
      $locales []= $profile->localeInfo->sourceLocale;
      foreach($profile->localeInfo->targetLocale as $locale) {
        $locales []= $locale;
      }
    }
  }
  }

  return $locales;
}


function gs_check_languages_supported($fileProfileId, $sourceLocale, $targetLocales)
{
  global $gs_webservice;

  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;

      $params = array('p_accessToken' => $access_token);
      $result = $gs_webservice->call('getFileProfileInfoEx', $params);

      $profiles = simplexml_load_string($result);
      $msg = '';
      
      foreach($profiles->fileProfile as $profile) {
        if($profile->id == $fileProfileId) {
          // check source locale
          if($profile->localeInfo->sourceLocale != gs_get_gs_locale_by_cwt_lang($sourceLocale)) {
            $msg .= "File profile " . $fileProfileId . " does not support the source locale or language mapping not specified: " . $sourceLocale . "<br />";
          }

          // get target locales supported by file profile
          $fileProfileTargetLocales = array();
          foreach($profile->localeInfo->targetLocale as $locale) {
            $fileProfileTargetLocales []= gs_get_cwt_lang_by_gs_locale($locale);
          }

          $not_found = array_diff($targetLocales, $fileProfileTargetLocales);
          if(!empty($not_found)) {
            $msg .= "File profile " . $fileProfileId . " does not support the following target locales or language mappings not specified: ";
            foreach($not_found as $locale) {
              $msg .= " $locale ";
            }

            return $msg;
          }

          break;
        }
      }
    }
  }

  return '';
}

function gs_webservice_login()
{
  global $gs_webservice;

  $params = array('p_username' => get_option('globalsight-username'), 'p_password' => get_option('globalsight-password'));
  return $gs_webservice->call('login', $params);
}

function gs_try_auto_language_mapping()
{
  global $gs_webservice;
  global $wpdb;
  global $sitepress_settings;
  
  $gs_user = get_userdatabylogin('globalsight');
  if(!$gs_user) {
    return; // globalsight user does not exist!
  }

  $fileProfileId = get_option('globalsight-file-profile-id');

  $result = gs_webservice_login();
  if($result->fault) {
    return "Error connecting to GlobalSight - Fault";
  } else {
    $err = $gs_webservice->getError();
    if($err) {
      return "Error connecting to GlobalSight - $err";
    } else {
      $access_token = $result;

      $params = array('p_accessToken' => $access_token);
      $result = $gs_webservice->call('getFileProfileInfoEx', $params);

      $profiles = simplexml_load_string($result);
      
      $mapping = array();
      foreach($profiles->fileProfile as $profile) {
        if($profile->id == $fileProfileId) {
          // source locale
          $fileProfileSourceLocale = $profile->localeInfo->sourceLocale;
          $cwt_source_lang = get_option("cms_with_tms_def_lang");
          if(gs_starts_with($fileProfileSourceLocale, $cwt_source_lang)) {
            $mapping['lang_mapping_' . $cwt_source_lang] = $fileProfileSourceLocale;
          }

          // get target locales supported by file profile
          $fileProfileTargetLocales = array();
          foreach($profile->localeInfo->targetLocale as $locale) {
            $fileProfileTargetLocales []= $locale;
          }

          // if each cwt_lang has only one matching gs_lang, then create the mapping automatically
          // as there is no ambiguity
          $cwt_lang_codes = gs_get_cwt_lang_codes();
          foreach($cwt_lang_codes as $cwt_lang_code) {
            $matches = gs_find_lang_matches($fileProfileTargetLocales, $cwt_lang_code);
            if(count($matches) == 1) {
              $mapping['lang_mapping_' . $cwt_lang_code] = $matches[0];
            }
          }

          break;
        }
      }

      gs_save_language_mappings($mapping);
    }
  }
}

function gs_find_lang_matches($arr, $prefix)
{
  $matches = array();
  foreach($arr as $val) {
    if(gs_starts_with($val, $prefix)) {
      $matches []= $val;
    }
  }
  return $matches;
}

function gs_verify_config_settings($skip_languages = false)
{
  global $gs_webservice;
  global $wpdb;
  
  $messages = array();
  
  $gs_endpoint_url = get_option('globalsight-webservice-endpoint');
  $gs_username = get_option('globalsight-username');
  $gs_file_profile_id = get_option('globalsight-file-profile-id');
  
  if($gs_endpoint_url == '') {
    $messages []= 'Please configure CMSwithTMS endpoint URL';
  } else {
    if($gs_username == '') {
      $messages []= 'Please enter username and password';
    } else {
      if($gs_file_profile_id == '') {
        $messages []= 'Please configure file profile ID';
      } else {
        $result = gs_webservice_login();
        if($result->fault) {
          $messages []= "Error connecting to GlobalSight - Fault";
        } else {
          $err = $gs_webservice->getError();
          if($err) {
            $messages []= "Error connecting to GlobalSight - $err";
          } else {
            if(!$skip_languages) {
            /*
              global $sitepress_settings;
              $native_lang = $sitepress_settings['icl_lso_native_lang'];
              $sourceLocale = $wpdb->get_var($wpdb->prepare("select code from {$wpdb->prefix}icl_languages where id = %d", $native_lang));
              $activeLanguages = $wpdb->get_results("select code from {$wpdb->prefix}icl_languages where active = 1");
              $targetLocales = array();
              foreach($activeLanguages as $lang) {
                $targetLocales []= $lang->code;
              }

              $targetLocales = array_diff($targetLocales, array($sourceLocale));

              $msg = gs_check_languages_supported($gs_file_profile_id, $sourceLocale, $targetLocales);
              if($msg != '') {
                $messages []= $msg;
              }
            */  
            }
          }
        }
      }
    }
  }

  return $messages;
}

?>
