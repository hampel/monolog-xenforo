<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;
use XF\Option\AbstractOption;

/**
 * An option that `$config['monolog']` in config.php can set instead.
 *
 * While config.php sets it, the options page shows the value in force and no input. That is not
 * only for clarity: the page submits a list of the options it showed, and XenForo saves `false`
 * for any listed option whose input sent nothing - so a merely disabled input would wipe the stored
 * value on every save. A locked row leaves `listedHtml` out, so the option is never listed, and
 * verifyOption() keeps the stored value for any save that arrives some other way.
 */
abstract class AbstractConfigurableOption extends AbstractOption
{
	/**
	 * The config.php section this option belongs to - 'file', 'email' or 'slack' - for an option
	 * that means nothing once config.php switches that section off. Null for the section's own
	 * on/off option, which shows the switch itself, and for options outside every section.
	 */
	protected const DEPENDS_ON = null;

	/**
	 * The value config.php sets, ready to show; null when config.php does not set this option.
	 */
	abstract public static function lockedValue(): ?string;

	/**
	 * The option's own control, for when config.php does not set it.
	 */
	abstract protected static function renderControl(Option $option, array $htmlParams): string;

	/**
	 * Whether config.php switches off the section this option belongs to.
	 */
	public static function isSectionDisabled(): bool
	{
		return static::DEPENDS_ON !== null && Config::enabled(static::DEPENDS_ON) === false;
	}

	public static function isLocked(): bool
	{
		return static::isSectionDisabled() || static::lockedValue() !== null;
	}

	public static function renderOption(Option $option, array $htmlParams)
	{
		if (static::isSectionDisabled())
		{
			// each phrase named literally, so xf-dev:unused-phrase-finder can see it is used
			$note = static::DEPENDS_ON === 'email'
				? \XF::phrase('monolog_email_disabled_in_config_php')
				: \XF::phrase('monolog_file_logging_disabled_in_config_php');

			return static::lockedRow($option, $htmlParams, (string) \XF::phrase('monolog_not_used'), $note);
		}

		$locked = static::lockedValue();
		if ($locked === null)
		{
			return static::renderControl($option, $htmlParams);
		}

		return static::lockedRow($option, $htmlParams, $locked, \XF::phrase('monolog_set_in_config_php'));
	}

	/**
	 * The value in force and why it cannot be changed here - with no input, and without the
	 * `listedHtml` that would put the option on the save list. See the class docblock.
	 *
	 * @param \XF\Phrase|string $note
	 */
	protected static function lockedRow(Option $option, array $htmlParams, string $value, $note): string
	{
		$html = '<div class="formRow-value">' . \XF::escapeString($value) . '</div>'
			. '<div class="formRow-explain">' . $note . '</div>';

		return static::getTemplater()->formRow($html, [
			'label' => $option->title,
			'hint' => $htmlParams['hintHtml'],
			'explain' => $htmlParams['explainHtml'],
			'rowclass' => $htmlParams['rowClass'] ?? '',
			// no 'html' => listedHtml: see the class docblock
		]);
	}

	public static function verifyOption(&$value, Option $option)
	{
		if (static::isLocked())
		{
			$value = $option->option_value;

			return true;
		}

		return static::verifyValue($value, $option);
	}

	protected static function verifyValue(&$value, Option $option): bool
	{
		return true;
	}

	/**
	 * The row options XenForo's own renderer uses, including the `listedHtml` that puts the option
	 * on the page's list of options to save.
	 */
	protected static function rowOptions(Option $option, array $htmlParams, bool $withLabel = true): array
	{
		$rowOptions = [
			'hint' => $htmlParams['hintHtml'],
			'explain' => $htmlParams['explainHtml'],
			'html' => $htmlParams['listedHtml'],
			'rowclass' => $htmlParams['rowClass'] ?? '',
		];
		if ($withLabel)
		{
			$rowOptions['label'] = $option->title;
		}

		return $rowOptions;
	}

	protected static function renderOnOff(Option $option, array $htmlParams): string
	{
		return static::getTemplater()->formCheckBoxRow([], [[
			'name' => $htmlParams['inputName'],
			'value' => 1,
			'selected' => (bool) $option->option_value,
			'label' => $option->title,
		]], static::rowOptions($option, $htmlParams, false));
	}

	protected static function renderOnOffTextBox(
		Option $option, array $htmlParams, string $onKey, string $valueKey, string $default, string $placeholder = ''
	): string
	{
		$value = $option->option_value;
		$on = !empty($value[$onKey]);
		$templater = static::getTemplater();

		$textBox = $templater->formTextBox([
			'name' => "{$htmlParams['inputName']}[{$valueKey}]",
			'value' => $on ? ($value[$valueKey] ?? '') : $default,
			'placeholder' => $placeholder,
		]);

		return $templater->formCheckBoxRow([], [[
			'name' => "{$htmlParams['inputName']}[{$onKey}]",
			'value' => 1,
			'selected' => $on,
			'label' => $option->title,
			'_dependent' => [$textBox],
		]], static::rowOptions($option, $htmlParams, false));
	}

	protected static function renderTextBox(Option $option, array $htmlParams): string
	{
		return static::getTemplater()->formTextBoxRow([
			'name' => $htmlParams['inputName'],
			'value' => $option->option_value,
		], static::rowOptions($option, $htmlParams));
	}

	protected static function renderNumberBox(Option $option, array $htmlParams, int $min = 0): string
	{
		return static::getTemplater()->formNumberBoxRow([
			'name' => $htmlParams['inputName'],
			'value' => $option->option_value,
			'min' => $min,
		], static::rowOptions($option, $htmlParams));
	}

	/**
	 * @param array $choices value => label
	 */
	protected static function renderSelect(Option $option, array $htmlParams, array $choices, $value): string
	{
		$options = [];
		foreach ($choices AS $choiceValue => $label)
		{
			$options[] = ['_type' => 'option', 'value' => $choiceValue, 'label' => $label];
		}

		return static::getTemplater()->formSelectRow([
			'name' => $htmlParams['inputName'],
			'value' => $value,
		], $options, static::rowOptions($option, $htmlParams));
	}
}
