<?php namespace Hampel\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;

/**
 * Adds `extra.request_id`: one id for every record a request writes, so all of them can be found
 * from any one - and, in an email, the alert tied to its log lines.
 *
 * The web server's own id is used where it provides one - Apache's mod_unique_id sets UNIQUE_ID,
 * and a proxy can send X-Request-ID - so a log line can also be matched to the access log.
 * Otherwise it is eight random hex characters. A CLI run or a job batch is one request.
 *
 * One instance per logger, which XenForo builds once per request, so one id per request.
 * Written for Monolog 2 and 3 alike - see VisitorProcessor.
 */
class RequestIdProcessor implements ProcessorInterface
{
	/** @var string|null */
	private $id;

	/**
	 * @param array|\Monolog\LogRecord $record
	 *
	 * @return array|\Monolog\LogRecord
	 */
	public function __invoke($record)
	{
		$extra = $record['extra'];
		$extra['request_id'] = $this->id();
		$record['extra'] = $extra;

		return $record;
	}

	public function id(): string
	{
		if ($this->id === null)
		{
			$this->id = self::fromServer() ?? bin2hex(random_bytes(4));
		}

		return $this->id;
	}

	/**
	 * The web server's request id, if it supplied one that looks like an id. A header value lands
	 * in the log, so anything longer, or with spaces, newlines or other characters, is ignored
	 * rather than written.
	 */
	public static function fromServer(): ?string
	{
		try
		{
			$request = \XF::app()->request();
		}
		catch (\Throwable $e)
		{
			return null;
		}

		foreach (['UNIQUE_ID', 'HTTP_X_REQUEST_ID'] AS $key)
		{
			$value = $request->getServer($key);
			if (is_string($value) && preg_match('/^[A-Za-z0-9@._:-]{1,64}$/', $value))
			{
				return $value;
			}
		}

		return null;
	}
}
