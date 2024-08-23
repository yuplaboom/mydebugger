<?php

namespace Root\Phalcon\Application;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Transport\Exception\NoNodeAvailableException;
use MongoDB\Client as MongoDbClient;
use Phalcon\Cache\Backend\Redis;
use Phalcon\Cache\Frontend\Data;
use Phalcon\Db\Dialect\MysqlExtended;
use Phalcon\Html\Escaper;
use Phalcon\Html\TagFactory;
use Phalcon\Mvc\Router;
use Pheanstalk\Pheanstalk;
use Root\Core\Error\Category;
use Root\Core\Error\Error;
use Root\Core\Intl;
use Root\Phalcon\Db\Manager;
use Root\Phalcon\Elasticsearch\Adapter;
use Root\Utility\ArrayTool;

trait Loader
{
    public function loadSystem(): self
    {
        $this->loadConfig();
        $this->loadLog();
        $this->loadErrorHandler();
        $this->loadCaches();
        $this->loadIntl();
        $this->loadTranslate();
        $this->loadMongo();
        $this->loadRedis();
        $this->loadMailer();
        $this->loadBeanstalk();
        $this->loadRsmq();
        $this->loadAppCaches();
        $this->loadDbAdapter();
        $this->loadModelsMetadataCache();
        $this->loadModelsCache();
        $this->loadRouter();
        $this->loadHookView();
        $this->loadView();
        $this->loadUrl();
        $this->loadFilter();
        $this->loadAssets();
        $this->loadSession();
        $this->loadDispatcher();
        $this->loadRootAutoloader();
        $this->loadModules();
        $this->loadFunctions();
        $this->loadElasticsearch();
        
        return $this;
    }
    
    private function loadConfig(): void
    {
        $env = defined('ROOT_APP_ENV') ? ROOT_APP_ENV : 'prod';
        
        if (file_exists(ROOT_APP_PATH . '/.version')) {
            $version = @file_get_contents(ROOT_APP_PATH . '/.version');
        }else{
            $version = date('M');
        }
        define('ROOT_APP_VER',$version);
        
        $settingsPath = ROOT_APP_PATH . '/Configs/' . ucfirst($env) . '/';
        $defaultSettings = include($settingsPath . 'Settings.default.php');
        $userSettings = is_file($settingsPath . 'Settings.php') ? include($settingsPath . 'Settings.php') : [];
        
        if (empty($defaultSettings)) {
            exit ("Can't find default settings file.\n");
        }
        $settings = ArrayTool::mergeRecursive($defaultSettings, $userSettings);
        if (!empty($settings['applications'])) {
            foreach ($settings['applications'] as $name => $applicationSettings) {
                if (isset($applicationSettings['enable']) && $applicationSettings['enable'] === false) {
                    unset($settings['applications'][$name]);
                }
            }
        }
        
        define('LG_DISPLAY_PHP_ERRORS', !empty($settings['switches']['displayPhpErrors']));
        
        define('LG_LOG_PHP', !empty($settings['switches']['logPhp']));
        define('LG_LOG_PHP_ERROR', (LG_LOG_PHP && !empty($settings['switches']['logPhpError'])));
        define('LG_LOG_PHP_WARNING', (LG_LOG_PHP && !empty($settings['switches']['logPhpWarning'])));
        define('LG_LOG_PHP_INFO', (LG_LOG_PHP && !empty($settings['switches']['logPhpInfo'])));
        define('LG_LOG_PHP_DEBUG', (LG_LOG_PHP && !empty($settings['switches']['logPhpDebug'])));
        
        define('LG_LOG_SQL', !empty($settings['switches']['logSql']));
        define('LG_LOG_SQL_QUERY', (LG_LOG_SQL && !empty($settings['switches']['logSqlQuery'])));
        
        define('LG_CACHE', !empty($settings['switches']['cache']));
        define('LG_CACHE_REDIS', (LG_CACHE && !empty($settings['switches']['cacheRedis'])));
        define('LG_CACHE_MODELS_METADATA', (LG_CACHE && !empty($settings['switches']['cacheModelsMetadata'])));
        define('LG_CACHE_MODELS', (LG_CACHE && !empty($settings['switches']['cacheModels'])));
        
        $defaultModules = include ROOT_APP_PATH . '/Configs/Modules.php';
        $userModules = is_file($settingsPath . 'Modules.php') ? include($settingsPath . 'Modules.php') : [];
        $modules = ArrayTool::mergeRecursive($defaultModules, $userModules);
        
        $configs = [
            'modules' => $modules,
            'settings' => $settings,
            'env' => $env,
            'version' => $version,
            'defaultLocale' => 'en_US',
            'locale' => (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? \Locale::acceptFromHttp(
                $_SERVER["HTTP_ACCEPT_LANGUAGE"]
            ) : false),
        ];
        
        $this->container->setShared(
            'config',
            function () use ($configs) {
                if (empty($configs['locale'])) {
                    $configs['locale'] = $configs['defaultLocale'];
                }
                $configs['language'] = \Locale::getPrimaryLanguage($configs['locale']);
                
                return new \Phalcon\Config\Config($configs);
            }
        );
    }
    
