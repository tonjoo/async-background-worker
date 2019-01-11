<?php

add_action( 'admin_enqueue_scripts', 'wp_background_worker_admin_scripts' );
function wp_background_worker_admin_scripts() {
	wp_enqueue_style( 'bg-worker-admin-style', ABW_PLUGIN_DIR . 'css/admin.css', array(), '1.0' );
	wp_enqueue_script( 'bg-worker-admin-js', ABW_PLUGIN_DIR . 'js/admin.js', array( 'jquery' ), '1.0', true );
}

add_action( 'admin_menu', 'background_worker_menu_page' );
function background_worker_menu_page() {
	add_management_page( __( 'Background Worker' ), __( 'Background Worker' ), 'manage_options', ABW_ADMIN_MENU_SLUG, 'background_worker_page_handler' );
}

function background_worker_page_handler() {
	global $wpdb, $pagenow;

	$table_name = $wpdb->prefix . ABW_DB_NAME;

	$page        = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ABW_ADMIN_MENU_SLUG;
	$page_uri    = add_query_arg(
		array(
			'page' => $page,
		),
		trailingslashit( admin_url() ) . $pagenow
	);
	$current_url = get_current_url();
	$nonce       = wp_create_nonce( 'clear_background_worker_jobs' );
	$paged       = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
	$status      = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
	$per_page    = 20;
	$offset      = ( $per_page * $paged ) - $per_page;

	$sql               = "SELECT * FROM $table_name ORDER BY id, created_datetime, attempts ASC";
	$total_active_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE attempts < 3" );
	$total_failed_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE attempts > 2" );
	$total_jobs        = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
	$max_pages         = max( 1, ceil( $total_jobs / $per_page ) );

	if ( '' !== $status ) {
		if ( 'active' === $status ) {
			$sql = $wpdb->prepare(
				"
				SELECT * FROM $table_name 
				WHERE 
					attempts < %d 
				ORDER BY id, created_datetime, attempts ASC
				",
				array( 3 )
			);

			$total_active_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
			$max_pages         = max( 1, ceil( $total_active_jobs / $per_page ) );
		} else {
			$sql = $wpdb->prepare(
				"
				SELECT * FROM $table_name 
				WHERE 
					attempts > %d 
				ORDER BY id, created_datetime, attempts DESC
				",
				array( 2 )
			);

			$total_failed_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM ($sql) AS derived_table" );
			$max_pages         = max( 1, ceil( $total_failed_jobs / $per_page ) );
		}
	}

	$jobs = $wpdb->get_results( $sql . " LIMIT $offset, $per_page" );

	?>
	<div id="background-worker" class="wrap">

		<h1><?php esc_html_e( 'Background Worker Log' ); ?></h1>

		<?php
		if ( isset( $_GET['action'] ) && wp_verify_nonce( sanitize_key( $_GET['action'] ), 'clear_background_worker_jobs' ) ) {
			$delete = $wpdb->query( "DELETE FROM $table_name WHERE 1" );

			if ( ! is_wp_error( $delete ) ) {
				echo '<p>' . esc_html__( 'All jobs have been cleared.' ) . '</p>';
			} else {
				echo '<p>Error : ' . $delete->get_error_message() . '</p>';
			}

			echo '<a href="' . esc_url( $page_uri ) . '">' . esc_html__( 'Return to plugin homepage' ) . '</a>';

			return;
		}
		?>

		<ul class="tabs">
			<li class="tab-link current" data-tab="background-worker-job"><?php esc_html_e( 'Jobs' ); ?></li>
			<li class="tab-link" data-tab="background-worker-log"><?php esc_html_e( 'Log' ); ?></li>
		</ul>

		<div id="background-worker-job" class="tab-content current">
			<div class="tab-wrapper">
				<div class="count">
					<span class="pull-left"><?php printf( esc_html__( 'Total Jobs: <strong>%s</strong>' ), esc_html( bw_number_format( $total_jobs ) ) ); ?></span>
					<span class="pull-right"><?php printf( esc_html__( 'Failed Jobs: <strong>%s</strong>' ), esc_html( bw_number_format( $total_failed_jobs ) ) ); ?></span>
				</div>

				<div class="navigation">
					<div class="pull-left">
						<ul class="subsubsub">
							<li><a href="<?php echo esc_url( $page_uri ); ?>" class="<?php echo ( '' === $status ) ? 'current' : ''; ?>
								"><?php esc_html_e( 'All Jobs' ); ?></a> (<?php echo esc_html( bw_number_format( $total_jobs ) ); ?>) | </li>
							<li><a href="<?php echo esc_url( add_query_arg( array( 'status' => 'active' ), $page_uri ) ); ?>" class="<?php echo 'active' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Active Jobs' ); ?></a> (<?php echo esc_html( bw_number_format( $total_active_jobs ) ); ?>) | </li>
							<li><a href="<?php echo esc_url( add_query_arg( array( 'status' => 'failed' ), $page_uri ) ); ?>" class="<?php echo ( 'failed' === $status ) ? 'current' : ''; ?>"><?php esc_html_e( 'Failed Jobs' ); ?></a> (<?php echo esc_html( bw_number_format( $total_failed_jobs ) ); ?>)</li>
						</ul>
					</div>

					<?php if ( $total_jobs > 0 ) { ?>
						<div class="pull-right">
							<a href="<?php echo esc_url( add_query_arg( array( 'action' => $nonce ), $page_uri ) ); ?>" onclick="if ( ! confirm( 'Are you sure?' ) ) return false;"><?php esc_html_e( 'Clear All Jobs' ); ?></a>
						</div>
						<?php
}
?>
				</div>

				<table id="bg-worker-jobs-queue" class="bordered-table">
					<thead>
						<tr>
							<th scope="row" rowspan="2">#</th>
							<th rowspan="2"><?php esc_html_e( 'ID' ); ?></th>
							<th rowspan="2"><?php esc_html_e( 'Created Date Time' ); ?></th>
							<th rowspan="2"><?php esc_html_e( 'Job' ); ?></th>
							<th rowspan="2"><?php esc_html_e( 'Argument(s)' ); ?></th>
							<th rowspan="2"><?php esc_html_e( 'Queue' ); ?></th>
							<th rowspan="2"><?php esc_html_e( 'Attempts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $jobs ) {
							$number = $offset;
							$i      = 0;
							foreach ( $jobs as $key => $job ) {
								$i++;
								$number++;

								$payload = unserialize( @$job->payload );

								?>
								<tr>
									<th scope="row"><?php echo esc_html( $number ); ?></th>
									<th><?php echo esc_html( $job->id ); ?></th>
									<td class="created_datetime text-center"><?php echo isset( $job->created_datetime ) ? esc_html( $job->created_datetime ) : '-'; ?></td>
									<td>
										<?php
											$function = $payload->function;

										if ( is_array( $function ) ) {

											if ( is_object( $function[0] ) ) {
												$class  = get_class( $function[0] );
												$access = '->';
											} else {
												$class  = $function[0];
												$access = '::';
											}

											echo esc_html( $class . $access . $function[1] ) . '()';
										} else {
											echo esc_html( $function ) . '()';
										}
										?>
									</td>
									<td class="arguments">
										<?php
										$args = $payload->user_data;

										if ( empty( $args ) ) {
											echo '<em>' . esc_html__( 'None' ) . '</em>';
										} else {
											$json_options = 0;

											if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
												$json_options |= JSON_UNESCAPED_SLASHES;
											}
											if ( defined( 'JSON_PRETTY_PRINT' ) ) {
												$json_options |= JSON_PRETTY_PRINT;
											}

											echo '<pre>' . wp_json_encode( $args, $json_options ) . '</pre>';
										}
										?>
									</td>
									<td class="text-center"><?php echo esc_html( $job->queue ); ?></td>
									<td class="text-center">
										<?php
										if ( $job->attempts > 2 ) {
											echo '<div class="actions">';
											echo '<a href="#" id="" data-job-ID="' . esc_attr( $job->id ) . '" class="button btn-bw-retry-job">' . esc_html__( 'Retry Job' ) . '</a>';
											echo '<span class="spinner hide"></span>';
											echo '</div>';
										} else {
											echo esc_html( $job->attempts );
										}
										?>
									</td>
								</tr>
								<?php
							}
						} else {
						?>
							<tr>
								<td colspan="6" class="text-center"><?php esc_html_e( 'No jobs' ); ?>.</td>
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
					$content = __( 'No log file to display. Please define BACKGROUND_WORKER_LOG file in your wp-config.php.' );
				} elseif ( ! function_exists( 'file_get_contents' ) ) {
					$content = __( 'Cannot read log. Function file_get_contents() does not exist.' );
				} else {

					global $wp_filesystem;

					if ( empty( $wp_filesystem ) ) {
						require_once ABSPATH . '/wp-admin/includes/file.php';
						WP_Filesystem();
					}

					$filearray = $wp_filesystem->get_contents( BACKGROUND_WORKER_LOG );

					$filearray = explode( "\n", $filearray );

					$filearray = array_slice( $filearray, -100 );

					$filearray = array_reverse( $filearray );

					$content = '';

					if ( $filearray ) {
						foreach ( $filearray as $key => $value ) {
							$content .= $value . "\n";
						}
						if ( '' === $content ) {
							$content = __( 'Nothing to read, permission problem maybe?' );
						}
					} else {
						$content = __( 'No Log' );
					}
				}
				?>
				<textarea class="log-text" rows="20"><?php echo esc_textarea( $content ); ?></textarea>
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

	if ( isset( $_GET['action'] ) && wp_verify_nonce( sanitize_key( $_GET['action'] ), 'clear_background_worker_jobs' ) ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'All jobs have been cleared.' ); ?></p>
		</div>
		<?php
	}
}

add_action( 'wp_ajax_retry_background_worker_job', 'retry_background_worker_job_ajax_callback' );
function retry_background_worker_job_ajax_callback() {
	global $wpdb;

	$table_name = $wpdb->prefix . ABW_DB_NAME;

	$response = [];

	$dataForm = $_POST['dataForm'];
	$_POST    = $dataForm;
	$job_id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

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
