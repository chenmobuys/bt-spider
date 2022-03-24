<?php

namespace BTSpider\Task;

use BTSpider\Service\DHTService;
use BTSpider\Support\Contracts\TaskInterface;

class ResponseTask implements TaskInterface
{
    /**
     * @var string
     */
    private $method;

    /** 
     * @var array
     */
    private $args;

    public function __construct(string $method, array $args = [])
    {
        $this->method = $method;
        $this->args = $args;
    }

    public function run(): void
    {
        call_user_func([DHTService::class, $this->method], ...$this->args);
    }
}
