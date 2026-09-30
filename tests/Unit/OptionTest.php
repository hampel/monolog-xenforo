<?php namespace Tests\Unit;

use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailSubject;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\SendEmail;
use Tests\TestCase;

class OptionTest extends TestCase
{
	public function test_the_subject_replaces_the_board_token()
	{
		$this->setOptions([
			'monologEmailSubject' => 'Errors on {board}',
			'boardTitle' => 'Test Board',
		]);

		$this->assertSame('Errors on Test Board', EmailSubject::get());
	}

	public function test_an_empty_subject_falls_back_to_monolog()
	{
		$this->setOption('monologEmailSubject', '');

		$this->assertSame('Monolog', EmailSubject::get());
	}

	public function test_an_unset_file_level_falls_back_to_warning()
	{
		$this->setOption('monologFileMinimumLogLevel', 0);

		$this->assertSame(300, FileMinimumLogLevel::get());
	}

	public function test_an_unset_email_level_falls_back_to_error()
	{
		$this->setOption('monologEmailMinimumLogLevel', 0);

		$this->assertSame(400, EmailMinimumLogLevel::get());
	}

	public function test_the_email_address_is_empty_when_email_is_disabled()
	{
		$this->setOption('monologSendEmail', ['enabled' => false, 'email' => 'logs@example.com']);

		$this->assertSame('', SendEmail::getAddress());
	}
}
