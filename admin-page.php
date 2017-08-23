<?PHP

add_action( "admin_enqueue_scripts", "wp_background_worker_admin_scripts" );
function wp_background_worker_admin_scripts() {
	wp_enqueue_style( 'bg-worker-admin-style', BG_WORKER_DIR . 'css/admin.css', array(), false );
	wp_enqueue_script( 'kpi-scripts-js', BG_WORKER_DIR . 'js/admin.js' , array( 'jquery' ), '', true );
}

add_action( "admin_menu", "background_worker_menu_page" );
function background_worker_menu_page() {
	add_management_page( __('Background Worker'), __('Background Worker'), 'manage_options', 'background_worker_log', 'background_worker_log_page_handler' );
}

function background_worker_log_page_handler() { 
	global $wpdb;

	$table_name = $wpdb->prefix . BG_WORKER_DB_NAME;

	$paged 		= isset($_GET['paged']) ? intval($_GET['paged']) : 1;
	$per_page 	= 20;
	$offset 	= ($per_page*$paged) - $per_page;

	$sql = "SELECT * FROM $table_name";
	$total_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
	$jobs = $wpdb->get_results( $sql . " ORDER BY ID ASC LIMIT $offset, $per_page" );

	$total_failed_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE attempts > 2" );

	?>
	<div id="background-worker" class="wrap">
	
		<h1>Background Worker Log</h1>
		
		<ul class="tabs">
			<li class="tab-link" data-tab="background-worker-log">Log</li>
			<li class="tab-link current" data-tab="background-worker-job">Job</li>
		</ul>

		<div id="background-worker-log" class="tab-content">
			<div class="progress">
				<?php 
					if ( !defined('BACKGROUND_WORKER_LOG') || !BACKGROUND_WORKER_LOG ) {
						$content = 'No Log to diplay please define BACKGROUND_WORKER_LOG file in your wp-config.php';
					} elseif( !function_exists('file_get_contents')) {
						$content = 'Cannot read log file_get_contents() function did not exists.';
					}
					else {

						global $wp_filesystem;

						if ( empty($wp_filesystem) ) {
							require_once (ABSPATH . '/wp-admin/includes/file.php');
							WP_Filesystem();
						}

						$filearray = $wp_filesystem->get_contents(BACKGROUND_WORKER_LOG);
						
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
				<textarea class="log-text" rows="20" style='width:1000px'><?php echo $content; ?></textarea>
			</div>
		</div>

		<div id="background-worker-job" class="tab-content current">
			<div class="count">
				<span class="pull-left">Total Jobs: <strong><?php echo bw_number_format($total_jobs); ?></strong></span>
				<span class="pull-right">Failed Jobs: <strong><?php echo bw_number_format($total_failed_jobs); ?></strong></span>
			</div>

			<table class="bordered-table">
				<thead>
					<tr>
						<th scope="row">#</th>
						<th><?php _e('Job'); ?></th>
						<th><?php _e('Queue'); ?></th>
						<th><?php _e('Attemps'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php 
						if ( $jobs ) { $i=0; $number = $offset; 
							foreach ($jobs as $key => $job) { $i++; $number++; ?>
								<tr>
									<th scope="row"><?php echo $number; ?></th>
									<td><?php echo unserialize(@$job->payload)->function; ?></td>
									<td class="text-center"><?php echo $job->queue; ?></td>
									<td class="text-center"><?php echo $job->attempts; ?></td>
								</tr>
								<?php 
							}
						} else { ?>
							<tr>
								<td colspan="4" class="text-center"><?php _e('Empty job.'); ?></td>
							</tr>
							<?php 
						}
					?>
				</tbody>
			</table>

			<div class="pagination text-right">
				<?php 
					echo paginate_links( array(
						'base' 		=> add_query_arg( 'paged', '%#%' ),
						'format' 	=> '',
						'prev_text' => __('&laquo;'),
						'next_text' => __('&raquo;'),
						'total' 	=> ceil($total_jobs/$per_page),
						'current' 	=> $paged
					));
				?>
			</div>
		</div>
	</div>
	<?php 
}