<?php
/*
Plugin Name: CMSwithTMS Wordpress Plugin
Plugin URI: localhost
Description: CMSwithTMS Wordpress Plugin
Version: 1.0b1
Author: Globalme
Author URI: http://github.com/app2technologies
License: Licensed to Globalme Localization Inc.
*/
?>
<?php
require_once(ABSPATH . WPINC . '/registration.php');
require_once(dirname(__FILE__) . '/constants.php' );
require_once(dirname(__FILE__) . '/media.php' );

require_once(GS_PLUGIN_PATH . '/lib/helpers.php');
require_once(GS_PLUGIN_PATH . '/lib/cron.php');
require_once(GS_PLUGIN_PATH . '/lib/globalsight.php');

register_activation_hook( __FILE__, 'cms_with_tms_install' );

function cms_with_tms_install() {
	global $cms_with_tms_db_version;
	$cms_with_tms_db_version = "1.0";

	global $wpdb;
  global $cms_with_tms_db_version;  
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');  
	
	$table_name = $wpdb->prefix . "cwt_translation_set";
 	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
    translation_setid int NOT NULL,
    src_post_id int NOT NULL,
  	src_lang_code varchar(255) NOT NULL,
  	tgt_post_id int NOT NULL,
  	tgt_lang_code varchar(255) NOT NULL
	  );";     		
	  dbDelta($sql);
  }  	 

  $table_name = $wpdb->prefix . "gs_jobs";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) { 
    $sql = "CREATE TABLE " . $table_name . " (
    id bigint(20) NOT NULL AUTO_INCREMENT,
	  cwt_job_id bigint(20) NOT NULL,
	  job_name varchar(255) NOT NULL,
	  status varchar(32) NOT NULL,
	  UNIQUE KEY id (id)
	  );";     		
	  dbDelta($sql);    
  }

  $table_name = $wpdb->prefix . "gs_languages";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) { 
    $sql = "CREATE TABLE " . $table_name . " (
    cwt_lang varchar(8),
    gs_lang varchar(8)
	  );";     		
	  dbDelta($sql);    
  }

  $table_name1 = $wpdb->prefix . "posts";
  $table_name2 = $wpdb->prefix . "postmeta";

  $preactivation_posts = $wpdb->get_results("SELECT * FROM $table_name1 WHERE ID NOT IN (SELECT post_id FROM $table_name2 WHERE meta_key='cms_with_tms_lang') AND (post_status = 'publish' OR post_status = 'future' OR post_status = 'draft' OR post_status = 'pending' OR post_status = 'private')");
  foreach ($preactivation_posts as $post) {
    update_post_meta($post->ID,'cms_with_tms_lang','');
  }  

  add_option("cms_with_tms_db_version", $cms_with_tms_db_version); 
  gs_install_cron();
}

register_deactivation_hook(__FILE__, 'cms_with_tms_plugin_uninstall');

function cms_with_tms_plugin_uninstall()
{
  
  global $wpdb;
  
  $wpdb->query("drop table {$wpdb->prefix}gs_languages");
  $wpdb->query("drop table {$wpdb->prefix}gs_jobs");
  //$wpdb->query("drop table {$wpdb->prefix}cwt_translation_set");
  
  gs_uninstall_cron();
}


function gs_register_settings()
{
  register_setting( 'globalsight-config-group', 'globalsight-configuration-type' );
  register_setting( 'globalsight-config-group', 'globalsight-webservice-endpoint' );
  register_setting( 'globalsight-config-group', 'globalsight-username' );
  register_setting( 'globalsight-config-group', 'globalsight-password' );
  register_setting( 'globalsight-config-group', 'globalsight-file-profile-id' );
  register_setting( 'globalsight-config-group', 'globalsight-theme-profile-id' );
  register_setting( 'globalsight-config-group', 'globalsight-download-status' );
}

function cms_with_tms_admin_menu() {
	add_menu_page('CMSwithTMS', 'GlobalSight', 'manage_options', 'cms-with-tms-id', 'cms_with_tms_render_admin_main');
	add_submenu_page('cms-with-tms-id','Configuration','Configuration','manage_options','cms-with-tms-id','cms_with_tms_render_admin_main');
	add_submenu_page('cms-with-tms-id','Languages','Languages','manage_options','cms-with-tms-lang-id','cms_with_tms_render_lang_settings');
	add_submenu_page('cms-with-tms-id','Jobs','Jobs','manage_options','cms-with-tms-jobs-id','cms_with_tms_render_jobs');
	add_submenu_page('cms-with-tms-id','Theme','Theme','manage_options','cms-with-tms-theme-id','cms_with_tms_render_theme_menu');
	add_action('admin_init','gs_register_settings' );
}
add_action('admin_menu', 'cms_with_tms_admin_menu');

function gs_admin_notices()
{
  global $current_screen;

  if($current_screen->parent_file == 'edit.php' || $current_screen->parent_file == 'edit.php?post_type=page') {
    $gs_messages = gs_verify_config_settings();
    gs_display_messages($gs_messages);
  }
}
add_action('admin_notices', 'gs_admin_notices');

function gs_admin_head()
{
?>
<style>
  table.with_border {
    border: 1px solid black;
  }

  table.with_border thead {
    font-weight: bold;
  }

  table.with_border td {
    border: 1px solid black;
    padding: 5px;
  }

  .notice {
    background-color: lightyellow;
    border: 1px solid yellow;
    padding: 5px;
    margin: 10px 0px;
    width: 50%;
  }
</style>
<?php
}
add_action('admin_head', 'gs_admin_head');

