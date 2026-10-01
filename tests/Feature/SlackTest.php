<?php namespace Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hampel\Monolog\Handler\XenForoSlackHandler;
use Tests\TestCase;

/**
 * Slack: posted through XenForo's HTTP client, one message per request, and a failure never
 * reaches the code that was logging.
 */
class SlackTest extends TestCase
{
	private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/secret';

	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'monologSlack' => ['enabled' => true, 'webhook' => self::WEBHOOK],
			'monologSlackMinimumLogLevel' => 400, // ERROR
			'monologSite' => 'example.com',
		]);
	}

	public function test_slack_is_off_by_default()
	{
		$this->fakesHttp([]);
		$this->setOption('monologSlack', ['enabled' => false, 'webhook' => '']);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('not posted');
		$channel->close();

		$this->assertNoHttpRequestSent();
	}

	public function test_a_record_at_the_level_is_posted_when_the_channel_closes()
	{
		$this->fakesHttp([new Response(200, [], 'ok')]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('it broke', ['order_id' => 42]);

		$this->assertNoHttpRequestSent();

		$channel->close();

		$this->assertHttpRequestSentTimes(1);
		$payload = $this->payload(0);
		$this->assertSame(self::WEBHOOK, (string) $this->getHttpRequests()[0]->getUri());
		$this->assertSame('1 log record from example.com', $payload['text']);
		$this->assertCount(1, $payload['attachments']);
		$this->assertStringContainsString('it broke', json_encode($payload['attachments'][0]));
		$this->assertStringContainsString('order_id', json_encode($payload['attachments'][0]));
	}

	public function test_it_posts_with_a_short_timeout()
	{
		$this->fakesHttp([new Response(200, [], 'ok')]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('it broke');
		$channel->close();

		$options = $this->getHttpHistory()[0]['options'];
		$this->assertSame(5, $options['timeout']);
		$this->assertSame(3, $options['connect_timeout']);
	}

	public function test_a_record_below_the_level_is_not_posted()
	{
		$this->fakesHttp([]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->warning('only a warning');
		$channel->close();

		$this->assertNoHttpRequestSent();
	}

	public function test_records_in_one_request_arrive_in_one_message()
	{
		$this->fakesHttp([new Response(200, [], 'ok')]);

		$monolog = $this->app()['monolog'];
		$monolog->channel('first')->error('one');
		$monolog->channel('second')->critical('two');
		$monolog->channel('first')->close();

		$this->assertHttpRequestSentTimes(1);
		$this->assertSame('2 log records from example.com', $this->payload(0)['text']);
		$this->assertCount(2, $this->payload(0)['attachments']);
	}

	public function test_a_long_batch_is_cut_short_and_says_how_many_more()
	{
		$handler = new XenForoSlackHandler(self::WEBHOOK, 'example.com');
		$capture = new \Monolog\Handler\TestHandler();
		$logger = new \Monolog\Logger('myaddon', [$capture]);
		for ($i = 1; $i <= 13; $i++)
		{
			$logger->error("error {$i}");
		}

		$payload = $handler->payload($capture->getRecords());

		$this->assertSame('13 log records from example.com', $payload['text']);
		$this->assertCount(XenForoSlackHandler::MAX_ATTACHMENTS + 1, $payload['attachments']);
		$this->assertSame('and 3 more - see the log file', end($payload['attachments'])['text']);
	}

	/**
	 * Built in the test: Guzzle is XenForo's, and a data provider runs before XenForo has loaded.
	 */
	public static function failures(): array
	{
		return [
			'unreachable' => ['unreachable'],
			// what Slack answers for a webhook that has been deleted, and for one revoked
			'deleted' => [404],
			'revoked' => [403],
		];
	}

	/**
	 * Guzzle names the URL in its exception messages, and the URL is the credential.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('failures')]
	public function test_a_failed_post_goes_to_the_error_log_without_the_webhook($failure)
	{
		$this->fakesErrors();
		$this->fakesHttp([$failure === 'unreachable'
			? new ConnectException('cURL error 28: Connection timed out for ' . self::WEBHOOK, new Request('POST', self::WEBHOOK))
			: new Response($failure, [], 'no_service')]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('it broke');
		$channel->close();

		$errors = $this->getErrorFake()->getExceptions();
		$this->assertCount(1, $errors);
		$message = $errors[0]['message'];
		$this->assertStringContainsString('Monolog: posting to Slack failed', $message);
		$this->assertStringNotContainsString('secret', $message);
	}

	public function test_config_php_sets_the_webhook_and_turns_slack_on()
	{
		$this->fakesHttp([new Response(200, [], 'ok')]);
		$this->setOption('monologSlack', ['enabled' => false, 'webhook' => '']);
		$this->setConfig('monolog', ['slack' => ['webhook' => 'https://hooks.example.com/from-config']]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('it broke');
		$channel->close();

		$this->assertSame('https://hooks.example.com/from-config', (string) $this->getHttpRequests()[0]->getUri());
	}

	public function test_config_php_false_turns_slack_off()
	{
		$this->fakesHttp([]);
		$this->setConfig('monolog', ['slack' => false]);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('not posted');
		$channel->close();

		$this->assertNoHttpRequestSent();
	}

	public function test_a_webhook_that_is_not_https_leaves_slack_off()
	{
		$this->fakesHttp([]);
		$this->setOption('monologSlack', ['enabled' => true, 'webhook' => 'http://hooks.slack.com/services/x']);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('not posted');
		$channel->close();

		$this->assertNoHttpRequestSent();
	}

	public function test_slack_takes_its_own_channels()
	{
		$this->fakesHttp([new Response(200, [], 'ok')]);
		$this->setConfig('monolog', ['slack' => ['channels' => ['myaddon' => 'warning']]]);

		$monolog = $this->app()['monolog'];
		$monolog->channel('another')->warning('not posted');
		$monolog->channel('myaddon')->warning('posted');
		$monolog->channel('myaddon')->close();

		$this->assertHttpRequestSentTimes(1);
		$this->assertSame('1 log record from example.com', $this->payload(0)['text']);
	}

	private function payload(int $index): array
	{
		return json_decode((string) $this->getHttpRequests()[$index]->getBody(), true);
	}
}
