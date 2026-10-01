<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * The log file's format - `file.format` in config.php can set it.
 */
class LogFormat extends AbstractConfigurableOption
{
	public const FORMATS = ['line', 'json'];

	public static function get(): string
	{
		$configured = Config::get('file', 'format');
		if (in_array($configured, self::FORMATS, true))
		{
			return $configured;
		}

		$option = \XF::options()->monologLogFormat ?? 'line';

		return in_array($option, self::FORMATS, true) ? $option : 'line';
	}

	public static function lockedValue(): ?string
	{
		$configured = Config::get('file', 'format');

		return in_array($configured, self::FORMATS, true) ? self::label($configured) : null;
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		$choices = [];
		foreach (self::FORMATS AS $format)
		{
			$choices[$format] = self::label($format);
		}

		return static::renderSelect($option, $htmlParams, $choices, $option->option_value ?: 'line');
	}

	protected static function verifyValue(&$value, Option $option): bool
	{
		if (!in_array($value, self::FORMATS, true))
		{
			$value = 'line';
		}

		return true;
	}

	private static function label(string $format): string
	{
		return (string) \XF::phrase('monolog_format_' . $format);
	}
}
