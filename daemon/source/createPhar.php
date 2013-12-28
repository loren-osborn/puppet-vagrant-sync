<?php

$srcRoot = dirname(__FILE__);
$buildRoot = dirname($srcRoot) . DIRECTORY_SEPARATOR . "build";
$archiveBaseName = "vagrant_sync";
$archiveName = "{$archiveBaseName}.phar";
$archivePath = $buildRoot . DIRECTORY_SEPARATOR . $archiveName;

if (file_exists($archivePath)) {
	if (!unlink($archivePath)) {
		throw new Exception("Unable to delete file $archivePath");
	}
}
$phar = new Phar(
	$archivePath, 
	(FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME),
	$archiveName
);
$phar["run.php"] = file_get_contents($srcRoot . DIRECTORY_SEPARATOR . "run.php");
$phar["autoloader.php"] = file_get_contents($srcRoot . DIRECTORY_SEPARATOR . "autoloader.php");
$phar->setStub($phar->createDefaultStub("run.php"));