    private function loadLog(): void
    {
        if (LG_LOG_SQL_QUERY) {
            $this->container->getShared('eventsManager')->attach(
                'db',
                function ($event, $connection) {
                    if ($event->getType() == 'beforeQuery') {
                        $sqlVariables = $connection->getSQLVariables();
                        
                        if(!empty($connection->getSQLVariables())){
                            $sqlVariables = implode(', ', array_map(
                                function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
                                $connection->getSQLVariables(),
                                array_keys($connection->getSQLVariables())
                            ));
                        }else{
                            $sqlVariables = '';
                        }
                        \Root\Log\Log::info($connection->getSQLStatement() . ' | ' . $sqlVariables);
                    }
                }
            );
        }
    }
    
    private function loadErrorHandler(): void
    {
        /*if (LG_DISPLAY_PHP_ERRORS) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL); // TODO put reporting level in settings instead?
        } else {
            ini_set('display_errors', 0);
            error_reporting(0);
        }*/
        
        \Root\Core\Error\Handler::register();
    }
    
    private function loadCaches(): void
    {
        $caches = $this->container->get('config')->path('settings.caches', [])->toArray();
        if (!empty($caches)) {
            foreach ($caches as $cacheName => $cacheConfig) {
                if ($cacheConfig['enable'] == false) {
                    continue;
                }
                
                $this->container->setShared(
                    $cacheName . 'Cache',
                    function () use ($cacheConfig) {
                        return new $cacheConfig['adapterClass'](
                            new \Root\Phalcon\Storage\SerializerFactory(),
                            $cacheConfig['options']
                        );
                    }
                );
            }
        }
    }
    
    private function loadIntl(): void
    {
        $this->container->setShared(
            'intl',
            function () {
                $intl = new Intl();
                return $intl;
            }
        );
    }
    
    private function loadTranslate(): void
    {
        $interpolator = new \Phalcon\Translate\InterpolatorFactory(
            ['intl' => '\Root\Phalcon\Translate\Interpolator\Intl']
        );
        $this->container->setShared(
            'translate',
            new \Root\Phalcon\Translate\Adapter\NativeArray($interpolator, ['defaultInterpolator' => 'intl'])
        );
    }
    
    private function loadMongo(): void
    {
        $mongoDatabases = $this->container->get('config')->path('settings.mongo', [])->toArray();
        
        if (!empty($mongoDatabases)) {
            foreach ($mongoDatabases as $name => $mongoDbConfig) {
                $this->container->setShared(
                    $name,
                    function () use ($mongoDbConfig) {
                        $host = 'mongodb://' . $mongoDbConfig['server'] . ':' . $mongoDbConfig['port']; // MongoDB server host
                        $client = new MongoDbClient($host);
                        
                        return ($client->selectDatabase($mongoDbConfig['database']));
                    }
                );
            }
        }
    }
    
