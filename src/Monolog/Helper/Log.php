<?php

use Monolog\Logger;
use Monolog\Handler\HandlerInterface;

class Monolog_Helper_Log
{
	/**
	 * Create a new monolog instance
	 *
	 * @param $name channel name
	 *
	 * @return Logger
	 * @throws XenForo_Exception
	 */
	public static function create($name)
	{
		if (empty($name)) throw new XenForo_Exception(new XenForo_Phrase('monolog_name_parameter_required'));

		$logger = new Logger($name);

		XenForo_Application::set("monolog-channel-{$name}", $logger);

		$handler = Monolog_Helper_Handler::getDefault();
		$logger->pushHandler($handler);

		return $logger;
	}

	/**
	 * Return our instance of the Monolog logger
	 *
	 * @param string $name
	 *
	 * @return Logger
	 * @throws Zend_Exception
	 */
	public static function get($name = "")
	{
		// if no name specified, get the name of the default channel instead
		if (empty($name)) $name = Monolog_Option_Channel::getDefault();

		return XenForo_Application::get("monolog-channel-{$name}");
	}

	/**
	 * Return the default instance of the Monolog logger
	 *
	 * @return Logger
	 */
	public static function getDefault()
	{
		return self::get(Monolog_Option_Channel::getDefault());
	}

	/**
	 * Pushes a handler onto the logger for a single channel
	 *
	 * @param $name
	 * @param HandlerInterface $handler
	 *
	 * @return Logger
	 */
	public static function setHandler($name, HandlerInterface $handler)
	{
		$log = self::get($name);
		$log->pushHandler($handler);

		return $log;
	}

	/**
	 * Adds a log record at an arbitrary level on the default channel
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param  mixed   $level   The log level
	 * @param  string  $message The log message
	 * @param  array   $context The log context
	 * @return Boolean Whether the record has been processed
	 */
	public static function log($level, $message, array $context = array())
	{
		return self::getDefault()->log($level, $message, $context);
	}

    /**
     * Adds a log record at the DEBUG level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function debug($message, array $context = array())
	{
		return self::getDefault()->debug($message, $context);
	}

    /**
     * Adds a log record at the INFO level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function info($message, array $context = array())
	{
		return self::getDefault()->info($message, $context);
	}

    /**
     * Adds a log record at the NOTICE level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function notice($message, array $context = array())
	{
		return self::getDefault()->notice($message, $context);
	}

    /**
     * Adds a log record at the WARNING level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function warning($message, array $context = array())
	{
		return self::getDefault()->warning($message, $context);
	}

   /**
     * Adds a log record at the ERROR level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function error($message, array $context = array())
	{
		return self::getDefault()->error($message, $context);
	}

   /**
     * Adds a log record at the CRITICAL level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function critical($message, array $context = array())
	{
		return self::getDefault()->critical($message, $context);
	}

   /**
     * Adds a log record at the ALERT level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function alert($message, array $context = array())
	{
		return self::getDefault()->alert($message, $context);
	}

    /**
     * Adds a log record at the EMERGENCY level on the default channel
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function emergency($message, array $context = array())
	{
		return self::getDefault()->emergency($message, $context);
	}
}