<?php namespace Hampel\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;

/**
 * Adds the user the request is running as to extra.visitor - user id 0 for a guest.
 *
 * Written to run on Monolog 2, which passes an array, and Monolog 3, which passes a LogRecord -
 * if XenForo provides Monolog, its copy wins. So the parameter is untyped, and `extra` is read and
 * assigned whole: LogRecord's array access allows setting `extra`, but most of its fields are
 * read-only.
 *
 * @param array|\Monolog\LogRecord $record
 *
 * @return array|\Monolog\LogRecord
 */
class VisitorProcessor implements ProcessorInterface
{
	public function __invoke($record)
	{
		$visitor = \XF::visitor();

		$extra = $record['extra'];
		$extra['visitor'] = [
			'userid' => $visitor->user_id,
			'username' => $visitor->username,
		];
		$record['extra'] = $extra;

		return $record;
	}
}
