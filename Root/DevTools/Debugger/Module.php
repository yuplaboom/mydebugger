<?php

namespace Modules\DevTools\Debugger;

use Phalcon\Logger\Adapter\File as FileAdapter;

class Module extends \Root\Phalcon\Mvc\Module
{
	public $applications = ['Api', 'Zeus', 'TransientApi'];

	public function registerServices(\Phalcon\Di\DiInterface $di)
	{
		$listener = Listener::getInstance();

		$di->getShared('eventsManager')->attach('application:beforeHandleRequest', [$listener, 'registerHandlers']);
		$di->getShared('eventsManager')->attach('dispatch:beforeExecuteRoute', [$listener, 'beforeExecuteRoute']);
		$di->getShared('eventsManager')->attach('responseApi:afterCompiledResponse', [$listener, 'afterCompiledResponse']);
	}
}
