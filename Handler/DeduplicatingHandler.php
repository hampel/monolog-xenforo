<?php namespace Hampel\Monolog\Handler;

use Monolog\Handler\HandlerInterface;

/**
 * Buffers a request's records into one batch, sent when the request ends, and skips a batch
 * holding nothing that has not already been sent within the timeout.
 *
 * It replaces Monolog's DeduplicationHandler, which decides a repeat by level and message alone.
 * Messages here are meant to be fixed strings with whatever varies in the context, so by that test
 * two different errors a minute apart are one, and the second never reaches email or Slack; and
 * two add-ons logging the same sentence suppress each other. Monolog's test cannot be changed from
 * a subclass on both versions - its methods are private in 2 and differently shaped in 3.
 *
 * A record is a repeat when its channel, level, message and one more thing all match:
 *
 *  - `fingerprint` in the context, if the caller gives one - whatever tells this occurrence from
 *    the next, such as an error's type, file and line;
 *  - otherwise the class, file and line of an `exception` in the context;
 *  - otherwise nothing, so a bare message repeats as it always did.
 *
 * As Monolog's did: one new record sends the whole batch, repeats included, since they are its
 * context; and a record logged while the batch is being sent is dropped with the buffer, so a
 * transport that logs its own sends cannot feed back. Untyped records, for Monolog 2 and 3 alike -
 * see LazyHandler.
 */
class DeduplicatingHandler implements HandlerInterface
{
	private HandlerInterface $handler;

	private string $store;

	private int $level;

	private int $time;

	private array $buffer = [];

	private bool $initialized = false;

	/**
	 * @param string $store the file recording what has been sent
	 * @param int $level the lowest level buffered
	 * @param int $time seconds within which a record already sent is a repeat; 0 sends everything
	 */
	public function __construct(HandlerInterface $handler, string $store, int $level, int $time)
	{
		$this->handler = $handler;
		$this->store = $store;
		$this->level = $level;
		$this->time = $time;
	}

	public function isHandling($record): bool
	{
		return $record['level'] >= $this->level;
	}

	public function handle($record): bool
	{
		if (!$this->isHandling($record))
		{
			return false;
		}

		if (!$this->initialized)
		{
			// the end of the request, when nothing else has closed the logger
			register_shutdown_function([$this, 'close']);
			$this->initialized = true;
		}

		$this->buffer[] = $record;

		return false;
	}

	public function handleBatch(array $records): void
	{
		foreach ($records AS $record)
		{
			$this->handle($record);
		}
	}

	public function close(): void
	{
		$this->flush();
		$this->handler->close();
	}

	public function flush(): void
	{
		if (!$this->buffer)
		{
			return;
		}

		$sent = $this->sent();
		$new = [];
		foreach ($this->buffer AS $record)
		{
			$key = self::key($record);
			$since = $record['datetime']->getTimestamp() - $this->time;

			if (!isset($sent[$key]) || $sent[$key] <= $since)
			{
				$new[$key] = $record['datetime']->getTimestamp();
			}
		}

		if ($new)
		{
			$this->remember($new);
			$this->handler->handleBatch($this->buffer);
		}

		// after the send, so anything logged during it goes too
		$this->buffer = [];
	}

	/**
	 * What makes a record the same record as an earlier one.
	 */
	public static function key($record): string
	{
		$context = $record['context'];
		$exception = $context['exception'] ?? null;

		if (isset($context['fingerprint']) && is_scalar($context['fingerprint']))
		{
			$discriminator = (string) $context['fingerprint'];
		}
		else if ($exception instanceof \Throwable)
		{
			$discriminator = get_class($exception) . ':' . $exception->getFile() . ':' . $exception->getLine();
		}
		else
		{
			$discriminator = '';
		}

		return md5(implode("\n", [
			$record['channel'],
			$record['level_name'],
			preg_replace('{[\r\n].*}s', '', (string) $record['message']),
			$discriminator,
		]));
	}

	/**
	 * @return array<string, int> key => when it was last sent
	 */
	private function sent(): array
	{
		$lines = is_file($this->store) ? @file($this->store, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

		$sent = [];
		foreach ($lines ?: [] AS $line)
		{
			// "timestamp:key"; anything else is a partial write, or a line Monolog's handler left
			$parts = explode(':', $line);
			if (count($parts) === 2 && ctype_digit($parts[0]))
			{
				$sent[$parts[1]] = (int) $parts[0];
			}
		}

		return $sent;
	}

	/**
	 * Rewrites the store as what is still inside the timeout, plus what is being sent now - so it
	 * never grows past one timeout's worth, and lines in the old format drop out.
	 *
	 * @param array<string, int> $new
	 */
	private function remember(array $new): void
	{
		$cutoff = time() - $this->time;
		$keep = array_filter($this->sent(), function ($timestamp) use ($cutoff)
		{
			return $timestamp > $cutoff;
		});

		$lines = '';
		foreach (array_merge($keep, $new) AS $key => $timestamp)
		{
			$lines .= "{$timestamp}:{$key}\n";
		}

		@file_put_contents($this->store, $lines, LOCK_EX);
	}
}
