<?php

namespace BTSpider\Support\Contracts;

use Swoole\Process;

interface ProcessInterface
{
    public function getProcess(): Process;

    public function getProcessName(): string;

    public function getProcessGroup(): string;
}
