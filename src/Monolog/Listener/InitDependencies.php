<?php

class Monolog_Listener_InitDependencies
{
	public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		// create our default handler
		Monolog_Helper_Handler::setDefault(Monolog_Helper_Handler::createStream());

		// create our default logging channel
		Monolog_Helper_Log::create(Monolog_Option_Channel::getDefault());
	}
}