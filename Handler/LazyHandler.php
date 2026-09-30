<?php namespace Hampel\Monolog\Handler;

use Monolog\Handler\HandlerInterface;

/**
 * A handler that is built on the first record it has to handle.
 *
 * Some handlers cannot be constructed without side effects. The mail handler needs XenForo's
 * mailer, and building the mailer fires mailer_transport_setup - which another add-on may answer
 * by asking for a log channel, re-entering the construction of this one. Wrapping the handler
 * defers all of that until a record actually reaches its level, so creating a channel, or writing
 * below that level, costs nothing.
 */
class LazyHandler implements HandlerInterface
{
	/** @var callable */
	private $factory;

	private int $level;

	private ?HandlerInterface $handler = null;

	/**
	 * @param callable $factory returns the HandlerInterface to delegate to
	 * @param int $level the lowest level the built handler handles; checked without building it
	 */
	public function __construct(callable $factory, int $level)
	{
		$this->factory = $factory;
		$this->level = $level;
	}

	public function isHandling(array $record): bool
	{
		return $record['level'] >= $this->level;
	}

	public function handle(array $record): bool
	{
		if (!$this->isHandling($record))
		{
			return false;
		}

		return $this->getHandler()->handle($record);
	}

	public function handleBatch(array $records): void
	{
		$records = array_filter($records, [$this, 'isHandling']);
		if ($records)
		{
			$this->getHandler()->handleBatch(array_values($records));
		}
	}

	public function close(): void
	{
		if ($this->handler)
		{
			$this->handler->close();
		}
	}

	public function isBuilt(): bool
	{
		return $this->handler !== null;
	}

	public function getHandler(): HandlerInterface
	{
		if ($this->handler === null)
		{
			$this->handler = call_user_func($this->factory);
		}

		return $this->handler;
	}
}
