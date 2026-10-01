<?php namespace Hampel\Monolog\Option;

use Monolog\Logger;

/**
 * The lowest level emailed - `email.level` in config.php can set it.
 */
class EmailMinimumLogLevel extends FileMinimumLogLevel
{
	protected const DEPENDS_ON = 'email';
	protected const SECTION = 'email';
	protected const OPTION = 'monologEmailMinimumLogLevel';
	protected const DEFAULT_LEVEL = Logger::ERROR;
}
