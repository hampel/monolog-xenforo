<?php namespace Tests\Feature;

use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * channel(), the 5.0 consumer API.
 */
class ChannelTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
	}

	public function test_a_channel_is_a_psr3_logger()
	{
		$this->assertInstanceOf(LoggerInterface::class, $this->app()['monolog']->channel('myaddon'));
	}

	public function test_asking_for_a_channel_twice_returns_the_same_logger()
	{
		$monolog = $this->app()['monolog'];

		$this->assertSame($monolog->channel('myaddon'), $monolog->channel('myaddon'));
	}

	public function test_a_channel_writes_under_its_own_name()
	{
		$this->app()['monolog']->channel('myaddon')->warning('something happened');

		$this->assertStringContainsString('] myaddon.WARNING: something happened', $this->logLines()[0]);
	}

	/**
	 * 4.x pushed the default handlers onto a shared logger, so whether a logger carried them
	 * depended on what had been resolved earlier in the request.
	 */
	public function test_a_record_is_written_once_whatever_was_resolved_before_it()
	{
		$monolog = $this->app()['monolog'];

		$monolog->channel('first')->warning('one');
		$monolog->channel('second')->warning('two');
		$monolog->newChannel('third')->warning('three');

		$this->assertCount(3, $this->logLines());
	}

	public function test_new_channel_is_an_alias_for_channel()
	{
		$monolog = $this->app()['monolog'];

		$this->assertSame($monolog->channel('myaddon'), $monolog->newChannel('myaddon'));
	}
}
