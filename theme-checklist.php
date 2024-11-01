<?php
/*
Plugin Name: Theme Review Checklist
Description: A checklist of the most common snag points that developers run into when creating themes.
Version: 1.0.3
Author: Greg Priday
Author URI: http://siteorigin.com
License: GPL3
License URI: license.txt
*/

define('THEME_CHECKLIST_VERSION', '1.0.3');

/**
 * Save data for the checklist
 *
 * @param bool $theme
 *
 * @return array
 */
function theme_checklist_load_data($theme = false){
	if(empty($theme)) $theme = get_stylesheet();

	$data = get_theme_mod( 'so_theme_checklist', array() );
	$data = apply_filters('theme_checklist_load', $data, $theme);

	if(!is_array($data)) $data = array();
	if( empty($data['checked']) ) $data['checked'] = array();
	if( empty($data['notes']) ) $data['notes'] = array();

	return $data;
}

/**
 * Load data for the checklist
 *
 * @param $data
 * @param bool $theme
 */
function theme_checklist_save_data($data, $theme = false) {
	if(empty($theme)) $theme = get_stylesheet();

	if(!is_array($data)) $data = array();
	if(empty($data['checked'])) $data['checked'] = array();
	if(empty($data['notes'])) $data['notes'] = array();

	$data = apply_filters('theme_checklist_save', $data, $theme);

	set_theme_mod('so_theme_checklist', $data);
}

/**
 * Add the checklist page
 */
function theme_checklist_add_menu(){
	add_submenu_page('tools.php', __('Theme Checklist', 'theme-checklist'), __('Theme Checklist', 'theme-checklist'), 'edit_theme_options', 'theme-checklist', 'theme_checklist_render_admin');
}
add_action('admin_menu', 'theme_checklist_add_menu');

/**
 * Display the admin page
 */
function theme_checklist_render_admin(){
	include plugin_dir_path(__FILE__).'/tpl/admin-page.php';
}

/**
 * Enqueue all the admin scripts
 *
 * @param $prefix
 */
function theme_checklist_enqueue_scripts($prefix){
	if($prefix != 'tools_page_theme-checklist') return;
	wp_enqueue_style( 'theme-checklist-admin', plugin_dir_url(__FILE__) . 'css/theme-checklist.css', array(), THEME_CHECKLIST_VERSION );

	wp_enqueue_script( 'theme-checklist-autosize', plugin_dir_url(__FILE__) . 'js/jquery.autosize.min.js', array('jquery'), '1.18.9' );
	wp_enqueue_script( 'theme-checklist-admin', plugin_dir_url(__FILE__) . 'js/theme-checklist.min.js', array('jquery'), THEME_CHECKLIST_VERSION );
}
add_action('admin_enqueue_scripts', 'theme_checklist_enqueue_scripts');

/**
 * Load the checks
 *
 * @param bool $load_status
 *
 * @return array
 */
function theme_checklist_load_checks($load_status = true){
	// checks.json content is a snapshot taken from


	$checks = get_transient('theme_checklist_checks');
	if($checks === false) {
		// Fetch the most up to date list of checks from the themechecklist server.
		$request = wp_remote_get('http://themechecklist.org/wp-admin/admin-ajax.php?action=get_themecheck_points');

		if( is_wp_error($request) && isset($request['response']['code']) && $request['response']['code'] == 200 ) {
			// We can't reach the checklist server, so use a snapshot
			$checks = json_decode( file_get_contents( plugin_dir_path(__FILE__) . '/checks.json' ) , true);
		}
		else {
			// Use the version of the checks from the checklist server
			$checks = json_decode( $request['body'] , true);
		}

		set_transient('theme_checklist_checks', $checks, 3600);
	}

	$checks = apply_filters('theme_checklist_checks', $checks);

	// Now lets load up the current status of all the checks
	if($load_status && !empty($checks) && is_array($checks)) {

		$data = theme_checklist_load_data();


		if(!empty($data['checked'])) {
			foreach($checks as $id => $check) {
				if(!isset($data['checked'][$id])) continue;
				$checks[$id]['status'] = $data['checked'][$id];
			}
		}

		if(!empty($data['notes'])) {
			foreach($checks as $id => $check) {
				if(!isset($data['notes'][$id])) continue;
				$checks[$id]['notes'] = $data['notes'][$id];
			}
		}
	}

	return ( !empty($checks) && is_array($checks) ) ? $checks : array();
}

/**
 * Handle changing the checked status of a single check
 */
function theme_checklist_change_status_handler(){
	if ( !current_user_can('edit_theme_options') ) exit();
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) exit();
	if ( empty($_GET['status']) || empty($_GET['check']) ) exit();

	$data = theme_checklist_load_data();

	if($_GET['status'] == 'reset') {
		unset( $data['checked'][ intval($_GET['check']) ] );
	}
	else {
		$data['checked'][ intval($_GET['check']) ] = $_GET['status'];
	}

	theme_checklist_save_data($data);
}
add_action('wp_ajax_theme_checklist_change', 'theme_checklist_change_status_handler');

/**
 * Save notes for a single check.
 */
function theme_checklist_save_notes_handler(){
	if ( !current_user_can('edit_theme_options') ) exit();
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) exit();
	if ( !isset($_POST['notes']) || empty($_GET['check']) ) exit();

	$data = theme_checklist_load_data();

	if( empty($data['notes'][intval($_GET['check'])]) ) {
		$data['notes'][intval($_GET['check'])] = '';
	}

	// Save the notes
	$data['notes'][intval($_GET['check'])] = stripslashes($_POST['notes']);
	if(empty($data['notes'][intval($_GET['check'])])) unset($data['notes'][intval($_GET['check'])]);
	theme_checklist_save_data($data);

	exit();
}
add_action('wp_ajax_theme_checklist_save_notes', 'theme_checklist_save_notes_handler');