function cms_with_tms_render_admin_main() {  
  if(isset($_GET['updated'])) {
    global $wpdb;
    $tablename = $wpdb->prefix . "gs_languages";
    // check for auto config option and set values from json
    if (get_option('globalsight-configuration-type')=='0') {
      echo "You have chosen autoconfig option<br/>";
      $auto_config = json_decode(file_get_contents(GS_AUTOCONFIG_URL));
      print_r($auto_config);
      update_option('globalsight-webservice-endpoint',$auto_config->cmswithtms_globalsight_webservice_endpoint);      
      update_option('globalsight-username',$auto_config->cmswithtms_globalsight_username);
      update_option('globalsight-password',$auto_config->cmswithtms_globalsight_password);
      update_option('globalsight-file-profile-id',$auto_config->cmswithtms_globalsight_file_profile_id);
      //cmswithtms_send_autoconfig_email($auto_config->cmswithtms_autoconfig_email);
      cmswithtms_send_autoconfig_email('amitpphatak@gmail.com');
      $lg_code = array();
      $lg_name = array();
      foreach($auto_config->cmswithtms_lang_names as $lang_code=>$lang_name) {
        $lg_code[] = $lang_code;
        $lg_name[] = $lang_name;
      }
      update_option("cms_with_tms_lang_names",$lg_name);
		  update_option("cms_with_tms_lang_codes",$lg_code);		
      foreach($auto_config->cmswithtms_lang_mapping as $lang_code=>$gs_locale) {        
        $exists = $wpdb->get_results($wpdb->prepare("SELECT gs_lang FROM $tablename WHERE cwt_lang = '%s'", $lang_code));
        if ($exists) {
          $wpdb->update($tablename,array('gs_lang'=>$gs_locale),array('cwt_lang'=>$lang_code),array('%s'), array('%s'));
        } else {
          $wpdb->insert($tablename,array('cwt_lang'=>$lang_code,'gs_lang'=>$gs_locale),array('%s','%s'));
        }
      }
    }
    gs_try_auto_language_mapping();
  }

  $gs_messages = gs_verify_config_settings();
  ?>

  <?php 
  if ($gs_messages) { 
    gs_display_messages($gs_messages); 
  } else { 
    echo "<div class='notice'>Connected to CMSwithTMS successfully!</div>";
  } 
  ?>

  <div class="wrap">
  <h2>GlobalSight Configuration</h2>

  <form method="post" action="options.php">
    <?php settings_fields( 'globalsight-config-group' ); ?>

    <h3>Select configuration option</h3>
    <table class="form-table">
      <tr valign="top">
      <td><input type="radio" name="globalsight-configuration-type" value="0" <?php echo get_option('globalsight-configuration-type','0')=='0'?"checked":""; ?> /> Use CMSwithTMS servers<br />
      </td>
      </tr>
      
      <tr valign="top">
      <td><input type="radio" name="globalsight-configuration-type" value="1" <?php echo get_option('globalsight-configuration-type','0')=='1'?"checked":""; ?> /> Use my own GlobalSight instance<br />
      </td>
      </tr>
      
      <tr valign="top">
      <th scope="row">Webservice Endpoint URL</th>
      <td><input type="text" name="globalsight-webservice-endpoint" value="<?php echo get_option('globalsight-webservice-endpoint'); ?>" /><br />
        If you have not modified any configuration on your GlobalSight installation, this value should be <br />
        http://globalsightip:port/globalsight/services/AmbassadorWebService
      </td>
      </tr>

      <tr valign="top">
      <th scope="row">User</th>
      <td><input type="text" name="globalsight-username" value="<?php echo get_option('globalsight-username'); ?>" /></td>
      </tr>

      <tr valign="top">
      <th scope="row">Password</th>
      <td><input type="password" name="globalsight-password" value="<?php echo get_option('globalsight-password'); ?>" /></td>
      </tr>
    </table>
    <br/>

    <h3>Translation Settings</h3>
    <p>Enter the GlobalSight translation settings</p>
    <table class="form-table">
      <tr valign="top">
      <th scope="row">File Profile ID</th>
      <td><input type="text" name="globalsight-file-profile-id" value="<?php echo get_option('globalsight-file-profile-id'); ?>" /></td>
      </tr>
    </table>
    <br/>   

    <h3>Status of Downloaded Translations from GlobalSight</h3>
    <p>Set status of downloaded translations to - 
    <select name="globalsight-download-status">
      <option value="draft" <?php echo (get_option('globalsight-download-status')=="draft")?"selected=selected":"" ?>>Draft</option>
      <option value="publish" <?php echo (get_option('globalsight-download-status')=="publish")?"selected=selected":"" ?>>Publish</option>
    </select>
    </p>
    <br/>

    <h3>Theme Translation Settings</h3>
    <p>Enter the GlobalSight Theme translation settings</p>
    <table class="form-table">
      <tr valign="top">
      <th scope="row">Theme File Profile ID</th>
      <td><input type="text" name="globalsight-theme-profile-id" value="<?php echo get_option('globalsight-theme-profile-id'); ?>" /></td>
      </tr>      
      <?php      
      $language_names = get_option("cms_with_tms_lang_names");
      $language_codes = get_option("cms_with_tms_lang_codes");
      ?>
    </table>   
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
  </form>
  </div>
  <?php
}

