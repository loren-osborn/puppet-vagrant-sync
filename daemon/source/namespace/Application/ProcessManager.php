<?php
namespace LinuxDr\VagrantSync\Application;

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
		$appName =  $this->getAppName();
		return "/tmp/{$appName}/{$appName}.pid";
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
}
