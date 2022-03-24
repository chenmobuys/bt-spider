<?php

namespace BTSpider\Task;

use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Service\DHTService;
use BTSpider\Support\Facades\Config;

class GetPeersTask implements TaskInterface
{
    /** @var string */
    private $infoHash;

    public function __construct(string $infoHash)
    {
        $this->infoHash = $infoHash;
    }

    public function run(): void
    {
        /** @var \BTSpider\Support\RoutingTable[] $tables */
        $tables = Config::get('tables', []);
        
        foreach ($tables as $table) {
            $nodes = $table->getTopNodesByNodeId($table->getNodeId());
            foreach ($nodes as $node) {
                DHTService::getPeers($node['ip'], $node['port'], $table->getNodeId(), $this->infoHash);
            }
        }
    }
}
