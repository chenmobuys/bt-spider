<?php

namespace BTSpider\Task;

use BTSpider\Service\DHTService;
use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Utils;

class BootstrapTask implements TaskInterface
{
    public function run(): void
    {
        /** @var \BTSpider\Support\RoutingTable[] $tables */
        $tables = Config::get('tables', []);
        $bootstrapNodes = Config::get('bootstrap_nodes', []);
        $target = Utils::randomBytes();
        foreach ($tables as $table) {
            foreach ($bootstrapNodes as $bootstrapNode) {
                list($ip, $port) = $bootstrapNode;
                $ip = gethostbyname($ip);
                DHTService::findNode($ip, $port, $table->getNodeId(), Utils::neighborBytes($target, $table->getNodeId()));
            }
        }
    }
}
