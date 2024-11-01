<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

class wp2pcloudLog {
	public static function info($id,$data) {
		$data = "\n".date('Y-m-d H:i:s').' - '. $data;
		$data = trim(strip_tags($data));
		if($data == "") {
			return;
		}
		global $wpdb;
		try {
			$table_name = $wpdb->prefix . 'wp2pcloud_logs';
			$sql = "UPDATE ".$table_name." SET content = CONCAT(content,%s,%s) where id = %s limit 1";
			$sql = $wpdb->prepare($sql,"<br />",$data,$id);
			$wpdb->query($sql);
		} catch(Exception $e) {

		}

	}
}

class wp2pcloudDatabaseBackup {
	
	private $tables,$db,$save_file,$write_file,$max_data_limit;
	
	public function __construct($log_id){
		global $wpdb;
		$this->tables = array();
		$this->db = $wpdb;
		$this->save_file = 'php://temp';
		$this->save_file = tempnam(sys_get_temp_dir(), 'sqlarchive');
		$this->write_file = fopen($this->save_file,'r+');
		$this->max_data_limit = 20;
		$this->log_id = $log_id;
	}

	public function start(){

		wp2pcloudLog::info($this->log_id,"Starting Database Backup");

		if(self::test_mysqldump() == true) {
			wp2pcloudLog::info($this->log_id," mysql_dump was successful!");
			wp2pcloudLog::info($this->log_id," MysqlDump file size: ".filesize($this->save_file).' bytes ('.$this->formatBytes(filesize($this->save_file)).')');
			return $this->save_file;
		} else {
			/*wp2pcloudLog::info($this->log_id," Didn't found mysql_dump, will backup with php ");*/

			require_once ( dirname(dirname(__FILE__)) . '/classes/lib/MysqlDumpFactory.php');

			/*$dsn = sprintf("mysql:host=%s;dbname=%s", DB_HOST, DB_NAME);*/
			$dump = MysqlDumpFactory::makeMysqlDump(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
			$dump->setFileName($this->save_file);
            $dump->export();
            wp2pcloudLog::info($this->log_id,"Database Backup Finished");
			return $this->save_file;
		}
	}

	
	private function test_mysqldump() {
		return false;
/*		$cmd = "mysqldump -h".DB_HOST." -u ".DB_USER." --password=".DB_PASSWORD." --skip-comments ".DB_NAME." > ".$this->save_file;
		exec($cmd,$out);
		if(file_exists($this->save_file) && filesize($this->save_file) != 0) {
			return true;
		}
		return false;*/
	}


	private function formatBytes($bytes, $dec = 2) {
		$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);

		return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}
}