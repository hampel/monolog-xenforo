<?php namespace Tests\Unit;

use Hampel\Monolog\Listener;
use Hampel\Monolog\Option\SlackWebhook;
use Tests\TestCase;

/**
 * The declaration to Hampel/AdminApi of which settings are secrets. It goes stale silently when an
 * option is renamed or added, so it is checked against _output/options/ - and against the code,
 * since a path that names nothing the add-on reads protects nothing.
 */
class SecretsDeclarationTest extends TestCase
{
	/** Options or option.members that read like a credential and are not one, with each reason. */
	private const NOT_SECRETS = [];

	private const CREDENTIAL = '/key|token|secret|password|webhook/i';

	public function test_it_declares_this_add_on_only()
	{
		$this->assertSame(['Hampel/Monolog'], array_keys($this->declarations()));
	}

	public function test_every_declared_secret_is_an_option_this_add_on_has()
	{
		foreach ($this->declared()['options'] AS $path)
		{
			$parts = explode('.', $path);
			$file = $this->addOnRoot() . "/_output/options/{$parts[0]}.json";
			$this->assertFileExists($file, $path);

			// a member must be one the option actually has
			if (isset($parts[1]))
			{
				$option = json_decode(file_get_contents($file), true);
				$this->assertContains($parts[1], $option['sub_options'],
					"{$path} is not a member of {$parts[0]}");
			}
		}
	}

	/**
	 * A new option, or a new member of an array option, that reads like a credential fails here
	 * until somebody decides. Members matter as much as ids: this add-on's one secret is the
	 * `webhook` member of `monologSlack`, whose id reads like nothing at all.
	 */
	public function test_an_option_or_member_that_reads_like_a_credential_is_declared_or_decided()
	{
		$decided = array_merge(self::NOT_SECRETS, $this->declared()['options']);

		$undecided = [];
		foreach ($this->optionIds() AS $optionId)
		{
			if (preg_match(self::CREDENTIAL, $optionId)
				&& !in_array($optionId, $decided, true))
			{
				$undecided[] = $optionId;
			}

			$file = $this->addOnRoot() . "/_output/options/{$optionId}.json";
			$option = json_decode(file_get_contents($file), true);
			foreach ($option['sub_options'] ?? [] AS $member)
			{
				$path = "{$optionId}.{$member}";
				if (preg_match(self::CREDENTIAL, $member) && !in_array($path, $decided, true)
					&& !in_array($optionId, $decided, true))
				{
					$undecided[] = $path;
				}
			}
		}

		$this->assertSame([], $undecided, 'declare each as a secret, or add it to NOT_SECRETS');
	}

	/**
	 * The config.php half: the key claimed is the one the add-on reads, and the secret's path is
	 * one the add-on actually takes a value from - proved by setting it and reading it back.
	 */
	public function test_the_declared_config_secret_is_where_the_add_on_reads_its_webhook()
	{
		$this->assertSame(['monolog'], array_keys($this->declared()['config']));
		$this->assertSame(['monolog.slack.webhook'], $this->declared()['config']['monolog']);

		$webhook = 'https://hooks.example.com/services/T/B/x';
		$this->setConfig('monolog', ['slack' => ['webhook' => $webhook]]);

		$this->assertSame($webhook, SlackWebhook::getWebhook());
	}

	private function declarations(): array
	{
		$declarations = [];
		Listener::adminApiRedaction($declarations);

		return $declarations;
	}

	private function declared(): array
	{
		return $this->declarations()['Hampel/Monolog'] + ['options' => [], 'config' => []];
	}

	private function optionIds(): array
	{
		$ids = [];
		foreach (glob($this->addOnRoot() . '/_output/options/*.json') ?: [] AS $file)
		{
			if (basename($file) != '_metadata.json')
			{
				$ids[] = basename($file, '.json');
			}
		}

		return $ids;
	}

	private function addOnRoot(): string
	{
		return dirname(__DIR__, 2);
	}
}
