<?php
require_once "PHPUnit/Autoload.php";
 
class ArchiveTest extends PHPUnit_Framework_TestCase
{
    const BUILD_DIR_NAME = 'build';
    const REAL_BUILD_DIR_TEMP_NAME = 'build_real';
    const ARCHIVE_FILE_NAME = 'vagrant_sync.phar';
    
	private $projectPath;

	function __construct() {
		$this->projectPath = dirname(dirname(__FILE__));
	}
	
	private function recursiveUnlink($path, $ignoreMissing = true) {
		if (!file_exists($path)) {
			if ($ignoreMissing) {
				return;
			}
			throw new \Exception('Directory doesn\'t exist.');
		} elseif (!is_dir($path)) {
			if (!unlink($path)) {
				throw new \Exception("Error deleting file: " . $path);
			}
		} else {
			$directoryIterator = new \DirectoryIterator($path);

			foreach ($directoryIterator as $fileInfo) {
				if (!$fileInfo->isDot()) {
					$this->recursiveUnlink($fileInfo->getPathname(), false);
				}
			}
			if (!rmdir($path)) {
				throw new \Exception("Error deleting directory: " . $path);
			}
		}
	}
   
    private function getBuildDirPath()
    {
    	return ($this->projectPath . DIRECTORY_SEPARATOR . self::BUILD_DIR_NAME);
    }
   
    private function getRealBuildDirTempPath()
    {
    	return ($this->projectPath . DIRECTORY_SEPARATOR . self::REAL_BUILD_DIR_TEMP_NAME);
    }
   
    private function getArchivePath()
    {
    	return ($this->getBuildDirPath() . DIRECTORY_SEPARATOR . self::ARCHIVE_FILE_NAME);
    }
   
    private function saveRealBuildDir()
    {
    	if (!is_dir($this->getRealBuildDirTempPath())) {
    		$this->recursiveUnlink($this->getRealBuildDirTempPath());
    		if (file_exists($this->getBuildDirPath())) {
				if (!rename($this->getBuildDirPath(), $this->getRealBuildDirTempPath())) {
					throw new \Exception("Error renaming " . $this->getBuildDirPath() . " to " . $this->getRealBuildDirTempPath());
				}
    		}
    	}
    	$this->recursiveUnlink($this->getBuildDirPath());
		if (!mkdir($this->getBuildDirPath(), 0755 )) {
			throw new \Exception("Error creating directory: " . $this->getBuildDirPath());
		}
    }
   
    private function restoreRealBuildDir()
    {
    	if (is_dir($this->getRealBuildDirTempPath())) {
    		$this->recursiveUnlink($this->getBuildDirPath());
			if (!rename($this->getRealBuildDirTempPath(), $this->getBuildDirPath())) {
				throw new \Exception("Error renaming " . $this->getRealBuildDirTempPath() . " to " . $this->getBuildDirPath());
			}
    	}
    }
   
    public function setUp()
    {
        $this->saveRealBuildDir();
    }
   
    public function tearDown()
    {
        $this->restoreRealBuildDir();
    }
 
    public function testSetupTeardown()
    {
        $this->assertTrue(is_dir($this->getRealBuildDirTempPath()), "Real build dir moved to " . $this->getRealBuildDirTempPath());
        $this->assertTrue(is_dir($this->getBuildDirPath()), "Fake build dir reated at " . $this->getBuildDirPath());
        $this->assertEquals(array('.', '..'), scandir($this->getBuildDirPath()), "Fake build dir empty");
    }
}
