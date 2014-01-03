<?php

spl_autoload_register(function ($className)
	{
		$applicationFileNamePrefix  = Phar::running() . '/namespace/';
		$namespacePrefix = 'LinuxDr\\VagrantSync\\';
		$fileName 	= '';
		$namespace = '';
		if (false !== ($lastNsPos = strripos($className, '\\'))) {
			$namespace = substr($className, 0, $lastNsPos);
			$className = substr($className, $lastNsPos + 1);
			$fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
		}
		$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		if ($namespacePrefix === substr($namespace . '\\', 0, strlen($namespacePrefix))) {
			$pathToLoad = $applicationFileNamePrefix . substr($fileName, strlen($namespacePrefix));
			if (file_exists($pathToLoad)) {
				include $pathToLoad;
			} else {
				throw new Exception("Missing file $pathToLoad");
			}
		}
	});

require_once Phar::running() . '/vendor/autoload.php';