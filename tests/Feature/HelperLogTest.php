<?php namespace Tests\Feature;

use Hampel\Monolog\Helper\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The static facade, pinned before the 5.0 rewrite. No add-on of ours calls it, but the README
 * has documented it since 2.1.0, so 5.0 keeps it as a deprecated wrapper.
 */
class HelperLogTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'monologAddRequestId' => false,
			'monologFileMinimumLogLevel' => 100, // DEBUG
		]);
	}

	public static function levels(): array
	{
		return [
			'debug' => ['debug', 'DEBUG'],
			'info' => ['info', 'INFO'],
			'notice' => ['notice', 'NOTICE'],
			'warning' => ['warning', 'WARNING'],
			'error' => ['error', 'ERROR'],
			'critical' => ['critical', 'CRITICAL'],
			'alert' => ['alert', 'ALERT'],
			'emergency' => ['emergency', 'EMERGENCY'],
		];
	}

	#[DataProvider('levels')]
	public function test_each_level_method_writes_to_the_xenforo_channel_at_that_level($method, $levelName)
	{
		Log::$method('a message', ['a' => 'b']);

		$lines = $this->logLines();

		$this->assertCount(1, $lines);
		$this->assertStringContainsString("] xenforo.{$levelName}: a message {\"a\":\"b\"} []", $lines[0]);
	}

	public function test_log_takes_the_level_as_an_argument()
	{
		Log::log('error', 'by argument');

		$this->assertStringContainsString('] xenforo.ERROR: by argument', $this->logLines()[0]);
	}
}
