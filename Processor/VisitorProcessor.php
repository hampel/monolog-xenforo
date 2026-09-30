<?php namespace Hampel\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;

/**
 * Adds the user the request is running as to extra.visitor - user id 0 for a guest.
 */
class VisitorProcessor implements ProcessorInterface
{
	public function __invoke(array $record): array
	{
		$visitor = \XF::visitor();

		$record['extra']['visitor'] = [
			'userid' => $visitor->user_id,
			'username' => $visitor->username,
		];

		return $record;
	}
}