    private function loadAppCaches(): void
    {
        $container = $this->container;
        
        $caches = $container->get('config')->path('settings.caches', [])->toArray();
        if (!empty($caches)) {
            foreach ($caches as $cacheName => $cacheConfigs) {
                if ($cacheConfigs['enable'] == false) {
                    continue;
                }
                
                $container->setShared(
                    $cacheName . 'Cache',
                    function () use ($cacheConfigs, $container) {
                        return new $cacheConfigs['adapterClass'](
                            new \Root\Phalcon\Storage\SerializerFactory(),
                            $cacheConfigs['options']
                        );
                    }
                );
            }
        }
    }
    
    /**
     * @deprecated
     */
    private function loadRedis(): void
    {
        if (LG_CACHE_REDIS) {
            $redisConfig = $this->container->get('config')->path('settings.redis', false)->toArray();
            if (!empty($redisConfig)) {
                $this->container->setShared(
                    'redis',
                    function () use ($redisConfig) {
                        $redis = new \Redis();
                        $redis->connect($redisConfig['host'], $redisConfig['port']);
                        return $redis;
                    }
                );
            }
        }
    }
    
    private function loadMailer(): void
    {
        $mailerConfig = $this->container->get('config')->path('settings.mailer', false)->toArray();
        if (!empty($mailerConfig)) {
            $container = $this->container;
            $this->container->setShared(
                'mailer',
                function () use ($container, $mailerConfig) {
                    $eventsManager = $container->getShared('eventsManager');
                    $container->getShared('eventsManager')->attach(
                        'mailer:beforeSend',
                        function ($event, \Phalcon\Mailer\Message $message) use ($mailerConfig) {
                            if ($mailerConfig['debug'] != false) {
                                $message->to($mailerConfig['debug']);
                            }
                        }
                    );
                    
                    $mailer = new \Phalcon\Mailer\Manager($mailerConfig);
                    $mailer->setEventsManager($eventsManager);
                    return $mailer;
                }
            );
        }
    }
    
    private function loadBeanstalk(): void
    {
        $beanstalkConfig = $this->container->get('config')->path('settings.beanstalk', false)->toArray();
        if (!empty($beanstalkConfig)) {
            $this->container->setShared(
                'beanstalk',
                function () use ($beanstalkConfig) {
                    return Pheanstalk::create($beanstalkConfig['host'], $beanstalkConfig['port']);
                }
            );
        }
    }
    
    private function loadRsmq(): void
    {
        $rsmqConfig = $this->container->get('config')->path('settings.rsmq', false)->toArray();
        if (!empty($rsmqConfig)) {
            $container = $this->container;
            $this->container->setShared(
                'rsmq',
                function () use ($container,$rsmqConfig) {
                    $rsmq = new \Root\Rsmq\Manager(new \Root\Rsmq\Client($rsmqConfig));
                    
                    $container->getShared('eventsManager')->attach(
                        'cli:terminateRequested',
                        function ($event, $task) use ($rsmq) {
                            $rsmq->getClient()->close();
                        }
                    );
                    return $rsmq;
                }
            );
        }
    }
    
