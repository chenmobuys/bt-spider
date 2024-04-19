<?php

namespace BTSpider\Managers;

use BTSpider\Application;
use BTSpider\Support\Facades\Config;
use Swoole\Server;

class ServerManager
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var \Swoole\Server
     */
    protected static $server;

    /**
     * @var array
     */
    protected $events = [
        'start', 'workerStart', 'workerStop', 'workerExit', 'workerError',
        'connect', 'receive', 'packet', 'close', 'task', 'finish', 'pipeMessage',
        'beforeShutdown', 'shutdown', 'managerStart', 'managerStop', 'beforeReload', 'afterReload',
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getServer(): Server
    {
        if (is_null(self::$server)) {
            $this->bootstrap();
        }

        return self::$server;
    }

    protected function bootstrap(): void
    {
        $this->initDirectory();

        $this->createServer();

        $this->registerServerListeners();

        $this->registerSwooleEvents();

        $this->registerUserProcess();
    }

    protected function createServer(): void
    {
        $host = Config::get('server.host');
        $port = Config::get('server.port');
        $settings = Config::get('server.settings', []);
        self::$server = new Server($host, $port, SWOOLE_BASE, SWOOLE_UDP);
        self::$server->set($settings);
    }

    protected function registerServerListeners(): void
    {
        $host = Config::get('server.host');
        $ports = Config::get('server.ports', []);
        $settings = Config::get('server.settings', []);
        foreach ($ports as $port) {
            $subServer = self::$server->listen($host, $port, SWOOLE_UDP);
        }
    }

    protected function registerSwooleEvents(): void
    {
        foreach ($this->events as $eventName) {
            if (method_exists(ServerEvent::class, $eventName)) {
                self::$server->on($eventName, function (...$args) use ($eventName) {
                    call_user_func([ServerEvent::class, $eventName], ...$args);
                });
            }
        }
    }

    protected function registerUserProcess(): void
    {
        $this->app['worker']->attachToServer(self::$server);
    }

    protected function initDirectory()
    {
        if (!is_dir($dataFileDir = dirname($this->app['config']->get('data_file')))) {
            mkdir($dataFileDir, 0777, true);
        }
        if (!is_dir($statsFileDir = dirname($this->app['config']->get('stats_file')))) {
            mkdir($statsFileDir, 0777, true);
        }
        if (!is_dir($logFileDir = dirname($this->app['config']->get('log_file')))) {
            mkdir($logFileDir, 0777, true);
        }
        if (!is_dir($serverLogFileDir = dirname($this->app['config']->get('server.settings.log_file')))) {
            mkdir($serverLogFileDir, 0777, true);
        }
        if (!is_dir($serverStatsFileDir = dirname($this->app['config']->get('server.settings.stats_file')))) {
            mkdir($serverStatsFileDir, 0777, true);
        }
        if (!is_dir($serverPidFileDir = dirname($this->app['config']->get('server.settings.pid_file')))) {
            mkdir($serverPidFileDir, 0777, true);
        }
    }
}
