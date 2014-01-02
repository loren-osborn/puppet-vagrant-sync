<?php

$srcRoot = dirname(__FILE__);
$buildRoot = dirname($srcRoot) . DIRECTORY_SEPARATOR . "build";
$archiveBaseName = "vagrant_sync";
$archiveName = "{$archiveBaseName}.phar";
$archivePath = $buildRoot . DIRECTORY_SEPARATOR . $archiveName;

if (ini_get('phar.readonly')) {
	echo 'phar.readonly must be set to false before running this script!';
	exit(2);
}
$filesToAdd = array();
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
$phar->startBuffering();
$phar->setSignatureAlgorithm(Phar::SHA512);
$phar->setStub(file_get_contents($srcRoot . DIRECTORY_SEPARATOR . "stub.php"));
$phar->buildFromDirectory($srcRoot, ',/(vendor/.*|(autoloader|namespace/.*)\.php)$,');
$phar->compressFiles(Phar::BZ2);
$phar->stopBuffering();

chmod($archivePath, 0755);