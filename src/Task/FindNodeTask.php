<?php

namespace BTSpider\Task;

use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Service\DHTService;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Utils;

class FindNodeTask implements TaskInterface
{
    /** @var string */
    private $ip;

    /** @var int */
    private $port;

    /** @var string */
    private $target;

    public function __construct(string $ip, int $port, string $target = null)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->target = $target;
    }

    public function run(): void
    {
        /** @var \BTSpider\Support\RoutingTable[] $tables */
        $tables = Config::get('tables', []);

        if (is_null($this->target)) {
            $target = Utils::randomBytes();
        }
        foreach ($tables as $table) {
            $target = Utils::neighborBytes($this->target, $table->getNodeId());
            DHTService::findNode($this->ip, $this->port, $table->getNodeId(), $target);
        }
    }
}
