<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\HandlerInterface;

class Monolog_Helper_Handler
{
	public static function createStream($filename = "", $level = 0)
	{
		if (empty($filename)) $filename = Monolog_Option_Channel::getDefault() . ".log";
		if ($level == 0) $level = Monolog_Option_Loglevel::get();

		return new StreamHandler(
			XenForo_Helper_File::getInternalDataPath() . '/' . $filename,
			$level
		);
	}

	public static function setDefault(HandlerInterface $handler)
	{
		XenForo_Application::set("monolog-default-handler", $handler);
	}

	public static function getDefault()
	{
		return XenForo_Application::get("monolog-default-handler");
	}
}
