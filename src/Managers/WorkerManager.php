<?php

namespace BTSpider\Managers;

use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Utils;
use Carbon\Carbon;
use Swoole\Atomic\Long;
use Swoole\Server;
use Swoole\Table;
use RuntimeException;
use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Timer;

class WorkerManager
{
    /**
     * 如果超出此数值，说明系统已经处理不过来，可以适当调整 server.worker.running_max 数值
     */
    private const COROUTINE_MAX = 1000;

    /**
     *
     * @var \Swoole\Table
     */
    protected $infoTable;

    /**
     *
     * @var \Swoole\Table
     */
    protected $taskTable;

    /**
     *
     * @var \BTSpider\Managers\WorkerProcess[]
     */
    protected $workers = [];

    /** 
     * @var \Swoole\Atomic\Long
     */
    protected $taskIdAtomic;

    /**
     * @var bool
     */
    protected $attachServer = false;

    /**
     * @var array
     */
    protected $exportedSocketWorkerIndexes = [];

    /**
     *
     * @var \Swoole\ConnectionPool
     */
    protected $connectionPool;

    public function __construct()
    {
        $this->taskIdAtomic = new Long(0);
        $this->infoTable = new Table(512);
        $this->infoTable->column('pid', Table::TYPE_INT, 8);
        $this->infoTable->column('waiting', Table::TYPE_INT, 8);
        $this->infoTable->column('running', Table::TYPE_INT, 8);
        $this->infoTable->column('success', Table::TYPE_INT, 8);
        $this->infoTable->column('failure', Table::TYPE_INT, 8);
        $this->infoTable->column('exceed', Table::TYPE_INT, 8);
        $this->infoTable->column('startTime', Table::TYPE_INT, 8);
        $this->infoTable->create();

        $this->taskTable = new Table(256);
        $this->taskTable->column('pid', Table::TYPE_INT, 8);
        $this->taskTable->column(\BTSpider\Task\BootstrapTask::class, Table::TYPE_INT, 8);
        $this->taskTable->column(\BTSpider\Task\FetchMetadataTask::class, Table::TYPE_INT, 8);
        $this->taskTable->column(\BTSpider\Task\FindNodeTask::class, Table::TYPE_INT, 8);
        $this->taskTable->column(\BTSpider\Task\GetPeersTask::class, Table::TYPE_INT, 8);
        $this->taskTable->column(\BTSpider\Task\ResponseTask::class, Table::TYPE_INT, 8);
        $this->taskTable->column('startTime', Table::TYPE_INT, 8);
        $this->taskTable->create();

        $this->connectionPool = new ConnectionPool(function () {
            return call_user_func([$this, 'getWorkerSocket']);
        }, Config::get('worker.worker_num'));
    }

    /**
     * @param \BTSpider\Support\Contracts\TaskInterface $task
     * @return void
     */
    public function task(TaskInterface $task): void
    {
        if (Coroutine::stats()['coroutine_num'] > self::COROUTINE_MAX) {
            swoole_error_log(SWOOLE_LOG_WARNING, 'Coroutine exceeds maximum ' . self::COROUTINE_MAX);
        } else if (count($this->workers) > 0) {
            Coroutine::create(function () use ($task) {
                $socket = $this->connectionPool->get();
                $socket->send(serialize($task));
                $this->connectionPool->put($socket);
            });
        }
    }

    /**
     * @return \Swoole\Table
     */
    public function getInfoTable()
    {
        return $this->infoTable;
    }

    /**
     * @return \Swoole\Table
     */
    public function getTaskTable()
    {
        return $this->taskTable;
    }

    /**
     * @param  $server
     * @return bool
     * @throws \RuntimeException
     */
    public function attachToServer(Server $server): bool
    {
        if (!$this->attachServer) {
            $list = $this->__initProcess();
            /** @var WorkerProcess $item */
            foreach ($list as $item) {
                $server->addProcess($item->getProcess());
            }
            $this->attachServer = true;
            return true;
        } else {
            throw new RuntimeException("Task instance has been attach to server");
        }
    }

    /**
     * @return array
     */
    private function __initProcess(): array
    {
        $serverName = Config::get('name');
        $workerNum = Config::get('worker.worker_num');
        for ($workerIndex = 0; $workerIndex < $workerNum; $workerIndex++) {
            $processName =  $serverName . '.Worker.' . $workerIndex;
            $processGroup = $serverName . '.Worker';
            $this->workers[$workerIndex] = new WorkerProcess(
                $processName,
                $processGroup,
                $workerIndex,
                $this->infoTable,
                $this->taskIdAtomic,
                $this->taskTable,
            );
        }
        return $this->workers;
    }

    public function getWorkerSocket()
    {
        $remainIndexes = array_diff(array_keys($this->workers), $this->exportedSocketWorkerIndexes);
        $randomIndex = array_rand($remainIndexes);
        $this->exportedSocketWorkerIndexes[] = $randomIndex;
        $socket = $this->workers[$randomIndex]->getProcess()->exportSocket();
        $socket->setProtocol($this->workers[$randomIndex]->getProtocol());
        return $socket;
    }
}
