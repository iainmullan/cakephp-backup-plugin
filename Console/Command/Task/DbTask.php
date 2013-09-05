<?php
App::uses('AppShell', 'Console/Command');
class DbTask extends AppShell {
	
	var $mysqlBinDir = '';
	var $commandLineArgs = '';
	
	function __construct() {
		parent::__construct();
		if (Configure::read('backup.mysql_bin_dir')) {
			$mysqlBinDir = Configure::read('backup.mysql_bin_dir');
		}
	}

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
		
		$password = '';
		if (!empty($dsc['password'])) {
			$password = "-p{$dsc['password']}";
		}

		$command = $this->mysqlBinDir."mysqldump -u {$dsc['login']} $password {$dsc['database']} > $filename ";
		$this->out($command);
		
		$this->_exec($command);

		return $filename;
	}

	function load($ds, $filename) {
		$dsc = $this->_config($ds);
		
		$password = '';
		if (!empty($dsc['password'])) {
			$password = "-p{$dsc['password']}";
		}
		
		$command = $this->mysqlBinDir."mysql -u {$dsc['login']} $password {$dsc['database']} < $filename ";
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
