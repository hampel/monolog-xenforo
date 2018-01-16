<?php

namespace Monolog\Test;

use XF\App;
use XF\Admin\Controller\AbstractController;

abstract class AbstractTest
{
	protected $app;
	protected $controller;
	protected $data;
	protected $defaultData = [];
	protected $messages = [];

	abstract public function run();

	public function __construct(App $app, AbstractController $controller, array $data = [])
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

	protected function message($type = 'none', $message)
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