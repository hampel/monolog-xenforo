<?php

namespace Hampel\Monolog\Test;

abstract class AbstractTest
{
	protected $app;
	protected $controller;
	protected $data;
	protected $defaultData = [];
	protected $messages = [];

	abstract public function run();

	public function __construct(
		\XF\App $app,
		\XF\Admin\Controller\AbstractController $controller,
		array $data = []
	)
	{
		$this->app = $app;
		$this->controller = $controller;
		$this->data = $this->setupData($data);
	}

	protected function setupData(array $data)
	{
		return array_merge($this->defaultData, $data);
	}

	public function getData()
	{
		return $this->data;
	}

	public function getMessages()
	{
		return $this->messages;
	}

	public function getErrorMessages()
	{
		return array_filter($this->messages, function($value) {
			return (isset($value['type']) && ($value['type'] == 'error'));
		});
	}

	public function getSuccessMessages()
	{
		return array_filter($this->messages, function($value) {
			return (isset($value['type']) && ($value['type'] == 'success'));
		});
	}

	protected function message($type, $message)
	{
		$this->messages[] = compact('type', 'message');
	}

	protected function errorMessage($message)
	{
		$this->message('error', $message);
	}

	protected function successMessage($message)
	{
		$this->message('success', $message);
	}
}