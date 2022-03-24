<?php

namespace BTSpider\Support\Facades;

/**
 * @method static void setBasePath(string $basePath)
 * @method static string basePath(string $path = '')
 * @method static string configPath()
 * @method static bool runningInConsole()
 * @see \BTSpider\Application
 */
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     */
    protected static function getFacadeAccessor()
    {
        return 'app';
    }
}
