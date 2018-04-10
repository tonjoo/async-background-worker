<?PHP

add_action( 'admin_enqueue_scripts', 'wp_background_worker_admin_scripts' );
function wp_background_worker_admin_scripts() {
	wp_enqueue_style( 'bg-worker-admin-style', ABW_PLUGIN_DIR . 'css/admin.css', array(), false );
	wp_enqueue_script( 'bg-worker-admin-js', ABW_PLUGIN_DIR . 'js/admin.js' , array( 'jquery' ), '', true );
}

add_action( 'admin_menu', 'background_worker_menu_page' );
function background_worker_menu_page() {
	add_management_page( __( 'Background Worker' ), __( 'Background Worker' ), 'manage_options', ABW_ADMIN_MENU_SLUG, 'background_worker_page_handler' );
}

function background_worker_page_handler() {
	global $wpdb, $pagenow;

	$table_name     = $wpdb->prefix . ABW_DB_NAME;

	$page           = isset( $_GET['page'] ) ? $_GET['page'] : ABW_ADMIN_MENU_SLUG;
	$page_uri       = add_query_arg(
		array(
			'page' => $page,
		), trailingslashit( admin_url() ) . $pagenow
	);
	$current_url    = get_current_url();
	$nonce          = wp_create_nonce( 'clear_background_worker_jobs' );
	$paged          = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
	$status         = isset( $_GET['status'] ) ? $_GET['status'] : '';
	$per_page       = 20;
	$offset         = ($per_page * $paged) - $per_page;

	$sql                = "SELECT * FROM $table_name ORDER BY id, created_datetime, attempts ASC";
	$total_active_jobs  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE attempts < 3" );
	$total_failed_jobs  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE attempts > 2" );
	$total_jobs         = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
	$max_pages          = max( 1, ceil( $total_jobs / $per_page ) );

	if ( '' != $status ) {
		if ( 'active' == $status ) {
			$sql = $wpdb->prepare(
				"
				SELECT * FROM $table_name 
				WHERE 
					attempts < %d 
				ORDER BY id, created_datetime, attempts ASC
				", array( 3 )
			);

			$total_active_jobs  = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
			$max_pages          = max( 1, ceil( $total_active_jobs / $per_page ) );
		} else {
			$sql = $wpdb->prepare(
				"
				SELECT * FROM $table_name 
				WHERE 
					attempts > %d 
				ORDER BY id, created_datetime, attempts DESC
				", array( 2 )
			);

			$total_failed_jobs  = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
			$max_pages          = max( 1, ceil( $total_failed_jobs / $per_page ) );
		}
	}

	$jobs = $wpdb->get_results( $sql . " LIMIT $offset, $per_page" );

	?>
	<div id="background-worker" class="wrap">
	
		<h1>Background Worker Log</h1>
		
		<?php
		if ( isset( $_GET['action'] ) && wp_verify_nonce( $_GET['action'], 'clear_background_worker_jobs' ) ) {
			$delete = $wpdb->query( "DELETE FROM $table_name WHERE 1" );

			if ( ! is_wp_error( $delete ) ) {
				echo '<p>' . __( 'All jobs have been cleared.' ) . '</p>';
			} else {
				echo '<p>Error : ' . $delete->get_error_message() . '</p>';
			}

			echo '<a href="' . $page_uri . '">' . __( 'Return to plugin homepage' ) . '</a>';

			return;
		}
		?>

		<ul class="tabs">
			<li class="tab-link current" data-tab="background-worker-job">Job</li>
			<li class="tab-link" data-tab="background-worker-log">Log</li>
		</ul>

		<div id="background-worker-job" class="tab-content current">
			<div class="tab-wrapper">
				<div class="count">
					<span class="pull-left">Total Jobs: <strong><?php echo bw_number_format( $total_jobs ); ?></strong></span>
					<span class="pull-right">Failed Jobs: <strong><?php echo bw_number_format( $total_failed_jobs ); ?></strong></span>
				</div>

				<div class="navigation">
					<div class="pull-left">
						<ul class="subsubsub">
							<li><a href="<?php echo $page_uri; ?>" class="
													<?php
													if ( '' == $status ) {
														echo 'current';}
?>
">Semua</a> (<?php echo bw_number_format( $total_jobs ); ?>) | </li>
							<li><a href="
							<?php
							echo add_query_arg(
								array(
									'status' => 'active',
								),$page_uri
							);
?>
" class="
<?php
if ( 'active' == $status ) {
	echo 'current';}
?>
">Active Jobs</a> (<?php echo bw_number_format( $total_active_jobs ); ?>) | </li>
							<li><a href="
							<?php
							echo add_query_arg(
								array(
									'status' => 'failed',
								),$page_uri
							);
?>
" class="
<?php
if ( 'failed' == $status ) {
	echo 'current';}
?>
">Failed Jobs</a> (<?php echo bw_number_format( $total_failed_jobs ); ?>)</li>
						</ul>
					</div>
					
					<?php if ( $total_jobs > 0 ) { ?>
						<div class="pull-right">
							<a href="
							<?php
							echo add_query_arg(
								array(
									'action' => $nonce,
								),$page_uri
							);
?>
" onClick="if ( !confirm('Are you sure?') ) return false;"><?php _e( 'Clear All Jobs' ); ?></a>
						</div>
						<?php
}
?>
				</div>

				<table class="bordered-table">
					<thead>
						<tr>
							<th scope="row" rowspan="2">#</th>
							<th rowspan="2"><?php _e( 'ID' ); ?></th>
							<th rowspan="2"><?php _e( 'Created Date Time' ); ?></th>
							<th rowspan="2"><?php _e( 'Job' ); ?></th>
							<th rowspan="2"><?php _e( 'Queue' ); ?></th>
							<th rowspan="2"><?php _e( 'Attempts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $jobs ) {
							$number = $offset;
							$i = 0;
							foreach ( $jobs as $key => $job ) {
								$i++;
								$number++;

								$payload = unserialize( @$job->payload );

								?>
								<tr>
									<th scope="row"><?php echo $number; ?></th>
									<th><?php echo $job->id; ?></th>
									<th class="text-center"><?php echo isset( $job->created_datetime ) ? $job->created_datetime : '-'; ?></th>
									<td>
										<?php
											$function = $payload->function;
										if ( is_array( $function ) ) {
											$obj = $function[0];
											unset( $function[0] );
											foreach ( $function as $func ) {
												echo $func;
											}
										} else {
											echo $function;
										}

											/*
                                             * Params
											 */
											// echo "<br>";
											// $user_data = $payload->user_data;
											// echo "<pre>";
											// if ( is_array($user_data) ) {
											// foreach ($user_data as $key_param => $param ) {
											// echo "[".$key_param."]" . " => " . $param;
											// echo "<br>";
											// }
											// } else {
											// echo "User data: " . $user_data;
											// }
											// echo "</pre>";
											?>
										</td>
										<td class="text-center"><?php echo $job->queue; ?></td>
										<td class="text-center">
											<?php
											if ( 'failed' == $status ) {
												echo '<div class="actions">';
												echo '<a href="#" id="" data-job-ID="' . $job->id . '" class="button btn-bw-retry-job">Retry Job</a>';
												echo '<span class="spinner hide"></span>';
												echo '</div>';
											} else {
												echo $job->attempts;
											}
											?>
										</td>
									</tr>
									<?php
							}
						} else {
						?>
								<tr>
									<td colspan="6" class="text-center"><?php _e( 'Empty job.' ); ?></td>
								</tr>
								<?php
						}
						?>
					</tbody>
				</table>

				<div class="pagination text-right">
					<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '&laquo;' ),
								'next_text' => __( '&raquo;' ),
								'total'     => $max_pages,
								'current'   => $paged,
							)
						);
					?>
				</div>
			</div>
		</div>

		<div id="background-worker-log" class="tab-content">
			<div class="tab-wrapper">
				<?php
				if ( ! defined( 'BACKGROUND_WORKER_LOG' ) || ! BACKGROUND_WORKER_LOG ) {
					$content = 'No Log to display. Please define BACKGROUND_WORKER_LOG file in your wp-config.php.';
				} elseif ( ! function_exists( 'file_get_contents' ) ) {
					$content = 'Cannot read log file_get_contents() function did not exists.';
				} else {

					global $wp_filesystem;

					if ( empty( $wp_filesystem ) ) {
						require_once( ABSPATH . '/wp-admin/includes/file.php' );
						WP_Filesystem();
					}

					$filearray = $wp_filesystem->get_contents( BACKGROUND_WORKER_LOG );

					$filearray = explode( "\n",$filearray );

					$filearray = array_slice( $filearray, -100 );

					$filearray = array_reverse( $filearray );

					$content = '';

					if ( $filearray ) {
						foreach ( $filearray as $key => $value ) {
							$content .= $value . "\n";
						}
						if ( $content == '' ) {
							$content = 'Nothing to read, permission problem maybe?';
						}
					} else {
						$content = 'No Log';
					}
				}
				?>
				<textarea class="log-text" rows="20"><?php echo $content; ?></textarea>
			</div>
		</div>

	</div>
	<?php
}

// add_action( "admin_notices", "background_worker_admin_notices" );
function background_worker_admin_notices() {
	if ( ! isset( $_GET['page'] ) || ABW_ADMIN_MENU_SLUG != $_GET['page'] ) {
		return;
	}

	if ( isset( $_GET['action'] ) && wp_verify_nonce( $_GET['action'], 'clear_background_worker_jobs' ) ) {
	?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'All jobs have been cleared.' ); ?></p>
		</div>
		<?php
	}
}

add_action( 'wp_ajax_retry_background_worker_job', 'retry_background_worker_job_ajax_callback' );
function retry_background_worker_job_ajax_callback() {
	global $wpdb;

	$table_name = $wpdb->prefix . ABW_DB_NAME;

	$response   = [];

	$dataForm   = $_POST['dataForm'];
	$_POST      = $dataForm;
	$job_id     = $_POST['id'];

	$update = $wpdb->update(
		$table_name,
		array(
			'attempts' => 0,
		),
		array(
			'id' => $job_id,
		),
		array( '%d' ),
		array( '%d' )
	);

	if ( ! is_wp_error( $update ) ) {
		$response['message'] = 'Active';
	} else {
		$response['message'] = $update->get_error_message();
	}

	wp_send_json( $response );
}
