<?php namespace Tests\Feature;

use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Monolog options page, rendered and saved through XenForo's own controller.
 *
 * An option config.php sets is shown with its value and no input, and is left off the page's list
 * of options to save: XenForo saves false for a listed option that sent nothing, so a listed but
 * disabled input would wipe the stored value on every save.
 */
class OptionsPageTest extends TestCase
{
	use UsesDatabaseTransactions;

	private const OPTIONS = [
		'monologLogFile', 'monologLogFormat', 'monologFileMinimumLogLevel',
		'monologSendEmail', 'monologEmailSubject', 'monologEmailMinimumLogLevel',
		'monologEmailDeduplicationTimeout', 'monologSlack', 'monologSlackMinimumLogLevel',
		'monologAddRequestId', 'monologAddVisitorExtra', 'monologAddWebExtra', 'monologSite',
	];

	protected function setUp(): void
	{
		parent::setUp();

		$this->fakesErrors();
		$admin = $this->actingAsMember(['is_admin' => true]);
		$this->setVisitorAdminPermissions($admin, ['option' => true]);
	}

	public function test_every_option_renders_its_control_when_config_php_sets_nothing()
	{
		$html = $this->page();

		$this->assertSame(self::OPTIONS, array_values(array_intersect(self::OPTIONS, $this->listed($html))));
		foreach (self::OPTIONS AS $optionId)
		{
			$this->assertStringContainsString("name=\"options[{$optionId}]", $html, $optionId);
		}
		foreach (array_keys(FileMinimumLogLevel::LEVELS) AS $level)
		{
			$this->assertStringContainsString("value=\"{$level}\"", $html);
		}
		$this->assertStringNotContainsString('Set in config.php', $html);
		$this->assertNoTemplateErrors();
		$this->assertNoErrorsLogged();
	}

	public static function locks(): array
	{
		return [
			'file path' => [['file' => ['path' => '/var/log/forum.log']], 'monologLogFile', '/var/log/forum.log'],
			'file format' => [['file' => ['format' => 'json']], 'monologLogFormat', 'JSON'],
			'file level' => [['file' => ['level' => 'error']], 'monologFileMinimumLogLevel', 'Error'],
			'email recipient' => [['email' => ['to' => 'a@example.com']], 'monologSendEmail', 'a@example.com'],
			'email level' => [['email' => ['level' => 'alert']], 'monologEmailMinimumLogLevel', 'Alert'],
			'email subject' => [['email' => ['subject' => 'Subject']], 'monologEmailSubject', 'Subject'],
			'email dedup' => [['email' => ['dedup' => 60]], 'monologEmailDeduplicationTimeout', '60'],
			'slack webhook' => [['slack' => ['webhook' => 'https://hooks.example.com/x']], 'monologSlack', 'Enabled'],
			'slack level' => [['slack' => ['level' => 'critical']], 'monologSlackMinimumLogLevel', 'Critical'],
			'request id' => [['request_id' => false], 'monologAddRequestId', 'Disabled'],
			'visitor' => [['visitor' => false], 'monologAddVisitorExtra', 'Disabled'],
			'web' => [['web' => true], 'monologAddWebExtra', 'Enabled'],
			'site' => [['site' => 'mysite'], 'monologSite', 'mysite'],
		];
	}

	#[DataProvider('locks')]
	public function test_an_option_config_php_sets_shows_its_value_and_no_input(array $config, $optionId, $shown)
	{
		$this->setConfig('monolog', $config);

		$html = $this->page();

		$this->assertStringContainsString('<div class="formRow-value">' . $shown . '</div>', $html);
		$this->assertStringNotContainsString("name=\"options[{$optionId}]", $html);
		$this->assertNotContains($optionId, $this->listed($html));
		$this->assertCount(count(self::OPTIONS) - 1, array_intersect(self::OPTIONS, $this->listed($html)),
			'every other option is still listed');
		$this->assertNoTemplateErrors();
	}

	public static function disabledSections(): array
	{
		return [
			'file off' => [
				['file' => false],
				['monologLogFile' => 'Disabled', 'monologLogFormat' => 'Not used', 'monologFileMinimumLogLevel' => 'Not used'],
			],
			'email off' => [
				['email' => false],
				['monologSendEmail' => 'Disabled', 'monologEmailSubject' => 'Not used',
					'monologEmailMinimumLogLevel' => 'Not used', 'monologEmailDeduplicationTimeout' => 'Not used'],
			],
			'slack off' => [
				['slack' => false],
				['monologSlack' => 'Disabled', 'monologSlackMinimumLogLevel' => 'Not used'],
			],
		];
	}

