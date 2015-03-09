<?php

class Monolog_Listener_InitDependencies
{
	public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		$autoload = Monolog_Addon::autoloadPath();

		if (!file_exists($autoload))
		{
			$message = 'Missing vendor autoload files at ' . $autoload;

			if ($dependencies instanceof XenForo_Dependencies_Admin)
			{
				XenForo_Error::logError($message);
			}
			else
			{
				throw new XenForo_Exception($message);
			}
		}
		else
		{
			// load the root Composer autoload file
			require_once $autoload;

			// create our default handler
			Monolog_Helper_Handler::setDefault(Monolog_Helper_Handler::createStream());

			// create our default logging channel
			Monolog_Helper_Log::create(Monolog_Option_Channel::getDefault());
		}
	}
}