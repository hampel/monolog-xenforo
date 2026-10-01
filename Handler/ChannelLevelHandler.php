<?php namespace Hampel\Monolog\Handler;

use Monolog\Handler\HandlerInterface;

/**
 * A minimum level per channel, in front of one output's handler.
 *
 * The levels on the options page apply to every add-on at once, so turning one up to Debug turns
 * them all up. `'channels' => ['myaddon' => 'debug']` in a config.php section raises or lowers one
 * channel instead, and this decides each record against its own channel's level.
 *
 * The handler underneath is built at the lowest of those levels, so it lets through anything this
 * passes. Monolog 2 asks isHandling() with only a level and no channel, so without a channel this
 * answers for the lowest level, and handle() makes the exact decision. Untyped records, for
 * Monolog 2 and 3 alike - see LazyHandler.
 */
class ChannelLevelHandler implements HandlerInterface
{
	private HandlerInterface $handler;

	private int $level;

	/** @var array<string, int> */
	private array $channels;

	private int $lowest;

	/**
	 * @param int $level the level for any channel not listed
	 * @param array<string, int> $channels channel name => level
	 */
	public function __construct(HandlerInterface $handler, int $level, array $channels)
	{
		$this->handler = $handler;
		$this->level = $level;
		$this->channels = $channels;
		$this->lowest = min(array_merge([$level], array_values($channels)));
	}

	public function isHandling($record): bool
	{
		$channel = $record['channel'] ?? null;

		return $record['level'] >= ($channel === null ? $this->lowest : $this->levelFor($channel));
	}

	public function handle($record): bool
	{
		if (!$this->isHandling($record))
		{
			return false;
		}

		return $this->handler->handle($record);
	}

	public function handleBatch(array $records): void
	{
		$records = array_filter($records, [$this, 'isHandling']);
		if ($records)
		{
			$this->handler->handleBatch(array_values($records));
		}
	}

	public function close(): void
	{
		$this->handler->close();
	}

	public function levelFor(string $channel): int
	{
		return $this->channels[$channel] ?? $this->level;
	}

	public function getHandler(): HandlerInterface
	{
		return $this->handler;
	}
}
