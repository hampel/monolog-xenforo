<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * The ACP Test Monolog page, Tools > Checks and tests.
 */
class ToolsControllerTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'monologFileMinimumLogLevel' => 100, // DEBUG, so every test message is written
		]);
	}

	public function test_the_page_needs_the_option_admin_permission()
	{
		$this->actingAsMember(['is_admin' => true]);

		$reply = $this->dispatch('tools/test-monolog', 'admin');

		$this->assertReplyIsError($reply, 403);
	}

	public function test_the_page_renders_for_an_admin_with_the_permission()
	{
		$this->actingAsOptionAdmin();

		$reply = $this->dispatch('tools/test-monolog', 'admin');

		$this->assertReplyTemplate($reply, 'monolog_tools_test_monolog');

		$html = $this->renderReply($reply);
		$this->assertNoUnresolvedPhrases($html, 'monolog_');
		$this->assertNoTemplateErrors();
	}

	public function test_running_the_test_writes_one_message_at_every_level()
	{
		$this->actingAsOptionAdmin();

		$reply = $this->callAction('XF:Tools', 'test-monolog', 'admin', [
			'test' => 'Hampel\Monolog:LoggerTest',
		]);

		$this->assertTrue($this->replyParam($reply, 'results'));

		$lines = $this->logLines();
		$this->assertCount(8, $lines);
		foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'] AS $i => $level)
		{
			$this->assertStringContainsString("] monolog-test.{$level}: ", $lines[$i]);
		}
	}

	private function actingAsOptionAdmin()
	{
		$admin = $this->actingAsMember(['is_admin' => true]);
		$this->setVisitorAdminPermissions($admin, ['option' => true]);

		return $admin;
	}
}
