<?php namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * extra.request_id: one id for every record a request writes.
 */
class RequestIdTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
	}

	public function test_every_record_in_a_request_carries_the_same_id()
	{
		$monolog = $this->app()['monolog'];
		$monolog->channel('first')->warning('one');
		$monolog->channel('second')->error('two');

		$ids = array_map([$this, 'requestId'], $this->logLines());

		$this->assertCount(2, $ids);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $ids[0]);
		$this->assertSame($ids[0], $ids[1]);
	}

	/**
	 * XenForo builds the logger, and with it this processor, once per request - so another request
	 * is another instance, with an id of its own.
	 */
	public function test_another_request_gets_another_id()
	{
		$first = new \Hampel\Monolog\Processor\RequestIdProcessor();
		$second = new \Hampel\Monolog\Processor\RequestIdProcessor();

		$this->assertSame($first->id(), $first->id());
		$this->assertNotSame($first->id(), $second->id());
	}

	public static function serverIds(): array
	{
		return [
			"Apache's UNIQUE_ID" => [['UNIQUE_ID' => 'YzAbC@xyz-12_3'], 'YzAbC@xyz-12_3'],
			"a proxy's X-Request-ID" => [['HTTP_X_REQUEST_ID' => 'f3a1c2d4-5b6e'], 'f3a1c2d4-5b6e'],
			'UNIQUE_ID first' => [['UNIQUE_ID' => 'apache', 'HTTP_X_REQUEST_ID' => 'proxy'], 'apache'],
		];
	}

	#[DataProvider('serverIds')]
	public function test_the_web_servers_id_is_used_where_there_is_one(array $server, $expected)
	{
		$this->withServer($server);

		$this->app()['monolog']->channel('myaddon')->warning('matched to the access log');

		$this->assertSame($expected, $this->requestId($this->logLines()[0]));
	}

	public static function unsafeIds(): array
	{
		return [
			'a newline, which would start a forged line' => ["abc\n[2026-01-01] forged.ERROR: x"],
			'spaces' => ['not an id'],
			'too long' => [str_repeat('a', 65)],
		];
	}

	/**
	 * A header lands in the log, so only a value shaped like an id is trusted.
	 */
	#[DataProvider('unsafeIds')]
	public function test_a_server_id_that_is_not_shaped_like_one_is_ignored($value)
	{
		$this->withServer(['HTTP_X_REQUEST_ID' => $value]);

		$this->app()['monolog']->channel('myaddon')->warning('generated instead');

		$this->assertCount(1, $this->logLines());
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $this->requestId($this->logLines()[0]));
	}

	public function test_line_records_leave_it_out_when_the_option_is_off()
	{
		$this->setOption('monologAddRequestId', false);

		$this->app()['monolog']->channel('myaddon')->warning('no id');

		$this->assertStringNotContainsString('request_id', $this->logLines()[0]);
	}

	public function test_json_records_carry_it_whatever_the_option()
	{
		$this->setOption('monologAddRequestId', false);
		$this->setConfig('monolog', ['file' => ['format' => 'json']]);

		$this->app()['monolog']->channel('myaddon')->warning('always');

		$this->assertArrayHasKey('request_id', json_decode($this->logLines()[0], true)['extra']);
	}

	public function test_config_php_can_switch_it_off()
	{
		$this->setConfig('monolog', ['request_id' => false]);

		$this->app()['monolog']->channel('myaddon')->warning('off in config');

		$this->assertStringNotContainsString('request_id', $this->logLines()[0]);
	}

	private function withServer(array $server): void
	{
		$request = new \XF\Http\Request($this->app()->inputFilterer(), [], [], [], $server);
		$this->swap('request', $request);
	}

	private function requestId(string $line): string
	{
		$this->assertSame(1, preg_match('/"request_id":"([^"]+)"/', $line, $matches), $line);

		return $matches[1];
	}
}