	/**
	 * A section config.php switches off takes every option in it along: they mean nothing while it
	 * is off, so none is offered for editing, and the rest of the page is untouched.
	 */
	#[DataProvider('disabledSections')]
	public function test_a_section_config_php_switches_off_locks_every_option_in_it(array $config, array $locked)
	{
		$this->setConfig('monolog', $config);

		$html = $this->page();
		$listed = $this->listed($html);

		foreach ($locked AS $optionId => $shown)
		{
			$this->assertStringNotContainsString("name=\"options[{$optionId}]", $html, $optionId);
			$this->assertNotContains($optionId, $listed, $optionId);
		}
		$this->assertSame(1, substr_count($html, '<div class="formRow-value">Disabled</div>'));
		$this->assertSame(count($locked) - 1, substr_count($html, '<div class="formRow-value">Not used</div>'));
		$this->assertSame(
			array_values(array_diff(self::OPTIONS, array_keys($locked))),
			array_values(array_intersect(self::OPTIONS, $listed)),
			'every option outside the section is still listed'
		);
		$this->assertNoTemplateErrors();
	}

	public function test_switching_a_section_off_on_the_options_page_locks_nothing()
	{
		$this->setOptions([
			'monologLogFile' => ['enabled' => false, 'logfile' => 'monolog.log'],
			'monologSendEmail' => ['enabled' => false, 'email' => ''],
			'monologSlack' => ['enabled' => false, 'webhook' => ''],
		]);

		$this->assertSame(self::OPTIONS, array_values(array_intersect(self::OPTIONS, $this->listed($this->page()))));
	}

	/**
	 * The webhook is a credential, so a locked Slack row says it is on and never what it is.
	 */
	public function test_a_webhook_config_php_sets_is_never_shown()
	{
		$this->setConfig('monolog', ['slack' => ['webhook' => 'https://hooks.slack.com/services/T/B/secret']]);

		$html = $this->page();

		$this->assertStringNotContainsString('secret', $html);
		$this->assertStringNotContainsString('hooks.slack.com', $html);
	}

	public static function webhooks(): array
	{
		return [
			'http' => ['http://hooks.slack.com/services/T/B/x', false],
			'not a URL' => ['hooks.slack.com/services/T/B/x', false],
			'https' => ['https://hooks.slack.com/services/T/B/x', true],
		];
	}

	#[DataProvider('webhooks')]
	public function test_enabling_slack_needs_an_https_webhook($webhook, $valid)
	{
		$option = $this->app()->em()->find('XF:Option', 'monologSlack');
		$value = ['enabled' => true, 'webhook' => $webhook];

		$this->assertSame($valid, \Hampel\Monolog\Option\SlackWebhook::verifyOption($value, $option));
	}

	public function test_a_save_from_elsewhere_keeps_an_option_in_a_switched_off_section()
	{
		$this->setConfig('monolog', ['email' => false]);
		$option = $this->app()->em()->find('XF:Option', 'monologEmailSubject');
		$value = 'Something else';

		$this->assertTrue(\Hampel\Monolog\Option\EmailSubject::verifyOption($value, $option));
		$this->assertSame($option->option_value, $value);
	}

	/**
	 * The proof the page is safe to save: submit what the page lists, with no values, through
	 * XenForo's own controller. A listed option is saved as false; the locked one keeps its value.
	 *
	 * Either layer alone keeps this green - the locked row leaving the option unlisted, or
	 * verifyOption() keeping the stored value - so each has its own test above and below. With
	 * both removed the stored value is wiped, which is the trap being guarded.
	 */
	public function test_saving_the_page_leaves_a_locked_option_untouched()
	{
		// stored first: once config.php sets the option, verifyOption() refuses to change it
		$this->saveOptionRow('monologSendEmail', ['enabled' => true, 'email' => 'stored@example.com']);
		$this->saveOptionRow('monologAddWebExtra', true);
		$this->setConfig('monolog', ['email' => ['to' => 'config@example.com']]);

		$listed = $this->listed($this->page());
		$this->callAction('XF:Option', 'update', 'admin', ['options_listed' => $listed, 'options' => []]);

		$this->assertSame(['enabled' => true, 'email' => 'stored@example.com'], $this->storedValue('monologSendEmail'));
		$this->assertFalse((bool) $this->storedValue('monologAddWebExtra'), 'a listed option is saved as XenForo always has');
	}

	public function test_a_save_from_elsewhere_keeps_a_locked_option_value()
	{
		$this->setConfig('monolog', ['email' => ['to' => 'config@example.com']]);
		$option = $this->app()->em()->find('XF:Option', 'monologSendEmail');
		$value = ['enabled' => false, 'email' => 'other@example.com'];

		$this->assertTrue(SendEmail::verifyOption($value, $option));
		$this->assertSame($option->option_value, $value);
	}

	private function page(): string
	{
		return $this->renderReply($this->dispatch('options/groups/monolog', 'admin'));
	}

	private function listed(string $html): array
	{
		preg_match_all('/name="options_listed\[\]" value="([^"]+)"/', $html, $matches);

		return $matches[1];
	}

	private function saveOptionRow(string $optionId, $value)
	{
		$this->app()->repository('XF:Option')->updateOption($optionId, $value);
	}

	private function storedValue(string $optionId)
	{
		return $this->app()->em()->find('XF:Option', $optionId)->option_value;
	}
}
