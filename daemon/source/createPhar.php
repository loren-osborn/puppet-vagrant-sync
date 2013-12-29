<?php

function recursiveFileAdd(&$filesToAdd, $startPath, $fromPath, $excludeList)
{
	$startPath = rtrim($startPath, '/\\');
	if (!file_exists($fromPath)) {
		throw new Exception('Directory doesn\'t exist.');
	} elseif (!is_dir($fromPath)) {
		$finalFileName = substr($fromPath, (strlen($startPath) + 1));
		if (!in_array($finalFileName, $excludeList) ) {
			$filesToAdd[$finalFileName] = $fromPath;
		}
	} else {
		$directoryIterator = new DirectoryIterator($fromPath);

		foreach ($directoryIterator as $fileInfo) {
			if (!$fileInfo->isDot()) {
				recursiveFileAdd($filesToAdd, $startPath, $fileInfo->getPathname(), $excludeList);
			}
		}
	}
}

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
recursiveFileAdd($filesToAdd, $srcRoot, $srcRoot, array('stub.php', 'createPhar.php'));
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
$phar->setStub(file_get_contents($srcRoot . DIRECTORY_SEPARATOR . "stub.php"));
foreach ($filesToAdd as $filename => $fullPath) {
	echo "Adding {$fullPath} as '{$filename}'...\n";
	$phar[$filename] = file_get_contents($fullPath);
}

chmod($archivePath, 0755);