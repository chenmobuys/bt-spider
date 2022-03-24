<?php

namespace BTSpider;

use BTSpider\Support\RoutingTable;
use BTSpider\Managers\ProcessManager;
use BTSpider\Managers\ServerManager;
use BTSpider\Managers\WorkerManager;
use BTSpider\Support\Config;
use BTSpider\Support\Facades\Facade;
use Illuminate\Container\Container;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class Application extends Container
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $commands = [
        \BTSpider\Commands\ServerCommand::class
    ];

    public function __construct($basePath)
    {
        if ($basePath) {
            $this->basePath = $basePath;
        }

        $this->bootstrap();
    }

    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function setBasePath($basePath): self
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());

        return $this;
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path Optionally, a path to append to the base path
     * @return string
     */
    public function basePath($path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the application configuration file.
     * 
     * @return string
     */
    public function configPath(): string
    {
        return $this->basePath('config.php');
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * @return int
     */
    public function terminate(): int
    {
        return $this['console']->run();
    }

    /**
     * @return void
     */
    protected function bootstrap(): void
    {
        Facade::setFacadeApplication($this);

        $this->registerCoreBindings();
    }

    /**
     * @return void
     */
    protected function registerCoreBindings(): void
    {
        if (!$this->runningInConsole()) {
            throw new RuntimeException('Must run in cli environment.');
        }

        static::setInstance($this);

        $this->instance('app', $this);

        $this->singleton('config', function ($app) {
            $config = new Config(require_once __DIR__ . '/config.php');
            if (is_file($app->configPath())) {
                $config->merge(require_once $app->configPath());
            }
            return $config;
        });

        $this->singleton('logger', function ($app) {
            $rotatingFileHandler = new RotatingFileHandler(
                $app['config']->get('log_file'),
                $app['config']->get('log_max'),
                $app['config']->get('log_level'),
            );
            return new Logger($app['config']->get('name'), [$rotatingFileHandler]);
        });

        $this->singleton('server', function ($app) {
            return new ServerManager($app);
        });

        $this->singleton('process', ProcessManager::class);

        $this->singleton('worker', WorkerManager::class);

        $this->singleton('console', ConsoleApplication::class);

        $this->singleton('style', function () {
            return new SymfonyStyle(new ArgvInput(), new ConsoleOutput());
        });
        
        $this->singleton('cursor', function () {
            return new Cursor(new ConsoleOutput());
        });
        
        $this['console']->addCommands(array_map(function ($command) {
            return new $command($this);
        }, $this->commands));

        // 初始化路由表
        $this['config']->set('tables', $this->getRoutingTables());
    }

    /**
     * @return Config
     */
    private function getRoutingTables()
    {
        $config = new Config();
        $ports = $this['config']->get('server.ports', []);
        array_unshift($ports, $this['config']->get('server.port'));
        foreach ($ports as $port) {
            $config->set($port, new RoutingTable);
        }
        return $config;
    }
}
