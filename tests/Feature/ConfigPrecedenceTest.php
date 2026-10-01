<?php namespace Tests\Feature;

use Hampel\Monolog\Option\AddVisitorExtra;
use Hampel\Monolog\Option\AddWebExtra;
use Hampel\Monolog\Option\EmailDeduplicationTimeout;
use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailSubject;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\LogFormat;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Monolog\Option\Site;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every option's getter: config.php wins over an option set to something else, and with no
 * config.php value the option decides.
 */
class ConfigPrecedenceTest extends TestCase
{
	/**
	 * [config.php block, the option and the value it is set to, the getter, expected with config]
	 */
	public static function settings(): array
	{
		return [
			'file off' => [['file' => false], ['monologLogFile', ['enabled' => true, 'logfile' => 'a.log']],
				[LogFile::class, 'isEnabled'], false],
			'file on by path' => [['file' => ['path' => 'b.log']], ['monologLogFile', ['enabled' => false, 'logfile' => 'a.log']],
				[LogFile::class, 'isEnabled'], true],
			'file format' => [['file' => ['format' => 'json']], ['monologLogFormat', 'line'],
				[LogFormat::class, 'get'], 'json'],
			'file level' => [['file' => ['level' => 'error']], ['monologFileMinimumLogLevel', 100],
				[FileMinimumLogLevel::class, 'get'], 400],
			'email off' => [['email' => false], ['monologSendEmail', ['enabled' => true, 'email' => 'x@example.com']],
				[SendEmail::class, 'isEnabled'], false],
			'email recipient' => [['email' => ['to' => 'c@example.com']], ['monologSendEmail', ['enabled' => false, 'email' => 'x@example.com']],
				[SendEmail::class, 'getAddress'], 'c@example.com'],
			'email level' => [['email' => ['level' => 'critical']], ['monologEmailMinimumLogLevel', 400],
				[EmailMinimumLogLevel::class, 'get'], 500],
			'email subject' => [['email' => ['subject' => 'From config']], ['monologEmailSubject', 'From option'],
				[EmailSubject::class, 'get'], 'From config'],
			'email dedup' => [['email' => ['dedup' => 60]], ['monologEmailDeduplicationTimeout', 300],
				[EmailDeduplicationTimeout::class, 'get'], 60],
			'visitor' => [['visitor' => false], ['monologAddVisitorExtra', true],
				[AddVisitorExtra::class, 'get'], false],
			'web' => [['web' => true], ['monologAddWebExtra', false],
				[AddWebExtra::class, 'get'], true],
			'site' => [['site' => 'from-config'], ['monologSite', 'from-option'],
				[Site::class, 'get'], 'from-config'],
		];
	}

	#[DataProvider('settings')]
	public function test_config_php_wins_over_the_option(array $config, array $option, callable $getter, $expected)
	{
		$this->setOption($option[0], $option[1]);
		$this->setConfig('monolog', $config);

		$this->assertSame($expected, call_user_func($getter));
	}

	#[DataProvider('settings')]
	public function test_without_config_php_the_option_decides(array $config, array $option, callable $getter, $expected)
	{
		$this->setOption($option[0], $option[1]);

		$this->assertNotSame($expected, call_user_func($getter));
	}

	public function test_an_unusable_config_value_leaves_it_to_the_option()
	{
		$this->setOptions(['monologFileMinimumLogLevel' => 400, 'monologLogFormat' => 'json']);
		$this->setConfig('monolog', ['file' => ['level' => 'loud', 'format' => 'yaml']]);

		$this->assertSame(400, FileMinimumLogLevel::get());
		$this->assertSame('json', LogFormat::get());
	}

	public function test_an_empty_site_falls_back_to_the_board_url_host()
	{
		$this->setOptions(['monologSite' => '', 'boardUrl' => 'https://forum.example.com/x']);

		$this->assertSame('forum.example.com', Site::get());
	}
}
