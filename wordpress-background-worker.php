<?php
/*
Plugin Name: WordPress Background Worker
Description: Aysinchrounous Background Worker for WordPress
Author: todiadiyatmo
Author URI: http://todiadiyatmo.com/
Version: 0.2
Text Domain: wordpress-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Run Pheanstalkd Queue.
 *
 * Returns an error if the option didn't exist.
 *
 * ## OPTIONS
 *
 * <listen>
 * : Listen mode.
 *
 * ## EXAMPLES
 *
 *     $ wp background-worker
 */

if( !defined( 'WP_BACKGROUND_WORKER_QUEUE_NAME' ) )
	define( 'WP_BACKGROUND_WORKER_QUEUE_NAME', 'default' );

function wp_background_worker_install_db() {
   	global $wpdb;
  	$db_name = $wpdb->prefix."jobs";
 
	if($wpdb->get_var("show tables like '$db_name'") != $db_name) 
	{
		$sql = "CREATE TABLE ".$db_name." 
				( `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
				  `queue` varchar(512) COLLATE utf8_unicode_ci NOT NULL, 
				  `payload` longtext COLLATE utf8_unicode_ci NOT NULL, 
				  `attempts` tinyint(3) UNSIGNED NOT NULL,
				  PRIMARY KEY(`id`) );";
 
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
 
}
// run the install scripts upon plugin activation
register_activation_hook(__FILE__,'wp_background_worker_install_db');

add_action( "admin_menu", "background_worker_menu_page" );
function background_worker_menu_page() {
	add_management_page( __('Background Worker'), __('Background Worker'), 'manage_options', 'background_worker_log', 'background_worker_log_page_handler' );
}


function background_worker_log_page_handler() { ?>
	<div id="" class="wrap">
	
		<h1>Background Worker Log</h1>
		
		<div class="progress">
			<?php 
					
				if ( !defined('BACKGROUND_WORKER_LOG') || !BACKGROUND_WORKER_LOG ) {
					$content = 'No Log to diplay please define BACKGROUND_WORKER_LOG file in your wp-config.php';
				} elseif( !function_exists('file_get_contents')) {
					$content = 'Cannot read log file_get_contents() function did not exists.';
				}
				else {
					$filearray = file_get_contents(BACKGROUND_WORKER_LOG);
					$filearray = explode("\n",$filearray);


					$filearray = array_slice($filearray, -100);

					$filearray = array_reverse($filearray);

					$content = '';
					if ( $filearray ) {
						foreach ($filearray as $key => $value) {
							$content .= $value . "\n";
						}
					if ( $content == '')
						$content = "Nothing to read, permission problem maybe ? ";
					
					} else {
						$content = 'No Log';
					}
				} 
			?>
			<textarea class="log-text" rows="20" style='width:70%'><?php echo $content; ?></textarea>
		</div>
	</div>
	<?php 
}

if( !defined( 'WP_CLI' ) ) {
	return;
}

function wp_background_add_job( $job, $queue = WP_BACKGROUND_WORKER_QUEUE_NAME ) {
	global $wpdb;

	// Serialize class
	$job_data = serialize($job);

	$wpdb->insert( 
		$wpdb->prefix.'jobs', 
			array( 
				'queue' => $queue, 
				'payload' => $job_data,
				'attempts' => 0 
			)
		);
}

function wp_background_worker_listen($queue = WP_BACKGROUND_WORKER_QUEUE_NAME) {

	global $wpdb;

	$job = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix."jobs WHERE attempts <= 2 AND queue='$queue' ORDER BY id ASC" );

	// No Job
	if(!$job)
		return;

    $job_data = unserialize(@$job->payload);

    if(!$job_data)
    	return;

    try{

	    $function = $job_data->function;  
    	$data = is_null($job_data->user_data) ? false : $job_data->user_data ;

    	if( is_callable($function) )
    		$function($data);
    	else
    		call_user_func_array($function,$data);

    	//delete data
    	$wpdb->delete( 
			$wpdb->prefix."jobs", 
			array( 'id' => $job->id  )
		);
    }
    catch (Exception $e){
    	
    	$wpdb->update( 
			$wpdb->prefix."jobs", 
			array( 
				'attempts' => $job->attempts+1,	
			), 
			array( 'id' => $job->id  )
		);

    	echo 'Caught exception: ',  $e->getMessage(), "\n";
    }



}

/**
 * Run background worker listener.
 *
 */

$background_worker_cmd = function( $args = array() ) { 

	if( sizeof($args)!=0 )
		list( $key ) = $args;	


	if( in_array('listen', $args) )
		$listen = true;
	else
		$listen = false;

	wp_background_worker_listen();

	if( $listen  ) {

    	$_ = $_SERVER['argv'][0];  // or full path to php binary

    
    	array_unshift($args,'background-worker');
    	
    	if( posix_geteuid() == 0 && !in_array('--allow-root', $args) )
	    	array_unshift($args,'--allow-root');

		usleep(500000);
    	pcntl_exec( $_, $args);
	}

	usleep(500000);
	die();
};

WP_CLI::add_command( 'background-worker', $background_worker_cmd );
 
