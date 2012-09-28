<?php
App::uses('AppShell', 'Console/Command');
class DbTask extends AppShell {

	function dump($ds = null) {

		if ($ds == null) {
			$ds = $this->args[0];
		}
		$dsc = $this->_config($ds);

		$fn = $dsc['database'].'__'.php_uname('n').'__'.date('Y-m-d-H-i').'.sql';

		$tmpFile = TMP.$fn;

		@unlink($tmpFile);

		$this->out('Dumping database: '.$dsc['database']);

		$command = "mysqldump -u {$dsc['login']} -p{$dsc['password']} {$dsc['database']} > $tmpFile ";
		$this->_exec($command);

		return $tmpFile;
	}

	function load($ds, $filename) {
		$dsc = $this->_config($ds);
		$command = "mysql -u {$dsc['login']} -p{$dsc['password']} {$dsc['database']} < $filename ";
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
