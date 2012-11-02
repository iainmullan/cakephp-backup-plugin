<?php
App::uses('AppShell', 'Console/Command');
class BackupShell extends AppShell {

	private $_config = array(
		'send' => false,
		'discard' => false
	);

	var $tasks = array('Backup.Db', 'Backup.Cloudfiles');

	function _init() {

		$config = Configure::read('backup');

		if ($config) {
			$this->_config = array_merge($this->_config, $config);
		}

		$this->out("Config is ");
		pr($this->_config);

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

	function restore() {

		$ds = 'default';
		if (isset($this->args[0])) {
			$ds = $this->args[0];
		}

		$contents = $this->Cloudfiles->bucket->list_objects();

		if (empty($contents)) {
			$this->out("No backups available");
			exit();
		}

		$name = Configure::read('backup.name');
		$contents = preg_grep('/^'.$name.'.*/', $contents);

		$i = 1;
		foreach($contents as $k => $v) {
			$options[$i] = $v;
			$this->out("[$i] {$v}");
			$i++;
		}

		$chosen = $this->in('Choose a Backup File:', array_keys($options));

		$this->out("You chose number $chosen");

		$filename = $options[$chosen];

		$file = $this->Cloudfiles->bucket->get_object($filename);

		$filename = preg_replace('/\//', '__', $filename);

		$path = APP.'tmp/'.$filename;
		$file->save_to_filename($path);

		// Backup current contents, as a precaution
		// $this->out("Dumping current local DB...");
		// $this->Db->dump($ds);

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

		$filename = $this->Db->dump($ds);

		if (Configure::read('backup.compress')) {
			$this->create_zip(array($filename), $filename.'.zip');
			$filename = $filename.'.zip';
		}

		$send = Configure::read('backup.send');
		if ($send) {

			foreach($send as $type => $options) {

				$method = 'send_'.$type;
				if (method_exists($this, $method)) {
					$this->$method($filename, $options);
				}

			}

			$this->out('Backup complete.', true);
		} else {
			// it has been sent anywhere, force a local save
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

	}

	function send_email($filename, $options) {
		$this->out('Emailing to '.$options['to'].'...', true);
		$email = new CakeEmail();
		$email->from($options['from']);
		$email->to($options['to']);
		$email->subject($options['subject']);
		$email->attachments($filename);
		$email->send('Backup attached');
	}

	function send_cloudfiles($filename, $options) {
		$remoteName = preg_replace('/__/', '/', basename($filename));

		$this->out('Uploading to Cloudfiles...', true);
		$start = time();
		$this->Cloudfiles->upload($filename, $remoteName, $options['container']);
		$took = time() - $start;
		$this->out('...upload completed in '.$took.' seconds.', true);

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
