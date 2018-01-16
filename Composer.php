<?php namespace Monolog;

class Composer
{
	public static function autoloadNamespaces(\XF\App $app, $prepend = false)
	{
		$namespaces = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_namespaces.php';

		if (!file_exists($namespaces))
		{
			$app->error()->logError('Missing vendor autoload files at %s', $namespaces);
		}
		else
		{
			$map = require $namespaces;

			foreach ($map as $namespace => $path) {
				\XF::$autoLoader->add($namespace, $path, $prepend);
			}
		}
	}

	public static function autoloadPsr4(\XF\App $app, $prepend = false)
	{
		$psr4 = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_psr4.php';

		if (!file_exists($psr4))
		{
			$app->error()->logError('Missing vendor autoload files at %s', $psr4);
		}
		else
		{
			$map = require $psr4;

			foreach ($map as $namespace => $path) {
				\XF::$autoLoader->addPsr4($namespace, $path, $prepend);
			}
		}
	}

	public static function autoloadClassmap(\XF\App $app)
	{
		$classmap = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';

		if (!file_exists($classmap))
		{
			$app->error()->logError('Missing vendor autoload files at %s', $classmap);
		}
		else
		{
			$map = require $classmap;

			if ($map)
			{
				\XF::$autoLoader->addClassMap($map);
			}
		}
	}

	public static function autoloadFiles(\XF\App $app)
	{
		$files = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_files.php';

		if (file_exists($files))
		{
			$includeFiles = require $files;

			foreach ($includeFiles as $fileIdentifier => $file)
			{
				if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
					require $file;

					$GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
				}
			}
		}
	}
}