    private function loadDbAdapter(): void
    {
        $mysqlDatabases = $this->container->get('config')->path('settings.databases', [])->toArray();
        if (!empty($mysqlDatabases)) {
            $container = $this->container;
            foreach ($mysqlDatabases as $dbName => $dbConfig) {
                $this->container->set(
                    $dbName == 'db' ? $dbName : 'db_' . $dbName,
                    function () use ($dbConfig, $container) {
                        
                        $dbclass = strpos($dbConfig['adapter'],'\\') !== 0?'Phalcon\Db\Adapter\Pdo\\' . $dbConfig['adapter']:$dbConfig['adapter'];
                        $connection = new $dbclass(
                            [
                                'host' => $dbConfig['host'],
                                'username' => $dbConfig['username'],
                                'password' => $dbConfig['password'],
                                'dbname' => $dbConfig['db'],
                                'charset' => 'utf8',
                                'dialectClass' => '\Root\Phalcon\Db\Dialect\MysqlExtended',
                                'options' => [
                                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                                    \PDO::ATTR_EMULATE_PREPARES => true,
                                ],
                            ]
                        );
                        $connection->setEventsManager($container->getShared('eventsManager'));
                        return $connection;
                    }
                );
            }
        }
        
        $this->container->setShared(
            'dbManager',
            function () {
                return new Manager();
            }
        );
    }
    
    private function loadElasticsearch(): void
    {
        $elasticsearchConfig = $this->container->get('config')->path('settings.elasticsearch', [])->toArray();
        if (!empty($elasticsearchConfig)) {
            $this->container->setShared(
                'elasticsearch',
                function () use ($elasticsearchConfig) {
                    return new Adapter($elasticsearchConfig);
                }
            );
        }
    }
    
    private function loadModelsMetadataCache(): void
    {
        $container = $this->container;
        $this->container->set(
            'modelsMetadata',
            function () use ($container) {
                $redisConfig = $container->get('config')->path('settings.redis', false)->toArray();
                
                if (LG_CACHE_MODELS_METADATA && !empty($redisConfig)) {
                    $serializerFactory = new \Phalcon\Storage\SerializerFactory();
                    $adapterFactory = new \Phalcon\Cache\AdapterFactory($serializerFactory);
                    $options = [
                        'host' => $redisConfig['host'],
                        'port' => $redisConfig['port'],
                        'index' => 1,
                        'lifetime' => 86400,
                        'prefix' => 'root:phm:metadata',
                    ];
                    return new \Phalcon\Mvc\Model\MetaData\Redis($adapterFactory, $options);
                } else {
                    return new \Phalcon\Mvc\Model\MetaData\Memory();
                }
            }
        );
    }
    
    private function loadModelsCache(): void
    {
        if (LG_CACHE_MODELS) {
            $container = $this->container;
            $this->container->set(
                'modelsCache',
                function () use ($container) {
                    $redisConfig = $container->get('config')->path('settings.redis', false)->toArray();
                    
                    $serializerFactory = new \Phalcon\Storage\SerializerFactory();
                    $adapterFactory = new \Phalcon\Cache\AdapterFactory($serializerFactory);
                    $options = [
                        'host' => $redisConfig['host'],
                        'port' => $redisConfig['port'],
                        'index' => 1,
                        'lifetime' => 86400,
                        'prefix' => 'phm_cache',
                    ];
                    
                    return new \Phalcon\Mvc\Model\MetaData\Redis($adapterFactory, $options);
                }
            );
        }
    }
    
    private function loadRouter(): void
    {
        $container = $this->container;
        $this->container->setShared(
            'router',
            function () use ($container) {
                if ($container instanceof \Phalcon\Di\FactoryDefault\Cli) {
                    $router = new \Phalcon\Cli\Router(false);
                    $router->add(
                        '#^(?::delimiter)?([a-zA-Z0-9\/_-]+):delimiter([a-zA-Z0-9\/\._-]+):delimiter([a-zA-Z0-9\._-]+)(:delimiter.*)*$#',
                        [
                            'module' => 1,
                            'task' => 2,
                            'action' => 3,
                            'params' => 4,
                        ]
                    );
                } else {
                    $router = new \Root\Phalcon\Mvc\Router(false);
                    $router->removeExtraSlashes(true);
                }
                return $router;
            }
        );
    }
    
