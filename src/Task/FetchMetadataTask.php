<?php

namespace BTSpider\Task;

use BTSpider\Service\MDService;
use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Facades\Logger;
use BTSpider\Support\Facades\Worker;

class FetchMetadataTask implements TaskInterface
{
    /** @var string */
    private $ip;

    /** @var int */
    private $port;

    /** @var string */
    private $infoHash;

    /** @var int */
    private $tries;

    public function __construct(string $ip, int $port, string $infoHash, int $tries = 3)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->infoHash = $infoHash;
        $this->tries = $tries;
    }

    public function run(): void
    {
        try {
            $metadata = MDService::getMetadata($this->ip, $this->port, $this->infoHash);
            if (is_array($metadata) && ($metadata['name'] ?? null)) {
                unset($metadata['piece length'], $metadata['pieces']);
                Logger::info($metadata['name']);
                file_put_contents(Config::get('data_file') . '.' . date('Ymd'), json_encode($metadata) . PHP_EOL, FILE_APPEND);
            }
        } catch (\RuntimeException $e) {
            // 失败就重新投递
            if ($this->tries > 0) {
                $this->tries--;
                Worker::task(new FetchMetadataTask($this->ip, $this->port, $this->infoHash, $this->tries));
            }
        }
    }
}
