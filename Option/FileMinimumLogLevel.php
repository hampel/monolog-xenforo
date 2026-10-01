<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use Monolog\Logger;
use XF\Entity\Option;

/**
 * The lowest level written to the log file - `file.level` in config.php can set it.
 */
class FileMinimumLogLevel extends AbstractConfigurableOption
{
	/**
	 * The PSR-3 levels by Monolog's numeric value, which is what the level options store.
	 *
	 * Spelled out rather than read from Logger::getLevels(), which Monolog 3 removed - and
	 * XenForo 2.4 bundles Monolog 3, so on 2.4 that call is a fatal error on the options page.
	 */
	public const LEVELS = [
		100 => 'Debug',
		200 => 'Info',
		250 => 'Notice',
		300 => 'Warning',
		400 => 'Error',
		500 => 'Critical',
		550 => 'Alert',
		600 => 'Emergency',
	];

	protected const DEPENDS_ON = 'file';
	protected const SECTION = 'file';
	protected const OPTION = 'monologFileMinimumLogLevel';
	protected const DEFAULT_LEVEL = Logger::WARNING;

	public static function get()
	{
		$level = Config::level(static::SECTION);
		if ($level !== null)
		{
			return $level;
		}

		$level = \XF::options()->{static::OPTION};

		return empty($level) ? static::DEFAULT_LEVEL : $level;
	}

	public static function lockedValue(): ?string
	{
		$level = Config::level(static::SECTION);

		return $level === null ? null : self::LEVELS[$level];
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		$value = $option->option_value ?: $option->default_value;

		return static::renderSelect($option, $htmlParams, self::LEVELS, $value);
	}
}
