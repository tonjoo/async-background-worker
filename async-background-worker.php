<?php
/*
Plugin Name: Async Background Worker
Description: Aysinchrounous Background Worker for WordPress
Author: todiadiyatmo
Author URI: http://todiadiyatmo.com/
Version: 1.0
Text Domain: awb
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
 * $ wp background-worker
 */
require_once( plugin_dir_path( __FILE__ ) . 'admin-page.php' );

define( 'ABW_PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
define( 'ABW_ADMIN_MENU_SLUG', 'background_worker' );

define( 'ABW_DB_VERSION', 16 );
define( 'ABW_DB_NAME', 'bg_jobs' );

if ( ! defined( 'ABW_SLEEP' ) ) {
	define( 'ABW_SLEEP', 10000 );
}

if ( ! defined( 'ABW_NO_JOB_PERIOD' ) ) {
	define( 'ABW_NO_JOB_PERIOD', 0 );
}

if ( ! defined( 'ABW_TIMELIMIT' ) ) {
	define( 'ABW_TIMELIMIT', 300 );
}

if ( ! defined( 'ABW_DEBUG' ) ) {
	define( 'ABW_DEBUG', false );
}

if ( ! defined( 'ABW_QUEUE_NAME' ) ) {
	define( 'ABW_QUEUE_NAME', 'default' );
}

$installed_version = intval( get_option( 'ABW_DB_VERSION' ) );

if ( $installed_version < ABW_DB_VERSION ) {
	// drop and re create
	if ( $installed_version <= 5 ) {
		global $wpdb;

		$db_name = $wpdb->prefix . 'jobs';

		$sql = 'DROP TABLE ' . $db_name . ';';
		$wpdb->query( $sql );

		async_background_worker_install_db();
	}

	// drop and re create
	if ( $installed_version <= 10 ) {
		global $wpdb;

		$db_name = $wpdb->prefix . ABW_DB_NAME;

		$sql = "ALTER TABLE {$db_name} ADD `created_datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `attempts`;";
		$wpdb->query( $sql );
	}

	// Change storage engine to InnoDB.
	if ( $installed_version <= 15 ) {
		global $wpdb;

		$db_name = $wpdb->prefix . ABW_DB_NAME;

		$sql = "ALTER TABLE {$db_name} ENGINE=InnoDB;";
		$wpdb->query( $sql );
	}

	update_option( 'ABW_DB_VERSION', ABW_DB_VERSION, 'no' );
}

function async_background_worker_install_db() {
	global $wpdb;

	$db_name = $wpdb->prefix . ABW_DB_NAME;

	// create db table
	$charset_collate = $wpdb->get_charset_collate();

	if ( $wpdb->get_var( "SHOW TABLES LIKE '$db_name'" ) != $db_name ) {
		$sql = 'CREATE TABLE ' . $db_name . "
				( `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				  `queue` varchar(255) NOT NULL,
				  `payload` longtext NOT NULL,
				  `attempts` tinyint(4) UNSIGNED NOT NULL,
					`created_datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY  (`id`)
			  ) ENGINE=InnoDB $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	update_option( 'ABW_DB_VERSION', ABW_DB_VERSION, 'no' );
}

// run the install scripts upon plugin activation
register_activation_hook( __FILE__,'async_background_worker_install_db' );

/**
 * Add settings button on plugin actions
 */
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'async_background_worker_add_settings_link' );
function async_background_worker_add_settings_link( $links ) {
	$menu_page = ABW_ADMIN_MENU_SLUG;
	$settings_link = '<a href="tools.php?page=' . $menu_page . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

if ( ! function_exists( 'get_current_url' ) ) {
	function get_current_url() {
		$url = @( $_SERVER['HTTPS'] != 'on' ) ? 'http://' . $_SERVER['SERVER_NAME'] : 'https://' . $_SERVER['SERVER_NAME'];
		$url .= $_SERVER['REQUEST_URI'];

		return $url;
	}
}

if ( ! function_exists( 'bw_number_format' ) ) {
	function bw_number_format( $number ) {
		return number_format( $number, 0, ',', '.' );
	}
}

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

function add_async_job( $job, $queue = ABW_QUEUE_NAME ) {
	global $wpdb;

	$table_name = $wpdb->prefix . ABW_DB_NAME;

	// Serialize class
	$job_data = serialize( $job );

	$wpdb->insert( 
		$table_name, 
		array( 
			'queue' 			=> $queue, 
			'created_datetime' 	=> current_time('mysql'), 
			'payload' 			=> $job_data, 
			'attempts' 			=> 0 
		), 
		array( '%s', '%s', '%s', '%d' ) 
	);
}

// alias
function wp_background_add_job( $job, $queue = ABW_QUEUE_NAME ) {
	add_async_job( $job, $queue );
}

/**
 * Run background worker listener.
 *
 * listen = Running the listener, WordPress is reboot after each job exectuion
 * listen-loop = Running the listener in daemon mode, WordPress is not reboot after each job execution
 */

$background_worker_cmd = function( $args = array() ,$assoc_args = array()) {

	$async_worker = new Async_Background_Worker($args,$assoc_args);

	$async_worker->run();

};

class Async_Background_Worker {

	private $args;
	private $assoc_args;
	private $name;
	private $listen;
	private $listen_loop;

	function __construct($args = array(), $assoc_args = array()) {
		
		$this->args = $args;
		$this->assoc_args = $assoc_args;

		// set name
		if(isset($assoc_args['name']) && $assoc_args['name']) {
			$this->name = $assoc_args['name'];
		}
		else {
			$this->name = "Async Worker #".getmypid();
		}

		// set queue name 
		if(isset($assoc_args['queue_name']) && $assoc_args['queue_name']) {
			$this->queue_name = $assoc_args['queue_name'];
		}
		else {
			$this->queue_name = ABW_QUEUE_NAME;			
		}

		$this->listen = in_array('listen',$args) ? true : false;
		$this->listen_loop = in_array('listen-loop',$args) ? true : false;


	}

	function run() {

		if ( $this->listen && ! function_exists( 'exec' ) ) {
			$this->debug( 'Cannot run WordPress background worker on `listen` mode, please use `listen-loop` instead' );
		}

		$this->debug( 'Async Background Worker : Start working on queue : '.$this->queue_name );

		if( $this->listen_loop ) {
			$this->set_timelimit(-1);	
		}
		else {
			$this->set_timelimit();	
		}

		// listen-loop mode
		// @todo max execution time on listen_loop
		if ( $this->listen_loop ) { 


			$this->debug( 'Async Background Worker : Mode Listen Loop' );
			while ( true ) { 
				$this->check_memory();

				usleep( ABW_SLEEP );
				$this->execute_job();
			} 
		} elseif ( $this->listen ) {
			$this->debug( 'Async Background Worker : Mode Listen' );
			// start daemon
			while ( true ) {

				$this->debug( 'Spawn worker' );
				usleep( ABW_SLEEP );
				$this->check_memory();

				// output buffer
				$output = array();
	
				// process $args			
				$args = $this->args;

				// remove $args listen and listen-loop
				$args = array_diff($args, ['listen'] );
				$args = array_diff($args, ['listen-loop'] );

				// add background worker command
				array_unshift( $args, 'background-worker' );

				// process $assoc_args
				$assoc_args = $this->assoc_args;

				foreach($assoc_args as $param => $value) {
				   $paramsJoined[] = "--$param='$value'";
				}

				$_ = $_SERVER['argv'][0]; // or full path to php binary

				if ( function_exists( 'posix_geteuid' ) && posix_geteuid() == 0 && ! in_array( '--allow-root', $args ) ) {
					array_unshift( $args, '--allow-root' );
				}

				$args = implode( ' ', $args );
				if( sizeof($assoc_args) != 0 ) {
					$args = $args.' '.$assoc_args;
				}
				$cmd = $_ . ' ' . $args . ' 2>&1';

				$this->debug( 'Command '.$cmd );

				exec( $cmd ,$output );

				foreach ( $output as $echo ) {
					WP_CLI::log( $echo );
				}

				$this->output_buffer_check();

			}
		} else {
			$this->execute_job();
		}

		$this->debug( 'Async Background Worker : End' );

		$this->output_buffer_check();
		exit();
	}

	function check_memory() {

		if ( ABW_DEBUG ) {
			$usage = memory_get_usage() / 1024 / 1024;

			$this->debug( 'Memory Usage : ' . round( $usage, 2 ) . 'M of maximum : '.WP_MEMORY_LIMIT );
		}

		// Assume that each proccess take 50 M
		if ( ( memory_get_usage() / 1024 / 1024) - 50 >= WP_MEMORY_LIMIT ) { 
			WP_CLI::log( 'Memory limit execeed' );
			exit();
		}
	}

	function debug( $msg ) {

		if ( ABW_DEBUG ) {
			WP_CLI::log( $this->name." (".$this->queue_name.") : ".$msg );
		}
	}

	function output_buffer_check() {

		@ob_flush();
		@flush();
	}


	function set_timelimit( $timelimit = false) {

		$timelimit_set = false;

		if ( $timelimit == false ) {
			$timelimit = ABW_TIMELIMIT;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $timelimit );
			$timelimit_set = true;
		} elseif ( function_exists( 'ini_set' ) ) {
			ini_set( 'max_execution_time', $timelimit );
			$timelimit_set = true;
		}

		if( $timelimit_set == true ) {
			$this->debug( 'Set Timelimit to : ' .$timelimit );
		}

	}

	function execute_job() {
		
		// re-init wp_monolog.
		define( 'DOING_BG_WORKER', true );
		if ( function_exists('wp_monolog') ) {
			if ( isset( $GLOBALS['wp_monolog'] ) ) {
				unset( $GLOBALS['wp_monolog'] );
			}
			wp_monolog();
		}

		global $wpdb;

		$wpdb->query('START TRANSACTION');

		$table_name = $wpdb->prefix . ABW_DB_NAME;

		$job = $wpdb->get_row( $wpdb->prepare( 
			"SELECT * FROM $table_name WHERE attempts <= %d AND queue=%s ORDER BY id ASC for update", array( 0, $this->queue_name ) 
		) );

		if( !$job ) {
			$job = 	$job = $wpdb->get_row( $wpdb->prepare( 
						"SELECT * FROM $table_name WHERE attempts <= %d AND queue=%s ORDER BY id ASC for update", array( 2, $this->queue_name ) 
							) );
		}

		// No Job
		if ( ! $job ) {
			$wpdb->query('COMMIT');
			$this->debug("No job available.." );

			if( ABW_NO_JOB_PERIOD >= 1 ) {
				$this->debug( 'BG Worker put to Sleep' );
				for ($i=0; $i < ABW_NO_JOB_PERIOD ; $i++) { 
					$this->debug( '.' );
					sleep(1);
				}
			}

			return;
		}

		$job_data = unserialize( @$job->payload );

		if ( ! $job_data ) {

			$this->debug("Delete malformated job..");

			$wpdb->delete( 
				$table_name, 
				array( 'id' => $job->id ), 
				array( '%d' ) 
			);
			$wpdb->query('COMMIT');

			return;
		}

		$this->debug( "Working on job ID = {$job->id}" );

		$wpdb->update( 
			$table_name, 
			array( 
				'attempts' => (int) $job->attempts + 1 
			), 
			array( 'id' => $job->id ) 
		);

		$wpdb->query('COMMIT');

		try {

			$function = $job_data->function;
			$data = is_null( $job_data->user_data ) ? false : $job_data->user_data;

			if ( is_callable( $function ) ) {
				$function($data);
			} else {
				call_user_func_array( $function, $data );
			}

			// delete data
			$wpdb->delete( 
				$table_name, 
				array( 'id' => $job->id ), 
				array( '%d' ) 
			);
		} catch (Exception $e) { 
			 WP_CLI::error( "Caught exception: ".$e->getMessage() );
		} 
	}

}

WP_CLI::add_command( 'background-worker', $background_worker_cmd );
