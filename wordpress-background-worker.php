<?php
/*
Plugin Name: WordPress Background Worker
Description: Background Worker with peanstalkd
Author: todiadiyatmo
Author URI: http://todiadiyatmo.com/
Version: 0.6.1
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
include 'vendor/autoload.php';

if( !defined( 'WP_BACKGROUND_WORKER_QUEUE_NAME' ) )
	define( 'WP_BACKGROUND_WORKER_QUEUE_NAME', 'WP_QUEUE' );

if( !defined( 'WP_BACKGROUND_WORKER_HOST' ) )
	define( 'WP_BACKGROUND_WORKER_HOST', '127.0.0.1' );

add_action( "admin_menu", "background_worker_menu_page" );
function background_worker_menu_page() {
	add_management_page( __('Background Worker'), __('Background Worker'), 'manage_options', 'background_worker_log', 'background_worker_log_page_handler' );
}

function background_worker_log_page_handler() { ?>
	<div id="" class="wrap">
	
		<h1>Background Worker Log</h1>
		
		<div class="progress">
			<?php 
				if ( !defined('ASTRA_API_LOG') || !ASTRA_API_LOG ) {
					$content = 'No Log to diplay please define ASTRA_API_LOG file in your wp-config.php';
				} else {
					$filearray = file(ASTRA_API_LOG);
					$lastlines = array_slice($filearray, -100);
					$reversed = array_reverse($lastlines);

					$content = '';
					if ( $reversed ) {
						foreach ($reversed as $key => $value) {
							$content .= $value . "\n";
						}
					} else {
						$content = 'No Error.';
					}
				} 
			?>
			<textarea class="log-text" rows="20"><?php echo $content; ?></textarea>
		</div>
	</div>
	<?php 
}

if( !defined( 'WP_CLI' ) ) {
	return;
}

function wp_background_add_job( $job, $tube = WP_BACKGROUND_WORKER_QUEUE_NAME ) {

	//@todo validate connection
	$queue = new \Pheanstalk\Pheanstalk(WP_BACKGROUND_WORKER_HOST);

	//@todo validate job

	// beanstalkd uses strings so we json_encode our job for storage  
	$job_data = json_encode($job);

	// place our job into the queue into a tube we'll call matching  
	$id = $queue->useTube(WP_BACKGROUND_WORKER_QUEUE_NAME)  
	    ->put($job_data);	
}

// @todo
function wp_background_get_queue() {
	// use $id to peek
}

function wp_background_worker_listen($listen) {

	//@todo validate connection
	$queue = new \Pheanstalk\Pheanstalk(WP_BACKGROUND_WORKER_HOST);

    // grab the next job off the queue and reserve it  
    $job = $queue->watch(WP_BACKGROUND_WORKER_QUEUE_NAME)  
        ->reserve();

    // decode the json data  
    $job_data = json_decode($job->getData(), false);

    $function = $job_data->function;  
    $data = $job_data->user_data;

    // run the function  
    $function($data);

    // remove the job from the queue  
    $queue->delete($job);  

	// exit for one success queue , must be spawn by supervisord 
	if( !$listen )
		die();
}

/**
 * Run background worker listener.
 *
 */

$background_worker_cmd = function( $args ) { 

	//@todo get all job

	$listen = true;	
	list( $key ) = $args;	

	while ($listen) {

		if( in_array('listen', $args) )
			$listen = true;
		else
			$listen = false;

		wp_background_worker_listen($listen);
	}
};

WP_CLI::add_command( 'background-worker', $background_worker_cmd );
