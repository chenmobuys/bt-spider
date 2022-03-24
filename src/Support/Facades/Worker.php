<?php

namespace BTSpider\Support\Facades;

/**
 * @method static void task(\BTSpider\Contracts\TaskInterface $task)
 * @see \BTSpider\Managers\WorkerManager
 */
class Worker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     * 
     */
    protected static function getFacadeAccessor()
    {
        return 'worker';
    }
}
