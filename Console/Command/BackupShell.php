<?php
App::uses('AppShell', 'Console/Command');
class BackupShell extends AppShell {

	var $tasks = array('Backup.Db', 'Backup.Cloudfiles');

	function main() {

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

		$i = 1;
		foreach($contents as $k => $v) {
			$options[$i] = $v;
			$this->out("[$i] {$v}");
			$i++;
		}

		$chosen = $this->in('Choose a backup file:', array_keys($options));

		$this->out("You chose number $chosen");

		$filename = $options[$chosen];

		$file = $this->Cloudfiles->bucket->get_object($filename);

		$filename = preg_replace('/\//', '__', $filename);
		$path = APP.'tmp/'.$filename;
		$file->save_to_filename($path);


		// Backup current contents, as a precaution
		$this->out("Dumping current local DB...");
		$this->Db->dump($ds);

		$this->out("Loading $filename...");
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

		$send = Configure::read('backup.send');
		if ($send) {

			$remoteName = preg_replace('/__/', '/', basename($filename));

			$this->out('Uploading to Cloudfiles...', true);
			$start = time();
			$this->Cloudfiles->upload($filename, $remoteName, 'backups');
			$took = time() - $start;
			$this->out('...upload completed in '.$took.' seconds.', true);

			$discard = Configure::read('backup.discard');
			if ($discard=='y') {
				$this->out('Deleting local file...', true);
				unlink($filename);
			}

			$this->out('Backup complete.', true);
		}

	}

}
