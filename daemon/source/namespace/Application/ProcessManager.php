<?php
namespace LinuxDr\VagrantSync\Application;

use LinuxDr\VagrantSync\Exception\InternalErrorException;

class ProcessManager
{
	public function getDaemonClass() {
		return 'System_Daemon';
	}

	public function getAppName() {
		return 'vagrant_sync';
	}

	public function getAppRunDir() {
		return '/tmp/' . $this->getAppName();
	}

	public function getAppPidFile() {
		return $this->getAppRunDir() . '/' . $this->getAppName() . '.pid';
	}

	public function __call($name, $arguments)
	{
		$matches = array();
		if (preg_match('/^daemon(.*)$/', $name, $matches)) {
			$staticMethodName = lcfirst($matches[1]);
			$className = $this->getDaemonClass();
			return call_user_func_array(array($className, $staticMethodName), $arguments);
		}
		throw new InternalErrorException('Call to undefined method ' . __CLASS__ . "::{$name}()");
	}

	public function getRunningPid() {
		$retVal = null;
		if (file_exists($this->getAppPidFile())) {
			$pidFileContents = file_get_contents($this->getAppPidFile());
			$matches = array();
			if (preg_match('/^(0|[1-9]\d*)$/m', $pidFileContents, $matches)) {
				$output = null;
				$exitCode = null;
				exec("ps {$matches[1]}", $output, $exitCode);
				if ($exitCode == 0) {
					$retVal = ($matches[1] + 0);
				}
			}
		}
		return $retVal;
	}

	public function isAlreadyRunning() {
		return !is_null($this->getRunningPid());
	}
}
