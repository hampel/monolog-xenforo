<?php namespace Hampel\Monolog\Option;

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
	 * The value config.php sets, ready to show; null when config.php does not set this option.
	 */
	abstract public static function lockedValue(): ?string;

	/**
	 * The option's own control, for when config.php does not set it.
	 */
	abstract protected static function renderControl(Option $option, array $htmlParams): string;

	public static function renderOption(Option $option, array $htmlParams)
	{
		$locked = static::lockedValue();
		if ($locked === null)
		{
			return static::renderControl($option, $htmlParams);
		}

		$html = '<div class="formRow-value">' . \XF::escapeString($locked) . '</div>'
			. '<div class="formRow-explain">' . \XF::phrase('monolog_set_in_config_php') . '</div>';

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
		if (static::lockedValue() !== null)
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
