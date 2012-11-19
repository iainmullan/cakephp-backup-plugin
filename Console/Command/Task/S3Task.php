<?php
include_once(APP.'Plugin/Backup/Vendors/aws/sdk.class.php');
include_once(APP.'Plugin/Backup/Vendors/aws/services/s3.class.php');
class S3Task extends Shell {

	var $s3;

	function __construct($a) {
		parent::__construct($a);

		$config = Configure::read('backup.send.s3');

		$this->config = $config;

		$this->s3 = new AmazonS3(array(
			'key' => $config['key'],
			'secret' => $config['secret']
		));
	}


	function upload($sourcePath, $destinationPath, $bucketName) {

		$fileData = file_get_contents($sourcePath);
		$object = array(
			'body' => $fileData
		);

		$bucketName = $this->config['bucket'];//.".s3-eu-west-1.amazonaws.com";

		$result = $this->s3->create_object($bucketName, $destinationPath, $object);

		return $result;
	}

	function ls() {
		$result = $this->s3->list_objects($this->config['bucket']);

		$items = array();
		foreach($result->body->Contents as $item) {
			$items[] = (string) $item->Key;
		}

		return $items;
	}

	function get($filename) {
		$file = $this->s3->get_object($this->config['bucket'], $filename);
		return $file->body;
	}


}

?>