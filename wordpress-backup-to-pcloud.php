<?php
/*
 * Plugin Name: WordPress Backup to Pcloud 
 * Plugin URI: https://www.pcloud.com 
 * Description: WordPress Backup to pCloud has been created to backup your blog and its data, regularly. Just choose a day, time and how often you wish your backup to be saved in your pCloud! 
 * Version: 2.0
 * Author: Yuksel Saliev - yuks
 * URI: https://www.pcloud.com 
 * License: Copyright 2016 - pCloud 
 * 	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as published by the Free Software Foundation.
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
	See the GNU General Public License for more details. 
	You should have received a copy of the GNU General Public License along with this program; if not, write to the 
	Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
define ( 'BACKUP_TO_PCLOUD_VERSION', '2.0' );
define ( "PCLOUD_DIR", 'wordpress-backup-to-pcloud' );
define ( 'PCLOUD_BACKUP_DIR', 'WORDPRESS_BACKUPS/'.preg_replace(array('/\W/', '/ /'), array('', '_'), get_bloginfo("name") )  );


require_once (ABSPATH.'wp-admin/includes/upgrade.php');
require_once (plugin_dir_path(__FILE__). '/functions/conf.php');
require_once (plugin_dir_path(__FILE__). '/functions/database_backup.php');
require_once (plugin_dir_path(__FILE__). '/functions/files_backup.php');
require_once (plugin_dir_path(__FILE__). '/functions/files_restore.php');

function backup_to_pcloud_admin_menu() {
	$imgUrl = rtrim(plugins_url( '/images/logo_16.png', __FILE__ ));
	add_menu_page ( 'B2pCloud', 'pCloud Backup', 'administrator', 'b2pcloud_settings', 'b2pcloud_display_settings', $imgUrl );
}
function wp2pcloud_ajax_process_request() {
	$result = array (
			'status' => '0' 
	);
	$m = isset ( $_GET ['method'] ) ? $_GET ['method'] : false;
	
	if ($m == 'set_auth') {
		wp2pcloud_setAuth ( $_POST ['auth'] );
		$result ['status'] = '1';
	} else if ($m == 'unlink_acc') {
		wp2pclod_unlink ();
		$result ['status'] = '1';
	} else if ($m == "set_schedule") {
		wp2pcloud_setSchData($_POST);
		set_schedule($_POST['freq']);
		$result['status'] = 1;

	} else if($m == "restore_archive") {
		$r = new wp2pcloudFilesRestore();
		$r->setAuth(wp2pcloud_getAuth());
		$r->setFileId($_POST['file_id']);
		$r->getFile();
		die();
	} else if($m == 'get_log') {
		global $wpdb;
        $table_name = $wpdb->prefix . 'wp2pcloud_logs';
		$sql = "select content from ".$table_name." order by id desc limit 1";
		$log = $wpdb->get_var($sql);

		echo json_encode(array("log" => $log));
		die();

	} else if($m == 'check_can_restore') {
		if(!is_writable(dirname(dirname(dirname(dirname(__FILE__))))).'/' ) {
			$result['status'] = "1";
			$result['msg'] = __("<p>Path ".dirname(dirname(dirname(dirname(__FILE__)))).'/'." is not writable!</p>");
		}
		if(!is_writable(sys_get_temp_dir ())) {
			$result['status'] = "1";
			$result['msg'] = __("<p>Path ".sys_get_temp_dir ()." is not writable!</p>");
		}
	} else if($m == "start_backup") {
		wp2pcloud_perform_backup();
		exit();
	}
	echo json_encode ( $result );
	die ();
}
function wp2pcloud_perform_backup() {
	set_time_limit(0);
	ini_set('memory_limit', '-1');

	global $wpdb;

	$table_name = $wpdb->prefix . 'wp2pcloud_logs';

	$wpdb->insert(
		$table_name,
		array(
			'date' => current_time( 'mysql' ),
			'content' => "Start backup at ".date("Y-m-d H:i:s"),
		)
	);

	$id = $wpdb->insert_id;

	$b = new wp2pcloudDatabaseBackup ($id);
	$file = $b->start ();

	$f = new wp2pcloudFilesBackup ($id);

	$f->setMysqlBackUpFileName ( $file );
	$f->setArchiveName ( 'wp2-pcloud_backup_' . get_bloginfo ( 'name' ) . '_' . get_bloginfo ( "wpurl" ) . '_' . time () . '_' . date ( 'Y-m-d' ) . '.zip' );
	$f->start ();

	$sql = "select content from ".$table_name." where id = ".$id;
	$log = $wpdb->get_var($sql);

	echo json_encode(array(
		'log' => $log
	));

	// remove old logs
	$sql = "DELETE
			FROM ".$table_name."
			WHERE id NOT IN (
			SELECT *
			FROM (
			SELECT id
			FROM ".$table_name."
			ORDER BY id DESC
			LIMIT 10
			) AS dd
			)";
	$wpdb->query($sql);

}

function run_pcloud_backup_hook() {
	wp2pcloud_perform_backup();
}

function b2pcloud_display_settings() {
	wp_enqueue_script ( 'wpb2pcloud', plugins_url( '/wpb2pcloud.js', __FILE__ ) );
	wp_enqueue_style('wpb2pcloud',plugins_url( '/wpb2pcloud.css', __FILE__ ));
	$data = array (
			'pcloud_auth' => wp2pcloud_getAuth (),
			'blog_name' => get_bloginfo ( 'name' ),
			'blog_url' => get_bloginfo ( 'url' ),
			'archive_icon' => plugins_url('images/zip.png',__FILE__),
			'PCLOUD_BACKUP_DIR' => PCLOUD_BACKUP_DIR 
	);
	wp_localize_script ( 'wpb2pcloud', 'php_data', $data );
	include 'views/wp2pcloud-config.php';
}
function wp2pcloud_install() {
	global $wpdb;
	$sql = "
	CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "wp2pcloud_config` (
			`key` VARCHAR(250) NOT NULL,
			`value` TEXT NOT NULL,
			PRIMARY KEY (`key`)
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;		
	";
	dbDelta ( $sql );

	//
	$sql = "
	CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "wp2pcloud_logs` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			`date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`content` TEXT NOT NULL,
			PRIMARY KEY (`id`)
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;
	";
	dbDelta ( $sql );
}

function wp2pcloud_uninstall() {
	global $wpdb;
	$sql = "DROP TABLE `" . $wpdb->prefix . "wp2pcloud_config`";
	$wpdb->query ( $sql );

	$sql = "DROP TABLE `" . $wpdb->prefix . "wp2pcloud_logs`";
	$wpdb->query ( $sql );


	wp_clear_scheduled_hook( 'run_pcloud_backup_hook' );
}

function load_scripts() {
	wp_register_script ( 'wpb2pcloud', plugins_url( '/wpb2pcloud.js', __FILE__ )  );
	wp_enqueue_script ( 'jquery' );
}

function set_schedule($frequency) {
	wp_clear_scheduled_hook( 'run_pcloud_backup_hook' );

	wp_schedule_event( time(), $frequency, 'run_pcloud_backup_hook' );
}

function backup_to_pcloud_cron_schedules($schedules) {
	$new_schedules = array (
			'daily' => array (
					'interval' => 86400,
					'display' => 'Daily' 
			),
			'weekly' => array (
					'interval' => 604800,
					'display' => 'Weekly' 
			),
			'fortnightly' => array (
					'interval' => 1209600,
					'display' => 'Fortnightly' 
			) 
	)
	;
	return array_merge ( $schedules, $new_schedules );
}
add_filter ( 'cron_schedules', 'backup_to_pcloud_cron_schedules' );
register_activation_hook ( __FILE__, 'wp2pcloud_install' );
register_deactivation_hook ( __FILE__, 'wp2pcloud_uninstall' );
add_action ( 'admin_menu', 'backup_to_pcloud_admin_menu' );
add_action ( 'wp_enqueue_scripts', 'load_scripts' );
add_action ( 'run_pcloud_backup_hook', 'wp2pcloud_perform_backup' );
if (is_admin ()) {
	add_action ( 'wp_ajax_wp2pclod', 'wp2pcloud_ajax_process_request' );
}

// wp_schedule_single_event(strtotime("+10 seconds"), 'run_pcloud_backup_hook');