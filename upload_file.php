<?php

require '../vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$key = $_ENV['IAM_KEY'];
$secret =  $_ENV['IAM_SECRET'];


$data = substr($_POST['data'], strpos($_POST['data'], ",") + 1);
$decodedData = base64_decode($data);
$filename = $_POST['fname'];
$fp = fopen($filename, 'wb');
fwrite($fp, $decodedData);
fclose($fp);



// AWS Info
$bucketName = 'amicusaudio';
$IAM_KEY = $key;
$IAM_SECRET = $secret;

// Connect to AWS
try {
	// You may need to change the region. It will say in the URL when the bucket is open
	// and on creation.
	$s3 = S3Client::factory(
		array(
			'credentials' => array(
				'key' => $IAM_KEY,
				'secret' => $IAM_SECRET
			),
			'version' => 'latest',
			'region'  => 'us-east-2'
		)
	);
} catch (Exception $e) {
	// We use a die, so if this fails. It stops here. Typically this is a REST call so this would
	// return a json object.
	die("Error: " . $e->getMessage());
}


// For this, I would generate a unqiue random string for the key name. But you can do whatever.
$keyName = $filename;
$pathInS3 = 'https://s3.us-east-2.amazonaws.com/' . $bucketName . '/' . $keyName;

// Add it to S3
try {
	// Uploaded:
	$file = realpath(dirname(__FILE__))."\\".$keyName;


	$s3->putObject(
		array(
			'Bucket'=>$bucketName,
			'Key' =>  $keyName,
			'SourceFile' => $file,
			'StorageClass' => 'REDUCED_REDUNDANCY',
			'ACL'    => 'public-read'
		)
	);

} catch (S3Exception $e) {
	die('Error:' . $e->getMessage());
} catch (Exception $e) {
	die('Error:' . $e->getMessage());
}



try {
	$url = "https://c09d96g177.execute-api.us-east-2.amazonaws.com/default/audio_test";
	$data = file_get_contents($url); // put the contents of the file into a variable
	$characters = json_decode($data, true); // decode the JSON feed
	echo $characters; 
} catch (Exception $e) {
	echo "Refresh page and try again";
}


// Now that you have it working, I recommend adding some checks on the files.
// Example: Max size, allowed file types, etc.
?>
