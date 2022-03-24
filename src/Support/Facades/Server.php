<?php

namespace BTSpider\Support\Facades;

/**
 * @see \BTSpider\Managers\ServerManager
 */
class Server extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     */
    protected static function getFacadeAccessor()
    {
        return 'server';
    }
}