    private function loadHookView(): void
    {
        $this->container->setShared(
            'hookView',
            function () {
                return new \Root\Phalcon\Mvc\View\Hook();
            }
        );
    }
    
    private function loadView(): void
    {
        if (!($this->container instanceof \Phalcon\Di\FactoryDefault\Cli)) {
            $container = $this->container;
            $this->container->setShared(
                'view',
                function () use ($container) {
                    $view = new \Root\Phalcon\Mvc\View();
                    $view->setEventsManager($container->getEventsManager());
                    $view->registerEngines(
                        [
                            ".phtml" => 'Phalcon\Mvc\View\Engine\Php',
                        ]
                    );
                    $view->setLayout('main');
                    $view->setBasePath(ROOT_APP_PATH);
                    
                    return $view;
                }
            );
        }
    }
    
    private function loadUrl(): void
    {
        $this->container->set(
            'url',
            function () {
                return new \Phalcon\Url();
            }
        );
    }
    
    private function loadFilter(): void
    {
        $this->container->setShared(
            'filter',
            function () {
                $factory = new \Phalcon\Filter\FilterFactory();
                $filter = $factory->newInstance();
                $filter->set(
                    'numeric',
                    function () {
                        return (new \Root\Phalcon\Filter\NumericFilter());
                    }
                );
                return $filter;
            }
        );
    }
    
    private function loadAssets(): void
    {
        $container = $this->container;
        $this->container->setShared(
            'assets',
            function () use ($container) {
                $options = $container->get('config')->path('settings.assets')->toArray();
                $options['queryParameter'] = ($container->get('config')->path('env') == 'dev')
                    ? time()
                    : $container->get('config')->path('version');
                
                $tagFactory = new TagFactory(new Escaper());
                
                return new \Root\Phalcon\Assets\Manager($tagFactory, $options);
            }
        );
    }
    
    private function loadSession(): void
    {
        $container = $this->container;
        $this->container->setShared(
            'session',
            function () use ($container) {
                $sessionSettings = $container->get('config')->settings['session']->toArray();
                $adapterClassName = '\\Phalcon\\Session\\Adapter\\' . $sessionSettings['adapter']['class'];
                
                session_set_cookie_params($sessionSettings['lifetime'], '/', $sessionSettings['domain']);
                session_name($sessionSettings['name']);
                
                $session = new $adapterClassName($sessionSettings['adapter']['configs']);
                $session->start();
                return $session;
            }
        );
    }
    
