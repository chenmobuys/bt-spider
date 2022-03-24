<?php

namespace BTSpider\Support\Facades;

/**
 * @method static async(\BTSpider\Contracts\TaskInterface $task)
 * @see \BTSpider\Managers\ProcessManager
 */
class Process extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     */
    protected static function getFacadeAccessor()
    {
        return 'process';
    }
}
