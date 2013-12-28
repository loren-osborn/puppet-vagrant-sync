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
	const SOURCE_DIR_NAME = 'source';
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
		exec("make " . self::BUILD_DIR_NAME . DIRECTORY_SEPARATOR . self::ARCHIVE_FILE_NAME);
		$this->assertTrue(file_exists($this->getArchivePath()), "Archive file created");
		$pharFile = new Phar($this->getArchivePath());
		$this->assertTrue($pharFile->isFileFormat(Phar::PHAR), "Archive file in correct format");
		$pharInfo = new PharFileInfo($this->getArchivePathAsUrl() . '/autoloader.php');
		$this->assertTrue($pharInfo->isCRCChecked(), "Archive file CRC matches");
	}

	public function testLauncher()
	{
		$this->saveRealDir(self::SOURCE_DIR_NAME);
		foreach (array('stub.php', 'createPhar.php') as $file) {
			if (!copy(
				$this->getRealDirTempPath(self::SOURCE_DIR_NAME) . DIRECTORY_SEPARATOR . $file,
				$this->getDirPath(self::SOURCE_DIR_NAME) . DIRECTORY_SEPARATOR . $file
			)) {
				throw new Exception("Error copying file $file");
			}
		}
		$mockAutoloaderCode = <<<'MOCK_AUTOLOADER_EOF'
<?php
			echo '1';
			if (!class_exists('LinuxDr\\VagrantSync\\Application\\Runner', false)) {
				echo '2';
			} else {
				echo '*CLASS  LinuxDr\\VagrantSync\\Application\\Runner UNEXPECTEDLY EXISTS*';
			}

			function __autoload($class_name) {
				echo '4';
				if ($class_name == 'LinuxDr\\VagrantSync\\Application\\Runner') {
					echo '5';
					$class_def = <<<'MOCK_APP_RUNNER_EOF'
						namespace LinuxDr\VagrantSync\Application;

						echo '6';
						class Runner
						{
							function __construct()
							{
								echo '*UNEXPECTED CONSTRUCTOR CALL*';
							}

							public static function launch($argList)
							{
								echo '9:' . join('.', $argList) . '';
							}
						}
						echo '7';
MOCK_APP_RUNNER_EOF;
					eval($class_def);
					echo '8';
				} else {
					echo '*UNEXPECTED CLASS AUTOLOAD: ' . $class_name . '*';
				}
			}
			echo '3';
MOCK_AUTOLOADER_EOF;
		file_put_contents(
			$this->getDirPath(self::SOURCE_DIR_NAME) . DIRECTORY_SEPARATOR . 'autoloader.php',
			$mockAutoloaderCode
		);
		$this->assertFalse(file_exists($this->getArchivePath()), "No archive file yet");
		exec("make " . self::BUILD_DIR_NAME . DIRECTORY_SEPARATOR . self::ARCHIVE_FILE_NAME);
		$testOupput = exec($this->getArchivePath() . " a bc def");
		$this->assertEquals('123456789:' . $this->getArchivePath() . '.a.bc.def', $testOupput, "Expected stub output");
		$testOupput = exec($this->getArchivePath() . " xyz");
		$this->assertEquals('123456789:' . $this->getArchivePath() . '.xyz', $testOupput, "Expected stub output");
	}
}
