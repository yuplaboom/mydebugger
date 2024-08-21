<?php

class Debugger_Logs_Model_Observer
{
	const BASE_URL = 'https://www.lesgrappes.localdev';
	const LOG_FILENAME = 'debugger_logs.log';

	protected $isLogged = false;

	protected $controllerInfo = '';

	protected $url = '';

	protected $logMessage = "";

	const BLOCK_NOT_TO_LOG = [
		'UIOptimization',
		'GoogleAnalytics',
	];

	const CONTROLLERS_NOT_TO_LOG = [
		'Mage_Cms_IndexController'
	];

	public function logTemplates(Varien_Event_Observer $observer)
	{
		if (Mage::getIsDeveloperMode() === false) {
			return;
		}

		if ($this->isLogged) {
			return;
		}

		$layout = Mage::getSingleton('core/layout');
		$rootBlock = $layout->getBlock('root');
		$response = Mage::app()->getResponse();
		$body = $response->getBody();
		$statusCode = $response->getHttpResponseCode();
		if ($this->controllerInfo) {
			foreach (self::CONTROLLERS_NOT_TO_LOG as $notToLog) {
				if (strpos($this->controllerInfo, $notToLog) !== false) {
					return;
				}
			}
		}
		$this->initFormattedLog($statusCode);

		if ($rootBlock || $this->isJson($body)) {
			$this->isLogged = true;
			$this->addLogLine("Controller: " . $this->controllerInfo);
			if (Mage::getSingleton('customer/session')->isLoggedIn()) {
				$customer = Mage::getSingleton('customer/session')->getCustomer();
				$this->addLogLine("Customer ID: " . $customer->getId());
				$this->addLogLine("Customer Email: " . $customer->getEmail());
			} else {
				$this->addLogLine("Customer ID: No customer is logged in.");
				$this->addLogLine("Customer Email: No customer is logged in.");
			}
			if ($this->isJson($body)) {
				$json = json_decode($body, true);
				if (isset($json['html'])) {
					$json['html'] = 'some html content';
				}
				$this->addLogLine("Response Data: " . json_encode($json));
			} else {
				$structure = $this->buildTemplateHierarchy($rootBlock);
				$this->addLogLine("Templates: " . $this->formatTemplateHierarchy($structure));
			}
			$this->sendLog();
		}
	}
	public function captureControllerInfo(Varien_Event_Observer $observer)
	{
		if (Mage::getIsDeveloperMode() === false) {
			return;
		}

		$controller = $observer->getControllerAction();
		$this->controllerInfo = get_class($controller) . '::' . $controller->getRequest()->getActionName() . 'Action';
		$this->url = Mage::app()->getRequest()->getRequestUri();
		set_error_handler([$this, 'handleErrors']);
		register_shutdown_function([$this, 'shutdownHandler']);
	}


	public function shutdownHandler()
	{
		$this->initFormattedLog(500);

		$error = error_get_last();
		if ($error !== null) {
			$this->addLogLine("Error: Fatal error on line " . $error['line'] . " in file " . $error['file']);
			$this->addLogLine("Error Code: " . $error['type']);
			$this->addLogLine('Message: ' . $error['message']);
			$this->sendLog();
		}
	}

	public function handleErrors($errno, $errstr, $errfile, $errline): bool
	{
		if ($this->isLogged) {
			return true;
		}

		if (!(error_reporting() & $errno)) {
			return true;
		}

		$code = 200;
		if ($errno === E_ERROR) {
			$code = 500;
		}
		$this->initFormattedLog($code);

		switch ($errno) {
			case E_ERROR:
				$this->addLogLine("Error: Fatal error on line $errline in file $errfile");
				$this->addLogLine("Error Code: " . $errno);
				$this->addLogLine('Message: ' . $errstr);
				$this->sendLog();
				exit(1);
				return true;

			case E_WARNING:
				$this->addLogLine("Warning: $errstr on line $errline in file $errfile");
				return true;

			case E_NOTICE:
				$this->addLogLine("Notice: $errstr on line $errline in file $errfile");
				return true;

			default:
				$this->addLogLine("Unknown error type: [$errno] $errstr on line $errline in file $errfile");
				return true;
		}
	}

	protected function buildTemplateHierarchy($block)
	{
		if (!$block) {
			return [];
		}

		$template = $block->getTemplateFile();
		$templateHierarchy = [];

		if ($template) {
			$blockClass = get_class($block);
			$shouldLog = true;
			foreach (self::BLOCK_NOT_TO_LOG as $notToLog) {
				if (strpos($blockClass, $notToLog) !== false) {
					$shouldLog = false;
				}
			}
			if ($shouldLog) {
				$templateHierarchy = $this->buildPathHierarchy($template, get_class($block));
			}
		}

		foreach ($block->getSortedChildren() as $childName) {
			$childBlock = $block->getLayout()->getBlock($childName);
			if ($childBlock) {
				$childHierarchy = $this->buildTemplateHierarchy($childBlock);
				$templateHierarchy = $this->mergeHierarchies($templateHierarchy, $childHierarchy);
			}
		}

		return $templateHierarchy;
	}

	protected function buildPathHierarchy($template, $blockClass)
	{
		$parts = explode(DS, $template);
		$fileName = array_pop($parts);
		$hierarchy = [];
		$currentLevel = &$hierarchy;

		foreach ($parts as $part) {
			if (!isset($currentLevel[$part])) {
				$currentLevel[$part] = [];
			}
			$currentLevel = &$currentLevel[$part];
		}

		$currentLevel[$fileName] = $template . ' - Block ' . $blockClass;

		return $hierarchy;
	}

	protected function mergeHierarchies($a, $b)
	{
		foreach ($b as $key => $value) {
			if (isset($a[$key]) && is_array($a[$key]) && is_array($value)) {
				$a[$key] = $this->mergeHierarchies($a[$key], $value);
			} else {
				$a[$key] = $value;
			}
		}
		return $a;
	}

	protected function formatTemplateHierarchy($structure, $level = 0)
	{
		$output = '';
		foreach ($structure as $key => $value) {
			$indent = str_repeat('  ', $level);
			if (is_array($value)) {
				$output .= $indent . $key . ":\n";
				$output .= $this->formatTemplateHierarchy($value, $level + 1);
			} else {
				$output .= $indent . $key . " - " . $value . "\n";
			}
		}
		return $output;
	}

	protected function isJson($string): bool
	{
		$json = json_decode($string, true);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	protected function addLogLine(string $string): void
	{
		$this->logMessage .= "\n" . $string;
	}

	protected function sendLog(): void
	{
		$this->isLogged = true;
		Mage::log($this->logMessage, null, self::LOG_FILENAME, true);
	}

	protected function initFormattedLog(int $statusCode)
	{
		if ($this->logMessage !== "") {
			return;
		}
		$this->addLogLine("HTTP Response Status: ". $statusCode);
		$this->addLogLine("URL: ". self::BASE_URL . $this->url);
		$request = Mage::app()->getRequest();
		$request->getMethod();
		$this->addLogLine("Request Method: " . $request->getMethod());
		$getParams = $request->getParams();
		$postParams = $request->getPost();
		if ($getParams) {
			$this->addLogLine("GET Params: " . json_encode($getParams));
		}
		if ($postParams) {
			$this->addLogLine("POST Params: " . json_encode($postParams));
		}
	}

}