/**
 * Handler to reset the data
 */
function theme_checklist_reset_all_handler(){
	if ( !current_user_can('edit_theme_options') ) exit();
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) exit();
	
	theme_checklist_save_data( array() );
}
add_action('wp_ajax_theme_checklist_reset_all', 'theme_checklist_reset_all_handler');

/**
 * Generate an export file
 */
function theme_checklist_export_handler(){
	if ( !current_user_can('edit_theme_options') ) exit();

	$theme = get_stylesheet();

	header('content-type: application/json');
	header('Content-Disposition: attachment; filename=checks-'.$theme.'-'.date('dm-o-Gi').'.json');
	header('Expires: 0');
	
	$data = theme_checklist_load_data();
	echo json_encode( $data );

	exit();
}
add_action('wp_ajax_theme_checklist_export', 'theme_checklist_export_handler');

/**
 * Handle the sync request. Generally after loading data.
 */
function theme_checklist_sync_handler(){
	if ( !current_user_can('edit_theme_options') ) exit();
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) exit();

	theme_checklist_save_data( stripslashes_deep($_POST) );
}
add_action('wp_ajax_theme_checklist_sync_data', 'theme_checklist_sync_handler');

/**
 * Generate a fix report
 */
function theme_checklist_view_fix_report(){
	if ( !current_user_can('edit_theme_options') ) exit();
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) exit();

	$checks = theme_checklist_load_checks();

	header('content-type: text/plain');

	$status_count = array(
		'pass' => 0,
		'fail' => 0,
		'status' => 0,
		'all' => 0,
	);

	// Count the number of passed and failed
	foreach($checks as $check) {
		$status_count['all']++;
		if(empty($check['status'])) continue;
		$status_count[$check['status']]++;
		$status_count['status']++;
	}

	if($status_count['pass'] + $status_count['fail'] > 0) {
		$theme = wp_get_theme();
		printf(
			__('%s was checked using the [https://wordpress.org/plugins/theme-checklist/ Theme Checklist plugin]. ', 'theme-checklist'),
			$theme->Name
		);
		printf(
			__('It passed %d/%d (%s) of the checks. ', 'theme-checklist'),
			$status_count['pass'],
			$status_count['status'],
			round($status_count['pass']/$status_count['status']*100) . '%'
		);

		if( $status_count['status'] < $status_count['all'] ) {
			$skipped = $status_count['all'] - ($status_count['status']);
			printf(
				_n('%d check was skipped in this review. ', '%d checks were skipped in this quality review. ', $skipped, 'theme-checklist'),
				$skipped
			);
			_e("It'll need to pass all checks before being approved.", 'theme-checklist');
		}

		echo "\n\n";
	}


	foreach($checks as $check) {
		if( !empty($check['status']) && $check['status'] == 'fail' ) {
			echo '== ' . __('Failed:', 'theme-checklist') . ' ' . $check['title'] .  ' =='."\n";
			echo $check['excerpt']." - ";
			echo '[' . $check['url'] . ' ' . __('Read More', 'theme-checklist').']';
			if(!empty($check['notes'])) {
				echo "\n\n";
				echo $check['notes'];
			}

			echo "\n\n";
		}
	}

	if($status_count['all'] == $status_count['pass']) {
		_e('== Conclusions ==', 'theme-checklist');
		echo "\n\n";
		_e("No issues were found after a full review. This theme is '''ready for approval'''. ", 'theme-checklist');
	}
	else if($status_count['fail'] == 0) {
		_e('== Conclusions ==', 'theme-checklist');
		echo "\n\n";
		_e("Nothing to fix, but this wasn't a full review please double check your theme against the [http://codex.wordpress.org/Theme_Review official Theme Review guidelines] to make sure it's ready for approval. ", 'theme-checklist');
	}
	else {
		_e('== Conclusions ==', 'theme-checklist');
		echo "\n\n";
		_e("This theme is '''not ready for approval''' on the theme directory. Please consider using the [https://wordpress.org/plugins/theme-checklist/ Theme Checklist plugin] to help your theme pass the review process. ");
		_e("[https://wordpress.org/themes/upload/ Re-upload your theme] after you've made the required corrects and we'll continue the review.", 'theme-checklist');
		echo "\n\n";
		_e("'''Note:''' The Theme Checklist website and plugin are not part of the official theme review. They're just helpful hints on how to pass the official guidelines. You'll still need to make sure that your theme complies with the current [http://codex.wordpress.org/Theme_Review official Theme Review guidelines]. We make every effort to ensure that the Theme Checklist site and plugin are kept up to date with the official gudeilines.");
	}

	exit();
}
add_action('wp_ajax_theme_checklist_view_fix_report', 'theme_checklist_view_fix_report');

/**
 * Just notify the user that there's an update with more checks available.
 */
function theme_checklist_update_check(){
	$updates = get_site_transient('update_plugins');

	if( !empty($updates->response) && !empty($updates->response['theme-checklist/theme-checklist.php']) ){
		?>
		<div class="error settings-error">
			<p><strong><?php printf(__("There is an update available for Theme Review Checklist. Please <a href='%s'>update</a> to make sure you have all the latest checks.", 'theme-checklist'), admin_url('plugins.php?plugin_status=upgrade') ) ?></strong></p>
		</div>
		<?php
	}
}