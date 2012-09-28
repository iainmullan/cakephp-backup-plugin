<?php
class CloudfilesTask extends Shell {

	var $cf;
	var $bucket;

	function __construct($a) {
		parent::__construct($a);

		include(APP.'Plugin/Backup/Vendors/rackspace-cloudfiles/cloudfiles.php');

		// RACKSPACE cloudfiles
		$auth = new CF_Authentication(Configure::read('backup.cloudfiles.username'), Configure::read('backup.cloudfiles.api_key'), null, UK_AUTHURL);
		$auth->authenticate();

		$conn = new CF_Connection($auth);
		$conn->ssl_use_cabundle();

		$this->cf = $conn;
		$this->bucket = $this->cf->get_container('backups');
	}

	function upload($full_path, $file_name, $containerName) {

		$file = $this->bucket->create_object($file_name);

		$file->load_from_filename($full_path);

		$uri = $this->bucket->make_public();

	   	return $file->public_ssl_uri();
	}

	function ls($containerName) {

		$items = $this->bucket->list_objects();

		return $items;
	}

	// function upload($sourcePath, $destinationPath, $bucketName) {
	// 	$fileData = file_get_contents($sourcePath);
	// 	$object = array(
	// 		'filename' => $destinationPath,
	// 		'body' => $fileData
	// 	);
	//
	// 	include_once(APP.'vendors/cloudfusion/cloudfusion.class.php');
	// 	include_once(APP.'vendors/cloudfusion/s3.class.php');
	// 	$_key = Configure::read('s3.key');
	// 	$_secret = Configure::read('s3.secret');
	// 	$this->s3 = new AmazonS3($_key, $_secret);
	//
	// 	$result = $this->s3->create_object('3dme.'.$bucketName, $object);
	// }

}
?>