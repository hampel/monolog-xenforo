<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Seconds before an identical record is emailed again - `email.dedup` in config.php can set it.
 */
class EmailDeduplicationTimeout extends AbstractConfigurableOption
{
	public static function get()
	{
		return self::configured() ?? \XF::options()->monologEmailDeduplicationTimeout;
	}

	public static function lockedValue(): ?string
	{
		$seconds = self::configured();

		return $seconds === null ? null : (string) $seconds;
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderNumberBox($option, $htmlParams);
	}

	private static function configured(): ?int
	{
		$value = Config::get('email', 'dedup');

		return is_int($value) && $value >= 0 ? $value : null;
	}
}
