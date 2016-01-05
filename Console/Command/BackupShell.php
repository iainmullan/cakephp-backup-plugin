<?php
App::uses('AppShell', 'Console/Command');
class BackupShell extends AppShell {

	private $_config = array(
		'send' => false,
		'discard' => false
	);

	var $tasks = array('Backup.Db', 'Backup.Cloudfiles', 'Backup.S3');

	function _init() {

		$config = Configure::read('backup');

		if ($config) {
			$this->_config = array_merge($this->_config, $config);
		}

	}

	function main() {

//		$this->_init();

		$options = array(
			'1' => 'Create a backup',
			'2' => 'Restore a backup'
		);
		foreach($options as $k=>$v) {
			$this->out(" $k. $v");
		}

		$response = $this->in('What do you want to do?', array_keys($options));

		switch($response) {
			case '1':
				$this->run();
				break;
			case '2':
				$this->restore();
				break;
		}

	}

	function latest() {
		$this->restore(false);
	}

	function restore($choose = true) {

		$ds = 'default';
		if (isset($this->args[0])) {
			$ds = $this->args[0];
		}

		$source = $this->{Configure::read('backup.source')};

		$contents = $source->ls();

		$name = Configure::read('backup.name');
		$contents = preg_grep('/^'.$name.'.*/', $contents);

		if (empty($contents)) {
			$this->out("No backups available for ".$name);
			$this->out();
			exit();
		}


		$i = 1;
		$options = array();
		foreach($contents as $k => $v) {
			$options[$i] = $v;
			$this->out("[$i] {$v}");
			$i++;
		}

		if ($choose) {
			$chosen = $this->in('Choose a Backup File:', array_keys($options));
		} else {
			$chosen = $i - 1;
		}

		$this->out("You chose number $chosen");

		$filename = $options[$chosen];

		$fileData = $source->get($filename);

		$path = APP.'tmp/'.$filename;

		$fh = fopen($path, 'w');
		fwrite($fh, $fileData);
		fclose($fh);

		$this->out("Loading $filename...");

		if (substr($path, '-4') == '.zip') {
			$this->out('Uncompressing...'.$path);
			$zip = new ZipArchive();
			$zip->open($path);

			$uncompressedPath = substr($path, 0, strlen($path)-4);
			$this->out($uncompressedPath);

			$zip->extractTo(APP.'tmp/', basename($uncompressedPath));

			unlink($path);

			$path = $uncompressedPath;

		} else {
//			echo 'NOT ZIPPED';
		}

//		exit();
		$this->Db->load('default', $path);

		// clean up tmp file
		unlink($path);
	}

	function run() {

		$ds = 'default';
		if (isset($this->args[0])) {
			$ds = $this->args[0];
		}

		$fn = Configure::read('backup.name').'_'.Configure::read('app.env').'_'.date('Y-m-d').'.sql';

		$filename = $this->Db->dump($ds, $fn);
		$oldFile = Configure::read('backup.name').'_'.Configure::read('app.env').'_'.date('Y-m-d', strtotime('-7 days')).'.sql';

		$this->out('Dumped file: '.$filename);

		if (Configure::read('backup.compress')) {
			$this->create_zip(array($filename), $filename.'.zip');
			unlink($filename);
			$filename = $filename.'.zip';
			$oldFile = $oldFile.'.zip';
		}

		$sources = Configure::read('backup.sources');
		if ($sources) {

			foreach($sources as $type => $options) {
				if ($options['enabled']) {
					$method = 'send_'.$type;
					if (method_exists($this, $method)) {
						$this->$method($filename, $options, $oldFile);
					}
				}
			}

			$this->out('Backup complete.', true);
		} else {
			// it hasn't been sent anywhere, force a local save
			if (!Configure::read('backup.dir')) {
				Configure::write('backup.dir', APP.'backups/');
			}
		}

		$backupDir = Configure::read('backup.dir');
		if ($backupDir) {
			if (!is_dir($backupDir)) {
				mkdir($backupDir);
				chmod($backupDir, 0777);
			}
			$backupFilename = $backupDir.basename($filename);
			rename($filename, $backupFilename);
		} else {
			unlink($filename);
		}

		$this->notify();
	}

	function notify() {

		$alerts = Configure::read('backup.alerts');

		foreach($alerts as $type => $config) {
			echo "Alert via ".$type;
			$method = "notify_$type";
			$this->$method($config);
		}

	}

	function notify_email($config) {
		$message = Configure::read('site.name')." Backup Complete";
	}

	function notify_sns($config) {

		include_once(APP.'Plugin/Backup/Vendors/aws/sdk.class.php');
		include_once(APP.'Plugin/Backup/Vendors/aws/services/sns.class.php');

		$sns = new AmazonSNS(array(
			'key' => $config['key'],
			'secret' => $config['secret'],
			'certificate_authority' => true
		));

		$message = Configure::read('site.name')." Backup Complete";

		$arn = $config['arn'];

		$r = $sns->publish($arn, $message);
	}

	function send_email($filename, $options) {
		$to = $options['to'];
		if (!is_array($to)) {
			$to = array($to);
		}

		$this->out('Emailing to '.implode(',',$to).'...', true);

		$email = new CakeEmail();
		$email->from($options['from']);
		$email->to($to);
		$email->subject($options['subject']);
		$email->attachments($filename);
		$email->send('Backup attached');
	}

	function send_cloudfiles($filename, $options) {
		$remoteName = preg_replace('/__/', '/', basename($filename));

		$this->out('Sending to Cloudfiles...', true);
		$start = time();
		$this->Cloudfiles->upload($filename, $remoteName, $options['container']);
		$took = time() - $start;
		$this->out('...send completed in '.$took.' seconds.', true);

		return true;
	}

	function send_s3($filename, $options, $oldFileToDelete = false) {
		$this->out('Sending to S3...', true);
		$start = time();
		$this->S3->upload($filename, basename($filename), $options['bucket']);
		$took = time() - $start;

		if ($oldFileToDelete) {
			$this->out('Deleting old backup: '.$oldFileToDelete, true);
			$this->S3->delete($oldFileToDelete, $options['bucket']);
		}

		$this->out('...send completed in '.$took.' seconds.', true);
		return true;
	}

	/* creates a compressed zip file */
	function create_zip($files = array(),$destination = '',$overwrite = false) {
	  //if the zip file already exists and overwrite is false, return false
	  if(file_exists($destination) && !$overwrite) { return false; }
	  //vars
	  $valid_files = array();
	  //if files were passed in...
	  if(is_array($files)) {
	    //cycle through each file
	    foreach($files as $file) {
	      //make sure the file exists
	      if(file_exists($file)) {
	        $valid_files[] = $file;
	      }
	    }
	  }
	  //if we have good files...
	  if(count($valid_files)) {
	    //create the archive
	    $zip = new ZipArchive();
	    if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
	      return false;
	    }
	    //add the files
	    foreach($valid_files as $file) {
	      $zip->addFile($file,basename($file));
	    }
	    //debug
	    //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;

	    //close the zip -- done!
	    $zip->close();

	    //check to make sure the file exists
	    return file_exists($destination);
	  }
	  else
	  {
	    return false;
	  }
	}

}
