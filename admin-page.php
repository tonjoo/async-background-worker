<?PHP

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

					global $wp_filesystem;

					$filename = BACKGROUND_WORKER_LOG;

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
	<?php 
}