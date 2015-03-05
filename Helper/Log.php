<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Monolog_Helper_Log
{
	protected static $_instance = null;

	protected $_monolog = null;

	private function __construct()
	{
		$this->_monolog = self::createMonolog();
	}

	/**
	 * Return our instance of the Monolog logger
	 *
	 * @return Logger
	 */
	public static function getMonolog()
	{
		$object = self::_getInstance();
		return $object->_monolog;
	}

	/**
	 * Adds a log record at an arbitrary level.
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
		return self::getMonolog()->log($level, $message, $context);
	}

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function debug($message, array $context = array())
	{
		return self::getMonolog()->debug($message, $context);
	}

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function info($message, array $context = array())
	{
		return self::getMonolog()->info($message, $context);
	}

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function notice($message, array $context = array())
	{
		return self::getMonolog()->notice($message, $context);
	}

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function warning($message, array $context = array())
	{
		return self::getMonolog()->warning($message, $context);
	}

   /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function error($message, array $context = array())
	{
		return self::getMonolog()->error($message, $context);
	}

   /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function critical($message, array $context = array())
	{
		return self::getMonolog()->critical($message, $context);
	}

   /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function alert($message, array $context = array())
	{
		return self::getMonolog()->alert($message, $context);
	}

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
	public static function emergency($message, array $context = array())
	{
		return self::getMonolog()->emergency($message, $context);
	}

	protected static final function _getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	protected static function createMonolog()
	{
		$log = new Logger('monolog');
		$log->pushHandler(
			new StreamHandler(XenForo_Helper_File::getInternalDataPath() . '/monolog.log', Logger::WARNING)
		);

		return $log;
	}
}