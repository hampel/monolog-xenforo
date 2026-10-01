<?php namespace Hampel\Monolog;

use Hampel\Monolog\Option\FileMinimumLogLevel;

/**
 * `$config['monolog']` from config.php - the server owner's settings, which win over the options.
 *
 *     $config['monolog'] = [
 *         'file' => [                 // or false to turn file logging off
 *             'path' => 'monolog.log',    // absolute, or relative to internal_data
 *             'format' => 'json',         // 'line' or 'json'
 *             'level' => 'warning',       // a level name or number
 *             'formatter' => callable,    // returns the file's formatter, in place of format
 *             'channels' => ['myaddon' => 'debug'],   // a level for one channel, not all of them
 *         ],
 *         'email' => [                // or false to turn email off
 *             'to' => 'admin@example.com',
 *             'level' => 'error',
 *             'subject' => 'Errors on {board}',
 *             'dedup' => 300,
 *             'channels' => ['myaddon' => 'warning'],
 *         ],
 *         'slack' => [                // or false to turn Slack off
 *             'webhook' => 'https://hooks.slack.com/services/...',
 *             'level' => 'error',
 *             'dedup' => 300,
 *             'channels' => ['myaddon' => 'warning'],
 *         ],
 *         'request_id' => true,
 *         'visitor' => true,
 *         'web' => false,
 *         'site' => 'myforum',
 *         'handlers' => [callable, ...],
 *         'processors' => [callable, ...],
 *     ];
 *
 * Every key is optional, and only a key that is present locks its option: `'file' => ['level' =>
 * 'error']` pins the level and leaves the path to the options page. A value that cannot be used is
 * ignored, so its option decides.
 */
class Config
{
	public static function all(): array
	{
		$config = \XF::app()->config('monolog');

		return is_array($config) ? $config : [];
	}

	/**
	 * A top-level value, or one key within a section; null if absent.
	 */
	public static function get(string $section, ?string $key = null)
	{
		$all = self::all();
		if (!array_key_exists($section, $all))
		{
			return null;
		}

		if ($key === null)
		{
			return $all[$section];
		}

		return is_array($all[$section]) ? ($all[$section][$key] ?? null) : null;
	}

	/**
	 * Whether config.php turns the `file`, `email` or `slack` section on or off, or null to leave it
	 * to the option. `false` turns it off; `true`, or a `path` / `to` / `webhook`, turns it on.
	 */
	public static function enabled(string $section): ?bool
	{
		$value = self::get($section);
		if (is_bool($value))
		{
			return $value;
		}

		$target = ['email' => 'to', 'slack' => 'webhook'][$section] ?? 'path';
		if (is_array($value) && is_string($value[$target] ?? null) && $value[$target] !== '')
		{
			return true;
		}

		return null;
	}

	/**
	 * The `level` within the `file` or `email` section, as Monolog's number; null if absent or not a
	 * level.
	 */
	public static function level(string $section): ?int
	{
		$value = self::get($section, 'level');

		return $value === null ? null : self::toLevel($value);
	}

	/**
	 * A level by name, any case, or by number: 'warning', 'WARNING' and 300 are all Warning.
	 *
	 * @param int|string $value
	 */
	public static function toLevel($value): ?int
	{
		if (is_int($value) || (is_string($value) && ctype_digit($value)))
		{
			$level = (int) $value;

			return isset(FileMinimumLogLevel::LEVELS[$level]) ? $level : null;
		}

		if (!is_string($value))
		{
			return null;
		}

		$level = array_search(ucfirst(strtolower(trim($value))), FileMinimumLogLevel::LEVELS, true);

		return $level === false ? null : $level;
	}

	/**
	 * The `channels` within a section: channel name => Monolog's level number. An entry whose level
	 * is not a level is left out, so that channel takes the section's level.
	 *
	 * @return array<string, int>
	 */
	public static function channelLevels(string $section): array
	{
		$levels = [];
		foreach (self::channels($section) AS $channel => $value)
		{
			$level = is_string($channel) && $channel !== '' ? self::toLevel($value) : null;
			if ($level !== null)
			{
				$levels[$channel] = $level;
			}
		}

		return $levels;
	}

	/**
	 * The `channels` entries channelLevels() leaves out - for monolog:validate to report.
	 */
	public static function invalidChannelLevels(string $section): array
	{
		return array_diff_key(self::channels($section), self::channelLevels($section));
	}

	/**
	 * The level for one channel in a section: its own if config.php sets one, otherwise $default.
	 */
	public static function levelFor(string $section, string $channel, int $default): int
	{
		return self::channelLevels($section)[$channel] ?? $default;
	}

	private static function channels(string $section): array
	{
		$channels = self::get($section, 'channels');

		return is_array($channels) ? $channels : [];
	}

	public static function string(string $section, ?string $key = null): ?string
	{
		$value = self::get($section, $key);

		return is_string($value) && $value !== '' ? $value : null;
	}

	public static function bool(string $section): ?bool
	{
		$value = self::get($section);

		return is_bool($value) ? $value : null;
	}
}
