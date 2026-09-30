<?php namespace Hampel\Monolog\Helper;

use Psr\Log\LoggerInterface;

/**
 * Static logging to the `xenforo` channel.
 *
 * @deprecated 5.0.0 ask for a channel of your own instead, and type against the PSR-3 interface:
 *     \XF::app()->get('monolog')->channel('myaddon')->info('message', ['key' => 'value']);
 */
class Log
{
	public static function emergency($message, array $context = [])
	{
		self::getMonolog()->emergency($message, $context);
	}

	public static function alert($message, array $context = [])
	{
		self::getMonolog()->alert($message, $context);
	}

	public static function critical($message, array $context = [])
	{
		self::getMonolog()->critical($message, $context);
	}

	public static function error($message, array $context = [])
	{
		self::getMonolog()->error($message, $context);
	}

	public static function warning($message, array $context = [])
	{
		self::getMonolog()->warning($message, $context);
	}

	public static function notice($message, array $context = [])
	{
		self::getMonolog()->notice($message, $context);
	}

	public static function info($message, array $context = [])
	{
		self::getMonolog()->info($message, $context);
	}

	public static function debug($message, array $context = [])
	{
		self::getMonolog()->debug($message, $context);
	}

	public static function log($level, $message, array $context = [])
	{
		self::getMonolog()->log($level, $message, $context);
	}

	/**
	 * @param string $logger ignored; 4.x resolved it as a container key, and only 'default' existed
	 */
	public static function getMonolog($logger = 'default'): LoggerInterface
	{
		return \XF::app()->get('monolog')->channel('xenforo');
	}
}
