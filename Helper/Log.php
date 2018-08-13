<?php namespace Hampel\Monolog\Helper;

class Log
{
	public static function emergency($message, array $context = array())
	{
		return self::getMonolog()->emergency($message, $context);
	}

	public static function alert($message, array $context = array())
	{
		return self::getMonolog()->alert($message, $context);
	}

	public static function critical($message, array $context = array())
	{
		return self::getMonolog()->critical($message, $context);
	}

	public static function error($message, array $context = array())
	{
		return self::getMonolog()->error($message, $context);
	}

	public static function warning($message, array $context = array())
	{
		return self::getMonolog()->warning($message, $context);
	}

	public static function notice($message, array $context = array())
	{
		return self::getMonolog()->notice($message, $context);
	}

	public static function info($message, array $context = array())
	{
		return self::getMonolog()->info($message, $context);
	}

	public static function debug($message, array $context = array())
	{
		return self::getMonolog()->debug($message, $context);
	}

	public static function log($level, $message, array $context = array())
	{
		return self::getMonolog()->log($level, $message, $context);
	}

	public static function getMonolog($logger = 'default')
	{
		return \XF::app()->get('monolog')->get("logger.{$logger}");
	}
}
