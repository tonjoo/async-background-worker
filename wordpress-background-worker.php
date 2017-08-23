<?php
/*
Plugin Name: WordPress Background Worker
Description: Aysinchrounous Background Worker for WordPress
Author: todiadiyatmo
Author URI: http://todiadiyatmo.com/
Version: 0.3
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
require_once(plugin_dir_path(__FILE__) . 'admin-page.php');

define('BG_WORKER_DIR', plugin_dir_url(__FILE__));

define('BG_WORKER_DB_VERSION',15);
define('BG_WORKER_DB_NAME','bg_jobs');

if( !defined('BG_WORKER_SLEEP') )
	define('BG_WORKER_SLEEP', 750000 );

if( !defined('BG_WORKER_TIMELIMIT') )
	define('BG_WORKER_TIMELIMIT', 60 );

if( !defined('WP_BG_WORKER_DEBUG') )
	define('WP_BG_WORKER_DEBUG',false );

if( !defined( 'WP_BACKGROUND_WORKER_QUEUE_NAME' ) )
	define( 'WP_BACKGROUND_WORKER_QUEUE_NAME', 'default' );

$installed_version = intval( get_option('BG_WORKER_DB_VERSION') );

if( $installed_version < BG_WORKER_DB_VERSION) {
	// drop and re create
	if( $installed_version <= 5 ) {
		global $wpdb;
  		$db_name = $wpdb->prefix."jobs";

		$sql = "DROP TABLE ".$db_name.";";
		$wpdb->query($sql);

		wp_background_worker_install_db();
	}


	// drop and re create
	if( $installed_version <= 10 ) {
		global $wpdb;
	  $db_name = $wpdb->prefix.BG_WORKER_DB_NAME;
	
		$sql = "ALTER TABLE {$db_name} ADD `created_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `attempts`;";
		$wpdb->query($sql);
	}
}


update_option('BG_WORKER_DB_VERSION', BG_WORKER_DB_VERSION);

function wp_background_worker_install_db() {
   	global $wpdb;
  	$db_name = $wpdb->prefix.BG_WORKER_DB_NAME;

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

if ( !function_exists('bw_number_format') ) {
	function bw_number_format($number) {
		return number_format($number, 0, ',', '.');
	}
}

if( !defined( 'WP_CLI' ) ) {
	return;
}

function wp_background_add_job( $job, $queue = WP_BACKGROUND_WORKER_QUEUE_NAME ) {
	global $wpdb;

	// Serialize class
	$job_data = serialize($job);

	$wpdb->insert(
		$wpdb->prefix.BG_WORKER_DB_NAME,
			array(
				'queue' => $queue,
				'payload' => $job_data,
				'attempts' => 0
			)
		);
}

function wp_background_worker_execute_job($queue = WP_BACKGROUND_WORKER_QUEUE_NAME) {
	global $wpdb;

	$job = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix.BG_WORKER_DB_NAME." WHERE attempts <= 2 AND queue='$queue' ORDER BY id ASC" );

	// No Job
	if( !$job ) {
		wp_background_worker_debug("No job available..");
		return;
	}

    $job_data = unserialize(@$job->payload);

    if(!$job_data) {

    	wp_background_worker_debug("Delete malformated job..");

    	$wpdb->delete(
			$wpdb->prefix.BG_WORKER_DB_NAME,
			array( 'id' => $job->id  )
		);

    	return;
    }

    wp_background_worker_debug("Working on job ID = {$job->id} ");

	$wpdb->update(
		$wpdb->prefix.BG_WORKER_DB_NAME,
		array(
			'attempts' => $job->attempts+1,
		),
		array( 'id' => $job->id  )
	);

    try{
	    $function = $job_data->function;
    	$data = is_null($job_data->user_data) ? false : $job_data->user_data ;

    	if( is_callable($function) )
    		$function($data);
    	else
    		call_user_func_array($function,$data);

    	//delete data
    	$wpdb->delete(
			$wpdb->prefix.BG_WORKER_DB_NAME,
			array( 'id' => $job->id  )
		);
    }
    catch (Exception $e){
    	 WP_CLI::error( "Caught exception: ".$e->getMessage() );
    }

}

/**
 * Run background worker listener.
 *
 * listen = Running the listener, WordPress is reboot after each job exectuion
 * listen-loop = Running the listener in daemon mode, WordPress is not reboot after each job execution
 */

$background_worker_cmd = function( $args = array() ) {


	if(  ( isset( $args[0] ) && 'listen' === $args[0] ) )
		$listen = true;
	else
		$listen = false;

	if( $listen && !function_exists('exec') ) {
		wp_background_worker_debug( "Cannot run WordPress background worker on `listen` mode, please use `listen-loop` instead" );
	}

	if(  isset( $args[0] ) && 'listen-loop' === $args[0])
		$listen_loop = true;
	else
		$listen_loop = false;

	if( !$listen && !$listen_loop ) {
		if( function_exists( 'set_time_limit' ) )
		 	set_time_limit(BG_WORKER_TIMELIMIT);
		elseif( function_exists( 'ini_set' ) )
			ini_set('max_execution_time', BG_WORKER_TIMELIMIT);
	}

	// listen-loop mode
	// @todo max execution time on listen_loop
	if( $listen_loop ) {

	    while( true ) {
	    	wp_background_worker_check_memory();

	        usleep(BG_WORKER_SLEEP);
	        wp_background_worker_execute_job();
	    }

	}
	else if ( $listen) {
		// start daemon
		while(true) {

			$output = array();

			wp_background_worker_check_memory();
			$args = array();

			usleep(BG_WORKER_SLEEP);
			wp_background_worker_debug("Spawn next worker");

			$_ = $_SERVER['argv'][0];  // or full path to php binary

		    array_unshift($args,'background-worker');


		    if( function_exists('posix_geteuid') && posix_geteuid() == 0 && !in_array('--allow-root', $args) )
			    array_unshift($args,'--allow-root');

			$args = implode(" ", $args);
			$cmd = $_." ".$args." 2>&1";

			exec( $cmd ,$output );

			foreach ( $output as $echo) {
				WP_CLI::log($echo);
			}

			wp_background_worker_output_buffer_check();

		}
	}
	else {

		wp_background_worker_execute_job();
	}

	wp_background_worker_output_buffer_check();
	exit();
};

function wp_background_worker_check_memory() {

	if( WP_BG_WORKER_DEBUG ) {
		$usage = memory_get_usage() / 1024 / 1024;
		wp_background_worker_debug(  "Memory Usage : ".round( $usage, 2 )."MB" );
	}

 	if ( ( memory_get_usage() / 1024 / 1024) >= WP_MEMORY_LIMIT ) {
				WP_CLI::log("Memory limit execeed");
        exit();
    }

}

WP_CLI::add_command( 'background-worker', $background_worker_cmd );

function wp_background_worker_debug( $msg ) {

	if( WP_BG_WORKER_DEBUG ) {
		WP_CLI::log($msg);
	}
}

function wp_background_worker_output_buffer_check() {

    @ob_flush();
    @flush();
}
