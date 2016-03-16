<?php
require_once(GS_PLUGIN_PATH . '/lib/helpers.php');
require_once(GS_PLUGIN_PATH . '/lib/globalsight.php');

function gs_define_cron_frequency($schedules)
{
  $schedules[GS_CRON_FREQUENCY_NAME] = array(
      'interval'=> 60,
      'display'=>  __('Once Every Minute')
  );
  return $schedules;
}
add_filter('cron_schedules','gs_define_cron_frequency');



function gs_install_cron()
{
  if(!wp_next_scheduled('gs_cron_hook')) {
    wp_schedule_event(time(), GS_CRON_FREQUENCY_NAME, 'gs_cron_hook');
  }
}


function gs_execute_recurring_cron()
{
  update_option('gs_execute_recurring_tasks', true);
}
add_action('gs_cron_hook','gs_execute_recurring_cron');


function gs_execute_recurring_tasks()
{
  if(!get_option('gs_execute_recurring_tasks')) {
    return;
  } else {
    // set it back to false
    update_option('gs_execute_recurring_tasks', false);
  }

  // don't stop processing on user abort
  ignore_user_abort(true);
  
  $gs_user = get_userdatabylogin('globalsight');
  if(!$gs_user) {
    return; // globalsight user does not exist!
  }

  // Check status of jobs that are not complete
  $gs_jobs = gs_get_jobs();
  foreach($gs_jobs as $gs_job) {
    if($gs_job->status != 'COMPLETE') {
      gs_download_translation_job($gs_job->id);
    }
  }
  
}
add_action('wp_loaded','gs_execute_recurring_tasks');



function gs_uninstall_cron()
{
  wp_clear_scheduled_hook('gs_cron_hook');
}

?>
