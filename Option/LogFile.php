<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;
use XF\Util\File;

/**
 * The log file, relative to internal_data - `file.path` in config.php can set it anywhere.
 *
 * Any admin with option permission can set this, and the file receives log lines that can carry
 * user-supplied text - so it is confined to internal_data. A path anywhere else, including a
 * stream wrapper, belongs in config.php, which only the server owner controls.
 */
class LogFile extends AbstractConfigurableOption
{
	public const DEFAULT_FILE = 'monolog.log';

	public static function isEnabled()
	{
		return Config::enabled('file') ?? !empty(\XF::options()->monologLogFile['enabled']);
	}

	/**
	 * The option's file, or the default if the stored value escapes internal_data - a value saved
	 * before 5.0 never went through verifyOption(). Empty when file logging is off.
	 */
	public static function getLogFile()
	{
		if (!self::isEnabled()) return '';

		$file = (string) (\XF::options()->monologLogFile['logfile'] ?? '');

		return $file !== '' && self::isInsideInternalData($file) ? $file : self::DEFAULT_FILE;
	}

	/**
	 * The full path the log file is written to, or null when file logging is off. `file.path` in
	 * config.php may be absolute or a stream such as php://stderr, and comes back unchanged; a
	 * relative path, and the option's path, are inside internal_data.
	 */
	public static function path(): ?string
	{
		if (!self::isEnabled())
		{
			return null;
		}

		$file = Config::string('file', 'path') ?? self::getLogFile();
		if ($file === '')
		{
			return null;
		}

		return File::canonicalizePath($file, File::canonicalizePath(\XF::app()->config('internalDataPath')));
	}

	public static function lockedValue(): ?string
	{
		$enabled = Config::enabled('file');
		if ($enabled === false)
		{
			return (string) \XF::phrase('disabled');
		}

		return Config::string('file', 'path') ?? ($enabled ? (string) \XF::phrase('enabled') : null);
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOffTextBox($option, $htmlParams, 'enabled', 'logfile', self::DEFAULT_FILE);
	}

	protected static function verifyValue(&$value, Option $option): bool
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