function cms_with_tms_render_lang_settings() {
  global $wpdb;
  $language_names = array("English");
	$language_codes = array("en");
  
  if (isset($_POST['update_lang'])) {		
		update_option("cms_with_tms_lang_names",$_POST['lang_name']);
		update_option("cms_with_tms_lang_codes",$_POST['lang_code']);		
		update_option("cms_with_tms_def_lang",$_POST['default']);
	}
	
	if (get_option("cms_with_tms_lang_names")) {
	  $language_names = get_option("cms_with_tms_lang_names");
	}
	if (get_option("cms_with_tms_lang_codes")) {
	  $language_codes = get_option("cms_with_tms_lang_codes");
	}	
	$def_language = get_option("cms_with_tms_def_lang","en");
  
	if(isset($_POST['SaveMapping'])) {
    // handle save of language mappings
    gs_save_language_mappings($_POST);
  }

  $gs_messages = gs_verify_config_settings(true);
  
  if ($gs_messages) { 
    gs_display_messages($gs_messages); 
  } else { 
    echo "<div class='notice'>Connected to CMSwithTMS successfully!</div>";
  } 
  

	$cwt_lang_codes = gs_get_cwt_lang_codes();
  $gs_locales = gs_get_gs_locales();  
  
  ?>
  <div class="wrap">
	  <h2>Manage Languages</h2>
    <form method="post" action="" onSubmit="validate_manage_lang(this); return false;">   
      <table>    	
        <tbody>
        	<tr>
        	  <td>
        	    <table id="dataTable" cellpadding="5" cellspacing="5">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Default?</th>
                    <th>Remove</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $counter=0;
                foreach ($language_names as $lang_name) {
                  $output = sprintf("
                  <tr>
                    <td><input type='text' name='lang_name[]' value='%s' /></td>
                    <td><input type='text' name='lang_code[]' value='%s' /></td>
                    <td><input type='radio' name='default' value='' %s /></td>
                    <td><input type='checkbox' name='chk' /></td>
                  </tr>
                  ",$lang_name,$language_codes[$counter],($language_codes[$counter]==$def_language?"checked":""));
                  $counter += 1;
                  echo $output;
                }  
                ?>
                </tbody>
              </table>
            </td>
          </tr>
        	<tr>
            <td>
              <a href="javascript://" onclick="cms_with_tms_addRow('dataTable');">Add rows</a>, 
	            <a href="javascript://" onclick="cms_with_tms_deleteRow('dataTable');">Remove rows</a>
            </td>
          </tr>
          <tr>            	
          	<td>
              <input type="hidden" name="update_lang"/>
              <input type="submit" value="Update"/>
            </td>
          </tr>
        </tbody>
      </table>    
    </form>	

    <h2>Language Mapping to GlobalSight</h2>

    <?php if(!empty($gs_messages)) { ?>
    <p>Your GlobalSight <a href="admin.php?page=<?php echo GS_PLUGIN_FOLDER_NAME; ?>/menu/config.php">configuration</a> has errors. Please correct it before setting up languages.</p>
    <?php } ?>  

    <form action="" method="post">
    <table class='with_border'>
      <thead>
        <tr>
          <td>Language Code</td>
          <td>GlobalSight Locale</td>
        </tr>
      </thead>
      <?php foreach($cwt_lang_codes as $cwt_lang_code) { ?>
      <tr>
        <td><?php echo $cwt_lang_code; ?></td>
        <td>
          <select name="lang_mapping_<?php echo $cwt_lang_code; ?>">
            <?php
              $count = 0;
              $current_gs_lang = $wpdb->get_var($wpdb->prepare("select gs_lang from {$wpdb->prefix}gs_languages where cwt_lang = %s", $cwt_lang_code));
            ?>
            <?php foreach($gs_locales as $gs_locale) { ?>
              <?php echo "In for each"; ?>
              <?php if(gs_starts_with($gs_locale, $cwt_lang_code)) { ?>
              <option value="<?php echo $gs_locale; ?>" <?php if($current_gs_lang == $gs_locale) echo 'selected="selected"'; ?> ><?php echo $gs_locale; ?></option>
              <?php $count++; } ?>
            <?php } ?>
            <?php
              if(!$count) {
                echo "<option value=''>--NA--</option>";
              }
            ?>
          </select>
        </td>
      </tr>
      <?php } ?>
    </table>
    <p><input type="hidden" name="SaveMapping"/>
    <input type="submit" value="Save Changes"/></p>
    </form> 
    
	</div>  
	<?php
}

function cms_with_tms_render_theme_menu()
{
  $messages = array();
  switch($_POST['operation'])
  {
    case 'upload': {
      foreach($_POST['locale'] as $locale) {
        $message = gs_create_theme_translation_job($locale);
        if($message != '') {
          $messages []= $message;
        }
      }
    }
    break;

    case 'download': {
      foreach($_POST['locale'] as $locale) {
        $message = gs_download_theme_translation_job($locale);
        if($message != '') {
          $messages []= $message;
        }
      }
    }
    break;
  }

  gs_display_messages($messages);
  
  $theme_dir = get_stylesheet_directory();
  $mo_files_location = $theme_dir . "/languages";
  
  $gs_locales = gs_get_mapped_locales();
  $theme_jobs = get_option('globalsight-theme-jobs', array());
  
  ?>
<script>
function doUpload() {
  var operation = document.getElementById("operation");
  operation.value = 'upload';
  document.forms["theme_form"].submit();
}

function doDownload() {
  var operation = document.getElementById("operation");
  operation.value = 'download';
  document.forms["theme_form"].submit();
}
</script>
  <?php
  echo '<h2>Theme Localization</h2>';

  echo "<form name='theme_form' method='post' action=''>";
  echo "<table class='with_border'>";
  echo "<thead><tr>
      <td>Locale</td>
      <td>MO file</td>
      <td>Job Name</td>
      <td>GlobalSight Status</td>
      <td>Upload</td>
      <td>Download</td>
    </tr></thead>";
  foreach($gs_locales as $gs_locale) {
    $mo_exists = file_exists($mo_files_location . "/{$gs_locale->gs_lang}.mo");
    echo "<tr>";
    echo "<td>{$gs_locale->gs_lang}</td>";

    if($mo_exists) {
      echo "<td>Found</td>";
    } else {
      echo "<td>Not Found</td>";
    }

    if(array_key_exists($gs_locale->gs_lang, $theme_jobs)) {
      echo "<td>{$theme_jobs[$gs_locale->gs_lang]['job_name']}</td>";
      echo "<td>{$theme_jobs[$gs_locale->gs_lang]['status']}</td>";
    } else {
      echo "<td>NA</td>";
      echo "<td>NA</td>";
    }

    echo "<td><input type='checkbox' name='locale[]' value='{$gs_locale->gs_lang}' /></td>";
    
    if(array_key_exists($gs_locale->gs_lang, $theme_jobs)) {
      echo "<td><input type='checkbox' name='locale[]' value='{$gs_locale->gs_lang}' /></td>";
    } else {
      echo "<td>NA</td>";
    }
    
    echo "</tr>";
  }
  echo "</table>";
  
  echo "<br />";
  echo "<input type='hidden' id='operation' name='operation' value='upload' />&nbsp;";
  echo "<input type='button' value='Send for Translation' onclick='doUpload()' />&nbsp;";
  echo "<input type='button' value='Download Translation' onclick='doDownload()' />&nbsp;";
  echo "</form>";
}

function cms_with_tms_render_jobs() {

  switch($_POST['operation'])
  {
    case 'translate': {
      require_once(GS_PLUGIN_PATH . '/lib/globalsight.php');
      $gs_messages = gs_verify_config_settings();
      if(empty($gs_messages)) {
        $gs_messages = gs_create_translation_job($_POST['cwt_job_id']);
      } else {
        $gs_messages []= "Job was not sent for translation!";
      }
    }
    break;

    case 'download': {
      require_once(GS_PLUGIN_PATH . '/lib/globalsight.php');
      $gs_messages = gs_download_translation_job($_POST['gs_job_id']);
    }
    break;

    default: {

    }
    break;
  }

  gs_display_messages($gs_messages);
  
  $gs_jobs = gs_get_jobs();
  $cwt_jobs_in_progress = gs_get_jobs_in_progress($gs_jobs);
  ?>
  <div class="wrap">
  <h2>Job Management</h2>
  <h3>GlobalSight Jobs</h3>
  <?php
  if(empty($gs_jobs)) {
    echo "No jobs found!";
  } else {
    echo "<table class='with_border'>";
    echo "<thead><tr>";

    echo "<td>ID</td>";
    echo "<td>Job Name</td>";
    echo "<td>CWT Job ID</td>";
    echo "<td>Status</td>";
    echo "<td>Actions</td>";
    echo "</tr></thead>";
    foreach($gs_jobs as $gs_job) {
      echo "<tr>";
      echo "<td>$gs_job->id</td>";
      echo "<td>$gs_job->job_name</td>";
      echo "<td>$gs_job->cwt_job_id</td>";
      echo "<td>$gs_job->status</td>";

      echo "<td>";
      if($gs_job->status != 'COMPLETE') {
        echo "<form method='post' action=''>
          <input type='hidden' name='operation' value='download' />
          <input type='hidden' name='gs_job_id' value='{$gs_job->id}' />
          <input type='submit' value='Download' /></form>";

      } else {
        echo "<form method='post' action=''>
          <input type='hidden' name='operation' value='download' />
          <input type='hidden' name='gs_job_id' value='{$gs_job->id}' />
          <input type='submit' value='Refresh' /></form>";
      }
      echo "</td>";

      echo "</tr>";
    }
    echo "</table>";
  } 
  ?> 
  </div>
  <?php
}

add_filter('query_vars', 'cms_with_tms_queryvars' );

function cms_with_tms_queryvars( $qvars )
{
  $qvars[] = 'cwtpost_id';
  $qvars[] = 'cwttgtlang';
  $qvars[] = 'cwtlang';
  return $qvars;
}

add_filter( 'default_content', 'cms_with_tms_translation_content' );
add_filter( 'default_title', 'cms_with_tms_translation_title' );

function cms_with_tms_translation_title($title) {
  if (isset($_GET['cwtpost_id']) && isset($_GET['cwtsrclang']) && isset($_GET['cwttgtlang'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    $title = $wpdb->get_var($wpdb->prepare("SELECT post_title FROM $table_name WHERE ID=%d",$_GET['cwtpost_id']));
  }  
  return $title;
}

function cms_with_tms_translation_content($content) {
  if (isset($_GET['cwtpost_id']) && isset($_GET['cwtsrclang']) && isset($_GET['cwttgtlang'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    $content = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM $table_name WHERE ID=%d",$_GET['cwtpost_id']));
  }  
  return $content;
}



function cms_with_tms_add_custom_box() {
  add_meta_box('cms_with_tms_page', __("Page's language"), 'cms_with_tms_render_curr_lang', 'page', 'side', 'high');
	add_meta_box('cms_with_tms_post', __("Post's language"), 'cms_with_tms_render_curr_lang', 'post', 'side', 'high');
}
/* Prints the box content */
function cms_with_tms_render_curr_lang() {
  global $wpdb;
  $id='';
  $src_lang='';
  $language_names = get_option("cms_with_tms_lang_names");
  $language_codes = get_option("cms_with_tms_lang_codes");
  $def_language = get_option("cms_with_tms_def_lang");
  
  if (isset($_GET['cwtsrclang']) && isset($_GET['cwttgtlang']) && isset($_GET['cwtpost_id'])) {
    $lang = $_GET['cwttgtlang'];
    $src_lang = $_GET['cwtsrclang'];
    $id = $_GET['cwtpost_id'];
  } elseif (isset($_GET['post'])){
    $id = get_post_meta($_GET['post'],"cms_with_tms_src_post_id",true);
    $lang = get_post_meta($_GET['post'],"cms_with_tms_lang",true);
    $src_lang = get_post_meta($_GET['post'],"cms_with_tms_src_lang",true);
    // for posts/pages created prior to plugin activation
    if (!$lang) {
      $lang = $def_language;
    }    
  } else {
    $lang = $def_language;
  }

  $counter=0;
  foreach ($language_names as $lang_name) {
    if ($language_codes[$counter]==$lang) {
      break;
    }
    $counter += 1;
  }
  // Use nonce for verification
  wp_nonce_field( plugin_basename(__FILE__), 'cms_with_tms_noncename' );

  echo $lang_name;
  $gs_lang_exists = NULL;
  $gs_lang_exists = $wpdb->get_var($wpdb->prepare("select gs_lang from {$wpdb->prefix}gs_languages where cwt_lang = %s", $language_codes[$counter]));
  if ($language_codes[$counter] != $def_language) {
    echo "<br/><br/>";
    echo "
      <input type='checkbox' name='cms_with_tms_gs' " . (is_null($gs_lang_exists)?"disabled /> CMSwithTMS not configured for this language":" />Send to CMSwithTMS for Translation");
  }
  echo "<input type='hidden' name='cms_with_tms_src_post_id' value='$id' />";
  echo "<input type='hidden' name='cms_with_tms_src_post_lang' value='$src_lang' />";
  echo "<input type='hidden' name='cms_with_tms_lang' value='$lang' />";
}
// backwards compatible
add_action('admin_menu', 'cms_with_tms_add_custom_box',1);

function cms_with_tms_save_postdata($post_id) {

  if ( !wp_verify_nonce( $_POST['cms_with_tms_noncename'], plugin_basename(__FILE__) )) {
    return $post_id;
  }

  // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
  // to do anything
  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
    return $post_id;
  }

  // Check permissions
  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ) )
      return $post_id;
  } else {
    if ( !current_user_can( 'edit_post', $post_id ) )
      return $post_id;
  }
  
  if (isset($_POST['cms_with_tms_src_post_id']) && isset($_POST['cms_with_tms_lang']) && $_POST['cms_with_tms_src_post_id']) {
    if (db_add_translation_set($_POST['cms_with_tms_src_post_id'],$_POST['cms_with_tms_src_post_lang'],$post_id,$_POST['cms_with_tms_lang'])) {
      update_post_meta($post_id,"cms_with_tms_lang",$_POST['cms_with_tms_lang']);
      update_post_meta($post_id,"cms_with_tms_src_lang",$_POST['cms_with_tms_src_post_lang']);
      update_post_meta($post_id,"cms_with_tms_src_post_id",$_POST['cms_with_tms_src_post_id']);

      // setting src post id to src lang for posts created prior to this plugin being activated  
      if (!get_post_meta($_POST['cms_with_tms_src_post_id'],"cms_with_tms_lang",true)) {
        update_post_meta($_POST['cms_with_tms_src_post_id'],"cms_with_tms_lang",$_POST['cms_with_tms_src_post_lang']);
      }

      if (isset($_POST['cms_with_tms_gs'])) {      
        $job_id = gs_create_translation_job($post_id,$_POST['cms_with_tms_src_post_id'],$_POST['cms_with_tms_src_post_lang'],$_POST['cms_with_tms_lang']); 
        global $wpdb;
        $table_name = $wpdb->prefix . 'posts';
        $wpdb->update($table_name, array('post_status' => 'draft'), array('id' => $post_id));             
      }
    }
  } else {
    $def_language = get_option("cms_with_tms_def_lang");
    update_post_meta($post_id,"cms_with_tms_lang",$def_language);
  }
  
}

/* Do something with the data entered */
add_action('save_post', 'cms_with_tms_save_postdata');
add_action('save_page', 'cms_with_tms_save_postdata');

function cms_with_tms_add_avail_trans($posts) {
  foreach ($posts as $post) {    
    $post->post_content .= add_translated_into_lang_list($post->ID,$post->post_type);
  }
  return $posts;
}
add_filter('the_posts','cms_with_tms_add_avail_trans',10,1);
add_filter('get_pages','cms_with_tms_add_avail_trans',10,1);


/*function cms_with_tms_exclude_langs($query) {
  print_r($wp_query);
  return $query;
}

add_filter('pre_get_posts', 'cms_with_tms_exclude_langs',10,1);*/


function cms_with_tms_exclude_pages($pages) {  
  global $wpdb;
  $excluded_pages = array();
  $def_language = get_option("cms_with_tms_def_lang");
  if (isset($_GET['cwtlang']) && ($_GET['cwtlang'] != $def_language)) {
    $sql = "SELECT ID FROM ". $wpdb->prefix . "posts WHERE post_type='page' AND ID NOT IN (SELECT post_id FROM ". $wpdb->prefix . "postmeta WHERE meta_key='cms_with_tms_lang' AND meta_value=%s)";
    $rows = $wpdb->get_results($wpdb->prepare($sql,$_GET['cwtlang']));
  } else {
    $language_codes = get_option("cms_with_tms_lang_codes");
    $other_langs = "(";
    foreach ($language_codes as $language_code) {
      if ($language_code != $def_language) {
        $other_langs .= "'" . $language_code . "',"; 
      }
    }
    $other_langs = substr($other_langs,0,-1) . ")";
    $sql = "SELECT ID FROM ". $wpdb->prefix . "posts WHERE post_type='page' AND ID IN (SELECT post_id FROM ". $wpdb->prefix . "postmeta WHERE meta_key='cms_with_tms_lang' AND meta_value IN ".$other_langs.")";
    $rows = $wpdb->get_results($sql);
  }  

  foreach ($rows as $row) {
    $excluded_pages[] = $row->ID;
  }
  return $excluded_pages;
}
add_filter('wp_list_pages_excludes', 'cms_with_tms_exclude_pages');


function cms_with_tms_posts_where($where) {  
  global $wpdb;
  /*
  echo $_SERVER['SERVER_NAME'];
  echo $_SERVER['REQUEST_URI'];
  echo "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
  echo url_to_postid("http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
  */
  $table_name = $wpdb->prefix . "postmeta";
  $def_language = get_option("cms_with_tms_def_lang");
  // select all posts and pages where language is other than default language
  if (isset($_GET['cwtlang']) && ($_GET['cwtlang'] != $def_language)) {
    $where .= sprintf(" AND ID IN (SELECT post_id FROM %s WHERE meta_key='cms_with_tms_lang' AND meta_value='%s')"
    ,$table_name,$_GET['cwtlang']);
  } else { // select all posts and pages where language is default language or post/page has no language set
    $language_codes = get_option("cms_with_tms_lang_codes");
    $not_in = "(";
    foreach ($language_codes as $language_code) {
      if ($language_code != $def_language) {
        $not_in .= "'" . $language_code . "',"; 
      }
    }
    $not_in = substr($not_in,0,-1) . ")";
    $where .= sprintf(" AND ID IN (SELECT DISTINCT post_id FROM %s WHERE meta_key='cms_with_tms_lang' AND meta_value NOT IN %s)"
    ,$table_name,$not_in);
  }
  //echo $where;
  return $where;
}

add_filter('posts_where', 'cms_with_tms_posts_where');

function cms_with_tms_modify_permalink($permalink) {
  if (isset($_POST['cms_with_tms_src_post_id']) && isset($_POST['cms_with_tms_lang']) && $_POST['cms_with_tms_src_post_id']) {
    //echo "In Post";
    return $permalink;
  }
  $def_language = get_option("cms_with_tms_def_lang");
  if (isset($_GET['cwtlang']) && $_GET['cwtlang']<>$def_language) {
    $permalink .= ( (!strpos($permalink,'?')) ? '?' : '') ."&cwtlang=".$_GET['cwtlang'];
  } elseif (isset($_GET['post']) && get_post_meta($_GET['post'],"cms_with_tms_lang",true)<>$def_language) {
    $permalink .= ( (!strpos($permalink,'?')) ? '?' : '') ."&cwtlang=".get_post_meta($_GET['post'],"cms_with_tms_lang",true);
  } else {
    //$permalink .= ( (!strpos($permalink,'?')) ? '?' : '') ."&cwtlang=".$def_language;
  }
  return $permalink;
}

add_filter('post_link','cms_with_tms_modify_permalink',1);
add_filter('page_link','cms_with_tms_modify_permalink',1);
add_filter('category_link','cms_with_tms_modify_permalink',1);
add_filter('tag_link','cms_with_tms_modify_permalink',1);
add_filter('year_link','cms_with_tms_modify_permalink',1);
add_filter('month_link','cms_with_tms_modify_permalink',1);
add_filter('day_link','cms_with_tms_modify_permalink',1);
add_filter('feed_link','cms_with_tms_modify_permalink',1);
add_filter('author_link','cms_with_tms_modify_permalink',1);
/*
function cms_with_tms_modify_postlink($postlink) {
  
  return $postlink;
}

add_filter('next_post_link','cms_with_tms_modify_postlink');
add_filter('previous_post_link','cms_with_tms_modify_postlink');
*/
function cms_with_tms_modify_postlink_where($where) {
  global $wpdb;
  //echo "Before:" . $where;
  $def_language = get_option("cms_with_tms_def_lang");
  $lang = $_GET['cwtlang'];
  $tablename = $wpdb->prefix . "postmeta";
  if (isset($lang) && ($lang != $def_language)) {
    $where .= " AND ID IN (SELECT post_id from $tablename WHERE meta_key='cms_with_tms_lang' AND meta_value='$lang')";
  } else { 
    $language_codes = get_option("cms_with_tms_lang_codes");
    $not_in = "(";
    foreach ($language_codes as $language_code) {
      if ($language_code != $def_language) {
        $not_in .= "'" . $language_code . "',"; 
      }
    }
    $not_in = substr($not_in,0,-1) . ")";
    $where .= " AND ID IN (SELECT DISTINCT post_id FROM $tablename WHERE meta_key='cms_with_tms_lang' AND meta_value NOT IN $not_in)";
  }
  //echo "After:" . $where;
  return $where;
}

add_filter('get_previous_post_where','cms_with_tms_modify_postlink_where');
add_filter('get_next_post_where','cms_with_tms_modify_postlink_where');

function cms_with_tms_widget() {
  $language_names = get_option("cms_with_tms_lang_names");
  $language_codes = get_option("cms_with_tms_lang_codes");
  $def_language = get_option("cms_with_tms_def_lang");

  if (isset($_GET['cwtlang']) && $_GET['cwtlang'] != '') {
    $def_language = $_GET['cwtlang'];    
  }

  $counter=0;
  $output = "<ul>";
  foreach ($language_names as $lang_name) {
    if ($language_codes[$counter]==$def_language) {
      $output .= sprintf("
      <li><b>%s*</b></li>
      ",$lang_name);
    } else {
      $self = $_SERVER["PHP_SELF"];
      if($_SERVER["QUERY_STRING"]) {
        if (false===strpos($_SERVER["QUERY_STRING"],"cwtlang")) {
          //$finalurl = $self . "?" . $_SERVER["QUERY_STRING"] . "&cwtlang=" . $language_codes[$counter];
          $finalurl = $self . "?cwtlang=" . $language_codes[$counter];
        } else {
          $pos = strpos($_SERVER["QUERY_STRING"],"cwtlang");
          $querystr = substr_replace($_SERVER["QUERY_STRING"],$language_codes[$counter],$pos+8,2);
          //$finalurl = $self . "?" . $querystr;
          $finalurl = $self . "?cwtlang=" . $language_codes[$counter];
        }
      } else {
        $finalurl = $self . "?" . "cwtlang=" . $language_codes[$counter];
      } 
      $output .= sprintf("
      <li><a href='%s'>%s</a></li>
      ",$finalurl,$lang_name);
    }    
    $counter += 1;
  }
  $output .= "</ul>";
  
	echo"<h3 class='widget-title'>Languages</h3>" . $output;
}
 
function cms_with_tms_init_widget(){
	wp_register_sidebar_widget("CMSwithTMS","Language Switcher", "cms_with_tms_widget");     
}


add_action("plugins_loaded", "cms_with_tms_init_widget");


function cms_with_tms_column_def($column_name) {
  $column_name['translate_into'] = __('Translations');
  return $column_name;
}

function cms_with_tms_add_columns($column_name, $id) {
  if( $column_name == 'translate_into' ) {
    $languages = get_option("cms_with_tms_lang_names");
    $language_codes = get_option("cms_with_tms_lang_codes");
    $def_language = get_option("cms_with_tms_def_lang");

    if (get_post_meta($id,"cms_with_tms_lang",true)) {
      $src_lang = get_post_meta($id,"cms_with_tms_lang",true);
    } else {
      $src_lang = $def_language;
    }
    
    $counter=0;
    $post_type = get_post_or_page($id);
    foreach ($language_codes as $lang_code) {
      if ($lang_code == $src_lang) {
        $output = "";
      } else {

        ($post_type=='post')?
        $output = sprintf("
        <a href='post-new.php?cwtpost_id=%d&amp;cwtsrclang=%s&amp;cwttgtlang=%s' alt='Translate into %s' title='Translate into %s'>%s +</a><br/>
        ",$id,$src_lang,$lang_code,$languages[$counter],$languages[$counter],$languages[$counter]):
        $output = sprintf("
        <a href='post-new.php?post_type=page&amp;cwtpost_id=%d&amp;cwtsrclang=%s&amp;cwttgtlang=%s' alt='Translate into %s' title='Translate into %s'>%s +</a><br/>
        ",$id,$src_lang,$lang_code,$languages[$counter],$languages[$counter],$languages[$counter]);
        
        $exists_link = db_exists_in_translation_set($id,$src_lang,$lang_code);
        if ($exists_link) {
          $output = sprintf("
          <a href='post.php?post=%d&amp;action=edit&amp;cwtlang=%s'>%s</a><br/>
          ",$exists_link,$lang_code,$languages[$counter]);
        }
      }
      echo $output;
      $counter += 1;
    }
  }
}

add_filter('manage_posts_columns', 'cms_with_tms_column_def');
add_filter('manage_pages_columns', 'cms_with_tms_column_def');
add_action('manage_posts_custom_column', 'cms_with_tms_add_columns', 10, 2);
add_action('manage_pages_custom_column', 'cms_with_tms_add_columns', 10, 2);

function cms_with_tms_media_buttons() {
  $id=0;  
  if (isset($_GET['post'])) {
    $id=$_GET['post'];
  }
  $languages = get_option("cms_with_tms_lang_names");
  $language_codes = get_option("cms_with_tms_lang_codes");
  $def_language = get_option("cms_with_tms_def_lang");  
  
  if ($id) {

    if (get_post_meta($id,"cms_with_tms_lang",true)) {
      $src_lang = get_post_meta($id,"cms_with_tms_lang",true);
    } else {
      $src_lang = $def_language;
    }
      
    $counter=0;
    $post_type = get_post_or_page($id);
    foreach ($language_codes as $lang_code) {
      if ($lang_code == $src_lang) {
        $output = "";
      } else {

        ($post_type=='post')?
        $output = sprintf("
        <a href='post-new.php?cwtpost_id=%d&amp;cwtsrclang=%s&amp;cwttgtlang=%s' alt='Translate into %s' title='Translate into %s'>%s</a>
        ",$id,$src_lang,$lang_code,$languages[$counter],$languages[$counter],$lang_code):
        $output = sprintf("
        <a href='post-new.php?post_type=page&amp;cwtpost_id=%d&amp;cwtsrclang=%s&amp;cwttgtlang=%s' alt='Translate into %s' title='Translate into %s'>%s</a>
        ",$id,$src_lang,$lang_code,$languages[$counter],$languages[$counter],$lang_code);
        
        $exists_link = db_exists_in_translation_set($id,$src_lang,$lang_code);
        if ($exists_link) {
          $output = sprintf("
          <a href='post.php?post=%d&amp;action=edit&amp;cwtlang=%s'>%s</a>
          ",$exists_link,$lang_code,$lang_code);
        }
      }
      echo $output;
      $counter += 1;
    }    
  }
}

add_action('media_buttons', 'cms_with_tms_media_buttons', 30);

function get_post_or_page($id) {
  global $wpdb;
  $tablename = $wpdb->prefix . "posts";
  $sql = $wpdb->prepare("SELECT post_type FROM $tablename WHERE ID=%d",$id);
  return $wpdb->get_var($sql);
}

add_action('admin_init', 'cwt_init');
function cwt_init() {
  if (current_user_can('delete_posts')) add_action('delete_post', 'cms_with_tms_delete_posts', 10);
}  

function cms_with_tms_delete_posts($pid) {
  global $wpdb;
  
  if ($wpdb->get_var($wpdb->prepare("SELECT tgt_post_id FROM {$wpdb->prefix}cwt_translation_set WHERE tgt_post_id = %d", $pid))) {
    return $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}cwt_translation_set WHERE tgt_post_id = %d", $pid));
  }
  return true;
}

function db_add_translation_set($src_post_id,$src_post_lang,$tgt_post_id,$tgt_post_lang) {
  global $wpdb;
  $tablename1 = $wpdb->prefix . "posts";
  $tablename2 = $wpdb->prefix . "postmeta";
  $sql = $wpdb->prepare("SELECT ID from $tablename1 WHERE ID IN (SELECT post_id FROM $tablename2 WHERE post_id=%d)",$tgt_post_id);
  if ($wpdb->get_var($sql)) {
    $tablename = $wpdb->prefix . "cwt_translation_set";

    //check if it is a re-post of existing translaion set
    $sql = $wpdb->prepare("SELECT * from $tablename WHERE src_post_id=%d and src_lang_code=%s and tgt_post_id=%d and tgt_lang_code=%s",$src_post_id,$src_post_lang,$tgt_post_id,$tgt_post_lang);
    $exists = $wpdb->get_row($sql);
    if ($exists) { return $tgt_post_id; }
    $sql = $wpdb->prepare("SELECT * from $tablename WHERE tgt_post_id=%d and tgt_lang_code=%s",$src_post_id,$src_post_lang);
    $is_translation = $wpdb->get_row($sql);
    if ($is_translation) {
      $wpdb->insert($tablename,array('translation_setid'=>$is_translation->src_post_id, 'src_post_id'=>$is_translation->src_post_id,'src_lang_code'=>$is_translation->src_lang_code,'tgt_post_id'=>$tgt_post_id,'tgt_lang_code'=>$tgt_post_lang),array('%d','%d','%s','%d','%s'));
    } else {
      $wpdb->insert($tablename,array('translation_setid'=>$src_post_id, 'src_post_id'=>$src_post_id,'src_lang_code'=>$src_post_lang,'tgt_post_id'=>$tgt_post_id,'tgt_lang_code'=>$tgt_post_lang),array('%d','%d','%s','%d','%s'));
    }
    return $tgt_post_id;
  }    
  return false;
}

function db_exists_in_translation_set($src_post_id,$src_post_lang,$tgt_post_lang) {
  global $wpdb;
  $exists=false;
  $tablename = $wpdb->prefix . "cwt_translation_set";
  $sql = $wpdb->prepare("SELECT tgt_post_id FROM $tablename WHERE src_post_id=%d AND src_lang_code=%s AND tgt_lang_code=%s",$src_post_id,$src_post_lang,$tgt_post_lang);
  $exists = $wpdb->get_var($sql);
  if (!$exists) {
    $sql = $wpdb->prepare("SELECT src_post_id FROM $tablename WHERE tgt_post_id=%d AND src_lang_code=%s AND tgt_lang_code=%s",$src_post_id,$tgt_post_lang,$src_post_lang);
    $exists = $wpdb->get_var($sql);
    if (!$exists) {
      $sql = $wpdb->prepare("SELECT tgt_post_id FROM $tablename WHERE src_post_id IN (SELECT src_post_id FROM $tablename WHERE tgt_post_id=%d AND tgt_lang_code=%s) AND tgt_lang_code=%s",$src_post_id,$src_post_lang,$tgt_post_lang);
      $exists = $wpdb->get_var($sql);
    }
  }
  return $exists;
}

function db_get_translations($src_post_id) {
  global $wpdb;
  $tablename = $wpdb->prefix . "cwt_translation_set";
  $sql = $wpdb->prepare("SELECT tgt_post_id as post_id, tgt_lang_code as lang_code FROM $tablename WHERE src_post_id=%d",$src_post_id);
  $translations = $wpdb->get_results($sql);
  if (!$translations) {//not a src language, check if it is a translation
    $sql = $wpdb->prepare("SELECT tgt_post_id as post_id, tgt_lang_code as lang_code from $tablename where src_post_id=(SELECT src_post_id FROM $tablename WHERE tgt_post_id=%d) and tgt_post_id<>%d UNION SELECT src_post_id as post_id, src_lang_code as lang_code FROM $tablename WHERE tgt_post_id=%d",$src_post_id,$src_post_id,$src_post_id);
    $translations = $wpdb->get_results($sql);
  }  
  return $translations;   
}

function add_translated_into_lang_list($post_id,$post_type) {
  global $wpdb;
  $output = "<div id='cms_with_tms_trans_list'>Also available in ";
  $translation_list = db_get_translations($post_id);
  if ($translation_list) {
    foreach ($translation_list as $list) {
      $languages = get_option("cms_with_tms_lang_names");
      $language_codes = get_option("cms_with_tms_lang_codes");
      
      $tablename = $wpdb->prefix . "posts";
      $is_published = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $tablename WHERE ID=%d and post_status='publish'",$list->post_id));
      if (!$is_published) {
        continue;
      }    
      $counter=0;
      foreach ($language_codes as $lang_code) {
        if ($list->lang_code == $lang_code) {
          /*
          ($post_type=='post')?
          $output .= sprintf("<a href='?p=%d&cwtlang=%s'>%s</a> ",$list->post_id,$list->lang_code,$languages[$counter]):
          $output .= sprintf("<a href='?page_id=%d&cwtlang=%s'>%s</a> ",$list->post_id,$list->lang_code,$languages[$counter]);
          */
          
          $link = get_permalink($list->post_id);
          //echo $link . "<br/>";
          $pos = strpos($link,"?");
          if ($pos === false) {
            $link .= "?cwtlang=" .  $list->lang_code;            
          } else {
            $pos = strpos($link,"cwtlang");
            if ($pos === false) {
              $link .= "&cwtlang=" .  $list->lang_code;
            } else {
              $link = substr_replace($link,$list->lang_code,$pos+8,2);
            }                        
          }
          $output .= sprintf("<a href='%s'>%s</a> ",$link,$languages[$counter]);
        }
        $counter+=1;
      }      
    }
    if ($output === "<div id='cms_with_tms_trans_list'>Also available in ") {
      $output = "";
    } else {
      $output .= "</div>";
    }
  } else {
    $output = "";
  }
  return $output;
}

function cms_with_tms_set_locale($locale)
{
  global $wpdb;

  $language = $_REQUEST['cwtlang'];
  if(!$language) {
    $language =  get_option("cms_with_tms_def_lang");
  }

  $locale = $wpdb->get_var($wpdb->prepare("select gs_lang from {$wpdb->prefix}gs_languages where cwt_lang = %s", $language));
  
  return $locale;
}

add_filter('locale', 'cms_with_tms_set_locale');


