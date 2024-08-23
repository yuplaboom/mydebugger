<?php

namespace Modules\DevTools\Debugger;

use Phalcon\Events\Event;
use Root\Log\Log as Log;
use Phalcon\Mvc\Dispatcher;

class Listener extends \Phalcon\Di\Injectable
{
	private static $instance = null;
	private array $data = [];

	private \Closure|null $initialErrorHandler = null;
	private \Closure|null $initialExceptionHandler = null;

	private function __construct()
	{
	}

	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
	{
		$params = $dispatcher->getParams();
		$controllerClass = $dispatcher->getControllerClass();
		$actionName = $dispatcher->getActionName();

		if ("api" === strtolower($dispatcher->getControllerName())) {
			$apiVersion = 'Latest';
			if (isset($params['apiVersion']) && is_numeric($params['apiVersion'])) {
				$apiVersion = 'V' . $params['apiVersion'];
			}

			$controllerClass = sprintf($params['apiClass'], $apiVersion);
			$actionName = $params['apiMethod'];
		}

		$this->data['controllerClass'] = $controllerClass;
		$this->data['actionName'] = $actionName;
		unset($params['apiClass'], $params['apiMethod'], $params['apiVersion'], $params['contextApi']);
		if ($params['aclAuth']['user']) {
			$user = $params['aclAuth']['user'] ?? null;
			if (null !== $user) {
				$this->data['user'] = [
					'email' => $user['identity']['email']['value'] ?? null,
					'userType' => $params['aclAuth']['userType'] ?? null,
					'id' => $user['id'] ?? null,
					'token' => $user['secureToken'] ?? null,
					'username' => $user['userName'] ?? null,
					'lastname' => $user['lastName'] ?? null,
					'firstname' => $user['firstName'] ?? null,
					'supplierCustomerGroup' =>  $user['identity']['marketplaceParameters']['supplierCustomerGroup']['value']['code'] ?? null,
					'acl' => $params['acl']['rule'] ?? null,
				];
			}
		}
		$request = $this->getDI()->get('request');
		$this->data['request'] = [
			'url' => "https://tr-api.lesgrappes.localdev" . $request->getURI(),
			'method' => $request->getMethod(),
			'GET' => $request->getQuery(),
			'POST' => $request->getPost(),
			'body' => json_encode(json_decode($request->getRawBody())),
			'port' => $request->getPort(),
			'ip' => $request->getServerAddress(),
			'clientIp' => $request->getClientAddress(),
		];
		unset($params['acl'], $params['aclAuth'], $params["aclIdentity"]);
		$this->data['params'] = $params;
		$this->registerHandlers();
	}

	public function registerHandlers(): void
	{
		$currentErrorHandler = set_error_handler(null);
		if ($currentErrorHandler !== [$this, 'handleError']) {
			$this->initialErrorHandler = $currentErrorHandler;
		}
		set_error_handler([$this, 'handleError']);

		$currentExceptionHandler = set_exception_handler(null);
		if ($currentExceptionHandler !== [$this, 'handleException']) {
			$this->initialExceptionHandler = $currentExceptionHandler;
		}
		set_exception_handler([$this, 'handleException']);
		register_shutdown_function([$this, 'onShutdown']);
	}

	public function handleException($e): void
	{
		$this->data['errors']['error'][] = [
			'message' => $e->getMessage(),
			'file' => explode(ROOT_APP_PATH . '/', $e->getFile())[1],
			'line' => $e->getLine(),
		];
		$this->data['response']['status'] = 500;

		if (null !== $this->initialExceptionHandler) {
			call_user_func($this->initialExceptionHandler, $e);
		}
	}

	public function handleError($errno, $errstr, $errfile, $errline): void
	{
		$type = '';
		switch ($errno) {
			case \E_WARNING:
			case \E_USER_WARNING:
			case \E_CORE_WARNING:
			case \E_COMPILE_WARNING:
			case \E_STRICT:
				$type = 'warning';
				break;
			case \E_NOTICE:
			case \E_USER_NOTICE:
				$type = 'notice';
				break;
			case \E_DEPRECATED:
			case \E_USER_DEPRECATED:
				$type = 'info';
				break;
		}

		$this->data['errors'][$type][] = [
			'message' => $errstr,
			'file' => explode(ROOT_APP_PATH . '/', $errfile)[1],
			'line' => $errline,
		];

		if (null !== $this->initialErrorHandler) {
			call_user_func($this->initialErrorHandler, $errno, $errstr, $errfile, $errline);
		}
	}

	public function onShutdown(): void
	{
		if (!empty($this->data['errors']['error'])) {
			Log::debug(json_encode($this->data), 'Debugger');
		}
	}

	public function afterCompiledResponse(Event $event, \Root\Phalcon\Api\Response $response): void
	{
		$this->data['response'] = [
			'status' => $response->compiledResponse['status'],
			'message' => $response->compiledResponse['statusMessage'],
			'data' => json_encode($response->compiledResponse['data'] ?? []),
			'contextApi' => json_encode($response->compiledResponse['contextApi'] ?? []),
		];
		Log::debug(json_encode($this->data), 'Debugger');
	}
}
