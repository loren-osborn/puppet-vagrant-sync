<?php
namespace LinuxDir\VagrantSync\Test\Archive;

use PHPUnit_Framework_TestCase;
use Phar;
use Exception;
use DirectoryIterator;
use PharFileInfo;

require_once "PHPUnit/Autoload.php";

class ArchiveTest extends PHPUnit_Framework_TestCase
{
	const BUILD_DIR_NAME = 'build';
	const TEMP_NAME_SUFFIX = '_real';
	const ARCHIVE_FILE_NAME = 'vagrant_sync.phar';

	private $projectPath;
	private $runningCwd;
	private $tempDirMap;

	function __construct()
	{
		$this->projectPath = dirname(dirname(dirname(__FILE__)));
		$this->tempDirMap = array();
	}

	private function recursiveUnlink($path, $ignoreMissing = true)
	{
		if (!file_exists($path)) {
			if ($ignoreMissing) {
				return;
			}
			throw new Exception('Directory doesn\'t exist.');
		} elseif (!is_dir($path)) {
			if (!unlink($path)) {
				throw new Exception("Error deleting file: " . $path);
			}
		} else {
			$directoryIterator = new DirectoryIterator($path);

			foreach ($directoryIterator as $fileInfo) {
				if (!$fileInfo->isDot()) {
					$this->recursiveUnlink($fileInfo->getPathname(), false);
				}
			}
			if (!rmdir($path)) {
				throw new Exception("Error deleting directory: " . $path);
			}
		}
	}

	private function getDirPath($dirName)
	{
		return ($this->projectPath . DIRECTORY_SEPARATOR . $dirName);
	}

	private function getRealDirTempPath($dirName)
	{
		return ($this->projectPath . DIRECTORY_SEPARATOR . $dirName . self::TEMP_NAME_SUFFIX);
	}

	private function getArchivePath()
	{
		return ($this->getDirPath(self::BUILD_DIR_NAME) . DIRECTORY_SEPARATOR . self::ARCHIVE_FILE_NAME);
	}

	private function getArchivePathAsUrl()
	{
		$nativePath = $this->getArchivePath();
		$noLeadingSlash = preg_replace(',^[\\\\/]*,', '', $nativePath);
		$urlSlashes = str_replace(DIRECTORY_SEPARATOR, '/', $noLeadingSlash);
		return ("phar:///" . $urlSlashes);
	}

	private function saveRealDir($dirName)
	{
		if (array_key_exists($dirName, $this->tempDirMap)) {
			throw new Exception("$dirName already in tempDirMap");
		}
		$this->tempDirMap[$dirName] = $dirName;
		if (!is_dir($this->getRealDirTempPath($dirName))) {
			$this->recursiveUnlink($this->getRealDirTempPath($dirName));
			if (file_exists($this->getDirPath($dirName))) {
				if (!rename($this->getDirPath($dirName), $this->getRealDirTempPath($dirName))) {
					throw new Exception("Error renaming " . $this->getDirPath($dirName) . " to " . $this->getRealDirTempPath($dirName));
				}
			}
		}
		$this->recursiveUnlink($this->getDirPath($dirName));
		if (!mkdir($this->getDirPath($dirName), 0755 )) {
			throw new Exception("Error creating directory: " . $this->getDirPath($dirName));
		}
	}

	private function restoreRealDirs()
	{
		foreach (array_keys($this->tempDirMap) as $dirName) {
			if (is_dir($this->getRealDirTempPath($dirName))) {
				$this->recursiveUnlink($this->getDirPath($dirName));
				if (!rename($this->getRealDirTempPath($dirName), $this->getDirPath($dirName))) {
					throw new Exception("Error renaming " . $this->getRealDirTempPath($dirName) . " to " . $this->getDirPath($dirName));
				}
			}
		}
	}

	public function setUp()
	{
		$this->saveRealDir(self::BUILD_DIR_NAME);
		$this->runningCwd = getcwd();
	}

	public function tearDown()
	{
		$this->restoreRealDirs();
		if (!chdir($this->runningCwd)) {
			throw new Exception("Error restoring CWD to {$this->runningCwd}");
		}
	}

	public function testSetupTeardown()
	{
		$this->assertTrue(is_dir($this->getRealDirTempPath(self::BUILD_DIR_NAME)), "Real build dir moved to " . $this->getRealDirTempPath(self::BUILD_DIR_NAME));
		$this->assertTrue(is_dir($this->getDirPath(self::BUILD_DIR_NAME)), "Fake build dir reated at " . $this->getDirPath(self::BUILD_DIR_NAME));
		$this->assertEquals(array('.', '..'), scandir($this->getDirPath(self::BUILD_DIR_NAME)), "Fake build dir empty");
	}

	public function testMakeGeneratesPhar()
	{
		$this->assertFalse(file_exists($this->getArchivePath()), "No archive file yet");
		$this->assertTrue(Phar::isValidPharFilename($this->getArchivePath()), "Archive filename is valid");
		if (!chdir($this->projectPath)) {
			throw new Exception("Error setting CWD to {$this->projectPath}");
		}
		system("make " . self::BUILD_DIR_NAME . DIRECTORY_SEPARATOR . self::ARCHIVE_FILE_NAME . " 2> /dev/null > /dev/null");
		$this->assertTrue(file_exists($this->getArchivePath()), "Archive file created");
		$pharFile = new Phar($this->getArchivePath());
		$this->assertTrue($pharFile->isFileFormat(Phar::PHAR), "Archive file in correct format");
		$pharInfo = new PharFileInfo($this->getArchivePathAsUrl() . '/run.php');
		$this->assertTrue($pharInfo->isCRCChecked(), "Archive file CRC matches");
	}
}