    private function loadDispatcher(): void
    {
        $container = $this->container;
        $this->container->setShared(
            'dispatcher',
            function () use ($container) {
                $eventsManager = $container->getShared('eventsManager');
                
                if ($container instanceof \Phalcon\Di\FactoryDefault\Cli) {
                    $dispatcher = new \Root\Phalcon\Cli\Dispatcher();
                } else {
                    $eventsManager->attach(
                        "dispatch:beforeException",
                        function ($event, \Root\Phalcon\Mvc\Dispatcher $dispatcher, $exception) {
                            $settings = $dispatcher->getDI()->get('config')->settings->toArray();
                            
                            //detect application
                            foreach ($settings['applications'] as $appName => $app) {
                                if (isset($app['router']['hostname']) && isset($_SERVER['HTTP_HOST'])) {
                                    $hostname = $app['router']['hostname'] . (!empty($app['router']['prefix']) ? $app['router']['prefix'] : '');
                                    $hostnameRegex = '#' . str_replace('/', '\/', $hostname) . '.*#';
                                    
                                    if (preg_match($hostnameRegex, $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])) {
                                        $dispatcher->setApplicationName($appName);
                                        break;
                                    }
                                }
                            }
                            switch ($exception->getCode()) {
                                case \Phalcon\Dispatcher\Exception::EXCEPTION_HANDLER_NOT_FOUND:
                                case \Phalcon\Dispatcher\Exception::EXCEPTION_ACTION_NOT_FOUND:
                                    if (isset(
                                        $settings['applications'][$dispatcher->getApplicationName()]['router']['route404']
                                    )) {
                                        
                                        $dispatcher->setParam('error', new Error(Category::EXCEPTION,$exception->getCode(),$exception->getMessage(),$exception->getFile(),$exception->getLine(),$exception->getTrace()));
                                        $dispatcher->forward(
                                            $settings['applications'][$dispatcher->getApplicationName()]['router']['route404']
                                        );
                                        return false;
                                    }
                            }
                        }
                    );
                    
                    $eventsManager->attach(
                        "dispatch:beforeExecuteRoute",
                        function ($event, \Root\Phalcon\Mvc\Dispatcher $dispatcher) {
                            $sModuleName = $dispatcher->getModuleName();
                            if (!empty($sModuleName)) {
                                $settings = $dispatcher->getDI()->get('config')->settings;
                                $application = $dispatcher->getApplicationName();
                                
                                if (!empty($settings['applications'][$application]['template']) && !empty($settings['applications'][$application]['template']['name'])) {
                                    $dispatcher->getDI()->get('view')->setTheme(
                                        $settings['applications'][$application]['template']['name']
                                    );
                                } else {
                                    $dispatcher->getDI()->get('view')->setTheme('default');
                                }
                                
                                if (!empty($settings['applications'][$application]['template']['defaultLayout'])) {
                                    $dispatcher->getDI()->get('view')->setLayout(
                                        $settings['applications'][$application]['template']['defaultLayout']
                                    );
                                }
                                
                                $dispatcher->getDI()->get('view')->initializeBuilder();
                            }
                        }
                    );
                    
                    $dispatcher = new \Root\Phalcon\Mvc\Dispatcher();
                }
                
                $dispatcher->setEventsManager($eventsManager);
                return $dispatcher;
            }
        );
    }
    
    private function loadRootAutoloader(): void
    {
        $loader = new \Phalcon\Autoload\Loader();
        $loader->register();
        $loader->setNamespaces(
            [
                'Modules' => ROOT_APP_PATH . '/Modules/',
                'Template' => ROOT_APP_PATH . '/Template/',
            ],
            true
        );
        $this->container->setShared('rootAutoloader', $loader);
    }
    
    private function loadModules(): void
    {
        $container = $this->getDI();
        $translate = $container->get('translate');
        $settings = $container->get('config')->settings;
        $applicationRouterGroups = [];
        
        // Prepare router for each application
        foreach ($settings['applications'] as $name => $values) {
            $router = new \Root\Phalcon\Mvc\Router\Group();
            $router->setApplication($name);
            
            if (isset($values['router'])) {
                if (isset($values['router']['prefix'])) {
                    $router->setPrefix($values['router']['prefix']);
                }
                if (isset($values['router']['hostname'])) {
                    $router->setHostname($values['router']['hostname']);
                }
                if (isset($values['router']['defaultRoute'])) {
                    $router->add('/', $values['router']['defaultRoute']->toArray());
                }
            }
            $applicationRouterGroups[$name] = $router;
        }
        
        $modules = $this->container->get('config')->modules->toArray();
        $phalconModuleConfigs = [];
        foreach ($modules as $moduleName => $moduleData) {
            if (is_numeric($moduleName)) {
                $moduleName = $moduleData;
                $moduleData = [];
            }
            
            $phalconModuleConfigs[$moduleName] = array_merge($moduleData, $this->getModulePaths($moduleName));
            
            // Load translations
            $i18nDir = $phalconModuleConfigs[$moduleName]['dir'] . '/I18n';
            if (is_dir($i18nDir)) {
                $files = scandir($i18nDir);
                foreach ($files as $file) {
                    if (is_file($i18nDir . '/' . $file)) {
                        $translate->addFile(str_replace('.php', '', $file), $i18nDir . '/' . $file);
                    }
                }
            }
            
            // Load module routes
            foreach ($settings['applications'] as $name => &$values) {
                $isApiType = $values['router']['type'] == 'api' ? true : false;
                $routeConfigFile = $phalconModuleConfigs[$moduleName] ['dir'] . '/Configs/' . ucfirst($name) . '/Routes.php';
                
                if (is_file($routeConfigFile)) {
                    $moduleRoutes = include $routeConfigFile;
                    if (!is_array($moduleRoutes) || empty($moduleRoutes)) {
                        continue;
                    }
                    foreach ($moduleRoutes as $moduleRouteName => $moduleRoute) {
                        $moduleRoute[1]['module'] = $moduleName;
                        
                        if ($isApiType) {
                            $moduleRoute[0] = '(?:/(?:v([a-zA-Z0-9\.]+)))?/' . $moduleRoute[0];
                            $moduleRoute[1]['controller'] = 'Api';
                            $moduleRoute[1]['apiVersion'] = 1;
                            $moduleRoute[1]['namespace'] = 'Root\Phalcon\Mvc';
                            $moduleRoute[1]['apiClass'] = trim($phalconModuleConfigs[$moduleName]['namespace'], '\\') . '\Api\%s\\' . $moduleRoute[1]['apiClass'];
                            if(isset($moduleRoute[2])){
                                $moduleRoute[2][] = 'OPTIONS';
                            }
                        } else {
                            $moduleRoute[1]['namespace'] = trim($phalconModuleConfigs[$moduleName]['namespace'], '\\');
                        }
                        $route = call_user_func_array([$applicationRouterGroups[$name], 'add'], $moduleRoute);
                        if (!is_numeric($moduleRouteName)) {
                            $route->setName($name . '.' . $moduleRouteName);
                        }
                    }
                }
            }
        }
        
        // Initialize Translations
        $translate->setDefaultLocale($this->container->get('config')->get('defaultLocale'));
        $translate->setLocale($this->container->get('config')->get('locale'));
        
        // Load module routes
        if (!$container instanceof \Phalcon\Di\FactoryDefault\Cli) {
            $router = $container->get('router');
            foreach ($settings['applications'] as $name => $values) {
                if (!empty($applicationRouterGroups[$name]->getRoutes())) {
                    $router->mount($applicationRouterGroups[$name]);
                }
            }
        }
        
        $this->registerModules($phalconModuleConfigs);
        $this->loadDefaultModules();
    }
    
    private function getModulePaths(string $moduleName): array
    {
        $namespace = '\Modules\\' . str_replace('/', '\\', $moduleName);
        
        return [
            'path' => ROOT_APP_PATH . '/Modules/' . $moduleName . '/Module.php',
            'dir' => ROOT_APP_PATH . '/Modules/' . $moduleName,
            'namespace' => $namespace,
            'className' => $namespace . '\\Module'
        ];
    }
    
    private function loadDefaultModules(): void
    {
        foreach ($this->getModules() as $name => $config) {
            if (!empty($config['loadedByDefault'])) {
                $this->loadModule($name);
            }
        }
    }
    
    private function loadModule(string $moduleName): void
    {
        $module = $this->getModule($moduleName);
        
        if (!class_exists($module['className'], false)) {
            $this->loadModuleDependencies($moduleName);
            $module = $this->getDI()->get($module['className']);
            $module->registerAutoloaders($this->getDI());
            $module->registerServices($this->getDI());
        }
    }
    
    private function loadModuleDependencies(string $moduleName): void
    {
        $moduleConfig = $this->getModule($moduleName);
        if (!empty($moduleConfig['dependencies'])) {
            foreach ($moduleConfig['dependencies'] as $moduleDependencyName) {
                $this->loadModule($moduleDependencyName);
            }
        }
    }
    
    private function loadFunctions(): void
    {
        require_once(realpath(dirname(__FILE__) . '/../..') . '/Core/functions.php');
    }
}
