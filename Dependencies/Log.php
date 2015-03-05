<?php

class Monolog_Dependencies_Log
{
	public static function registerDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		Monolog_Helper_Log::create();

		$handler = Monolog_Helper_Handler::createStream();
		Monolog_Helper_Handler::setDefault($handler);
	}

	public static function pushDefaultHandler(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		$logger = Monolog_Helper_Log::getDefault();
		$handler = Monolog_Helper_Handler::getDefault();

		if (!is_null($handler))
		{
			$logger->pushHandler($handler);
		}
	}
}