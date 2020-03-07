<?php
switch ($_GET['step']) {
case "1":
	try {
		file_put_contents('ff-boka-master.zip', fopen('https://github.com/d-tamm/ff-boka/archive/master.zip', 'r'));
	} catch (Exception $e) {
		echo "Something went wrong.";
		var_dump($e);
	}
	break;
case "2":
	try {
		$zip = new ZipArchive;
		if ($zip->open('ff-boka-master.zip') === TRUE) {
			$zip->extractTo('.');
			$zip->close();
		} 
	} catch (Exception $e) {
		echo "Something went wrong.";
		var_dump($e);
	}
	break;
case "3":
	unlink('ff-boka-master.zip');
	break;
default:
}

