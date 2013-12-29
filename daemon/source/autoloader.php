<?php

spl_autoload_register(function ($className)
	{
		$fileNamePrefix  = Phar::running() . '/namespace/';
		$namespacePrefix = 'LinuxDr\\VagrantSync\\';
		if ($namespacePrefix === substr($className, 0, strlen($namespacePrefix))) {
			$fileName 	= '';
			$namespace = '';
			if (false !== ($lastNsPos = strripos($className, '\\'))) {
				$namespace = substr($className, 0, $lastNsPos);
				$className = substr($className, $lastNsPos + 1);
				$fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
			}
			$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
			$pathToLoad = $fileNamePrefix . substr($fileName, strlen($namespacePrefix));

			if (file_exists($pathToLoad)) {
				include $pathToLoad;
			} else {
				throw new Exception("Missing file $pathToLoad");
			}
		}
	});