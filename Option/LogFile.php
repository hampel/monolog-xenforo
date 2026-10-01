<?php namespace Hampel\Monolog\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

/**
 * The log file, relative to internal_data.
 *
 * Any admin with option permission can set this, and the file receives log lines that can carry
 * user-supplied text - so it is confined to internal_data. A path anywhere else, including a
 * stream wrapper, belongs in config.php (`$config['monolog']['file']`), which only the server
 * owner controls.
 */
class LogFile extends AbstractOption
{
	public const DEFAULT_FILE = 'monolog.log';

	public static function isEnabled()
	{
		return !empty(\XF::options()->monologLogFile['enabled']);
	}

	/**
	 * The configured file, or the default if the stored value escapes internal_data - a value
	 * saved before 5.0 never went through verifyOption().
	 */
	public static function getLogFile()
	{
		if (!self::isEnabled()) return '';

		$file = (string) (\XF::options()->monologLogFile['logfile'] ?? '');

		return self::isInsideInternalData($file) ? $file : self::DEFAULT_FILE;
	}

	public static function verifyOption(array &$value, Option $option)
	{
		$file = (string) ($value['logfile'] ?? '');

		if ($file !== '' && !self::isInsideInternalData($file))
		{
			$option->error(\XF::phrase('monolog_log_file_must_be_inside_internal_data'), $option->option_id);
			return false;
		}

		return true;
	}

	/**
	 * Refuses anything that could resolve outside internal_data: a leading slash or backslash, a
	 * drive letter or stream wrapper (`C:`, `php:`, `phar:`), and any `..` segment.
	 */
	public static function isInsideInternalData(string $path): bool
	{
		if (preg_match('#^([/\\\\]|[a-z][a-z0-9+.-]*:)#i', $path))
		{
			return false;
		}

		foreach (preg_split('#[/\\\\]+#', $path) AS $segment)
		{
			if ($segment === '..')
			{
				return false;
			}
		}

		return true;
	}
}
