<?php
class wp2pcloudFilesBackup {
	private $save_file, $write_filename, $sql_backup_file;

	public function __construct($log_id) {
		$this->log_id = $log_id;
		$this->save_file = "archive.zip";
		$this->write_filename = tempnam ( sys_get_temp_dir (), 'archive' );

		wp2pcloudLog::info($this->log_id," Start with file backup, temp filename: ".$this->write_filename);
	}

	private function formatBytes($bytes, $dec = 2) {
		$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);

		return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}

	public function setArchiveName($name) {
		$this->save_file = ($name != "") ? $name : $this->save_file;
		$this->save_file = preg_replace ( '@(https://)|(http://)@', '', $this->save_file );
		$this->save_file = str_replace ( " ", "_", $this->save_file );
		$this->save_file = str_replace ( "/", "_", $this->save_file );
	}
	public function setMysqlBackUpFileName($name) {
		$this->sql_backup_file = $name;
	}
	public function start() {
		$dirs = self::find_all_files ( rtrim ( ABSPATH, '/' ) );
		wp2pcloudLog::info($this->log_id," Found ".count($dirs). " files");
        $this->create_zip ( $dirs );

		wp2pcloudLog::info($this->log_id,"Zip file is created! Uploading to pCloud");
		$pcloud_info = self::send_to_pcloud ();
		wp2pcloudLog::info($this->log_id,"Uploaded to pCloud");

		if(isset($pcloud_info['metadata'][0]['fileid'])) {
			wp2pcloudLog::info($this->log_id,"pCloud file id: ".$pcloud_info['metadata'][0]['fileid']);
		}

		wp2pcloudLog::info($this->log_id,"Backup is completed!");
	}

	private function find_all_files($dir) {
		$root = scandir ( $dir );
		$result =  array();
		foreach ( $root as $value ) {
			if ($value === '.' || $value === '..') {
				continue;
			}
			if (is_file ( "$dir/$value" )) {
				$result [] = "$dir/$value";
				continue;
			}

			foreach ( self::find_all_files ( "$dir/$value" ) as $value ) {
				$result [] = $value;
			}
		}
		return $result;
	}
	private function create_zip($files) {
		wp2pcloudLog::info($this->log_id,"Starting with creating ZIP files");

		$zip = new ZipArchive ();
		$zip->open ( $this->write_filename, ZIPARCHIVE::CREATE );
		$zip->setArchiveComment ( "Wordpress2pCloud" );
		foreach ( $files as $el ) {
			$lname = str_replace(ABSPATH, "", $el);
			$zip->addFile ( $el,$lname);
			/*wp2pcloudLog::info($this->log_id,"Added ".$lname);*/
		}
		if ($this->sql_backup_file != false) {
			$zip->addFile ( $this->sql_backup_file, 'backup.sql' );
			wp2pcloudLog::info($this->log_id,"Added ".$this->sql_backup_file);
		}
		$zip->close ();

		wp2pcloudLog::info($this->log_id,"Backup file size:  ".filesize($this->write_filename).' bytes ('.$this->formatBytes(filesize($this->write_filename)).')');
	}
	private function makeDirectory($dir_name = "/WORDPRESS_BACKUPS") {
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)" );
		curl_setopt ( $ch, CURLOPT_URL, 'http://api.pcloud.com/createfolder?path='.$dir_name.'&name='.trim($dir_name,'/').'&auth=' . wp2pcloud_getAuth () );
		$response = curl_exec ( $ch );
		$response = @json_decode ( $response );
		curl_close ( $ch );
		return $response;
	}

	private function getUploadDirId() {
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)" );
		curl_setopt ( $ch, CURLOPT_URL, 'http://api.pcloud.com/listfolder?path=/'.PCLOUD_BACKUP_DIR.'&auth=' . wp2pcloud_getAuth () );
		$response = curl_exec ( $ch );
		$response = @json_decode ( $response );
		curl_close ( $ch );
		$folder_id = false;
		if ($response->result == 2005) {
				$folders = explode("/", PCLOUD_BACKUP_DIR);
				self::makeDirectory ("/".$folders[0]);
				$res = self::makeDirectory ("/".$folders[0]."/".$folders[1]);
			$folder_id = $res->metadata->folderid;
		} else {
			$folder_id = $response->metadata->folderid;
		}

		wp2pcloudLog::info($this->log_id,"Folder id ".$folder_id);

		return $folder_id;
	}

	private function getCurlValue($filename, $contentType, $postname)
	{
		// PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
		// See: https://wiki.php.net/rfc/curl-file-upload
		if (function_exists('curl_file_create')) {
			return curl_file_create($filename, $contentType, $postname);
		}

		// Use the old style if using an older version of PHP
		$value = "@{$filename};filename=" . $postname;
		if ($contentType) {
			$value .= ';type=' . $contentType;
		}

		return $value;
	}

	private function rename_cloud_file($current_file_id,$path,$folder_id) {
		$url = 'https://api.pcloud.com/renamefile?auth='.wp2pcloud_getAuth().'&fileid='.$current_file_id.'&toname='.$this->save_file;
		$ch = curl_init ();

		$options = array(CURLOPT_URL => $url,
		                 CURLOPT_RETURNTRANSFER => true,
		                 CURLINFO_HEADER_OUT => true, //Request header
		                 CURLOPT_HEADER => true, //Return header
		                 CURLOPT_SSL_VERIFYPEER => false, //Don't veryify server certificate
		                 CURLOPT_USERAGENT => "Mozilla/4.0 (compatible;)"
		);
		curl_setopt_array($ch, $options);
		$response = curl_exec ( $ch );

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$body = substr($response, $header_size);
		curl_close($ch);

		return $body;
	}

	private function send_to_pcloud() {
		if (! file_exists ( $this->write_filename )) {
			echo "File don't exist";
			return false;
		}

		// try with curl exec
		$folder_id = self::getUploadDirId ();

		rename($this->write_filename,$this->write_filename.'.zip');
		$this->write_filename = $this->write_filename.'.zip';


		$cmd = "curl -F \"auth=".wp2pcloud_getAuth()."\" -F \"folderid=".$folder_id."\" -F \"file=@".$this->write_filename."\" https://api.pcloud.com/uploadfile";
		exec($cmd,$res);
		if(!empty($res)) {
			$res = implode("",$res);
			$res = json_decode($res,true);
		}
		if(isset($res['metadata'])) {
			$this->rename_cloud_file($res['metadata'][0]['fileid'],PCLOUD_BACKUP_DIR,$folder_id);
			unlink ( $this->write_filename );
			return $res;
		}


		$url = 'https://api.pcloud.com/uploadfile?auth='.wp2pcloud_getAuth().'&folderid='.$folder_id;

		$cfile = $this->getCurlValue($this->write_filename,'application/zip',$this->write_filename);
		$data = array('file' => $cfile,'filename'=>$this->save_file);


		$ch = curl_init ();

		$options = array(CURLOPT_URL => $url,
		                 CURLOPT_RETURNTRANSFER => true,
		                 CURLINFO_HEADER_OUT => true, //Request header
		                 CURLOPT_HEADER => true, //Return header
		                 CURLOPT_SSL_VERIFYPEER => false, //Don't veryify server certificate
		                 CURLOPT_POST => true,
						CURLOPT_USERAGENT => "Mozilla/4.0 (compatible;)",
		                 CURLOPT_POSTFIELDS => $data
		);
		curl_setopt_array($ch, $options);
		$response = curl_exec ( $ch );

		$header_info = curl_getinfo($ch,CURLINFO_HEADER_OUT);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
		curl_close($ch);
		unlink ( $this->write_filename );

		$data = json_decode($body,true);

		if(isset($data['metadata'][0])) {
			$this->rename_cloud_file($data['metadata'][0]['fileid'],PCLOUD_BACKUP_DIR,$folder_id);
		}

		return $data;
	}
}