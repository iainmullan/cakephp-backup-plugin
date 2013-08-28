<?php
App::uses('AppShell', 'Console/Command');
class DbTask extends AppShell {

	function dump($ds = null, $filename = false) {

		if ($ds == null) {
			$ds = $this->args[0];
		}
		$dsc = $this->_config($ds);

		if (!$filename) {

			$name = Configure::read('backup.name');
			if (!$name) {
				$name = $dsc['database'];
			}

			$fn = $name.'__'.php_uname('n').'__'.date('Y-m-d-H-i').'.sql';
			$filename = TMP.$fn;
		}

		@unlink($filename);

		$this->out('Dumping database: '.$dsc['database']);

		$command = "/usr/bin/mysqldump -u {$dsc['login']} -p{$dsc['password']} {$dsc['database']} > $filename ";
		$this->_exec($command);

		return $filename;
	}

	function load($ds, $filename) {
		$dsc = $this->_config($ds);
		$command = "/usr/bin/mysql -u {$dsc['login']} -p{$dsc['password']} {$dsc['database']} < $filename ";
		$this->_exec($command);
	}

	function _exec($command) {
		$output = '';
		exec($command, $output);
		$this->out($output);
	}

	function _config($ds) {
        App::import('Model', 'ConnectionManager');
		$configs = ConnectionManager::enumConnectionObjects();
        $dsc = $configs[$ds];
		return $dsc;
	}

}
