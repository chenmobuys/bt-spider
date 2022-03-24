<?php


namespace BTSpider\Managers;

use BTSpider\Support\Contracts\ProcessInterface;
use Exception;
use SplQueue;
use BTSpider\Support\Contracts\TaskInterface;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Facades\Process as ProcessManager;
use Swoole\Atomic\Long;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Scheduler;
use Swoole\Event;
use Swoole\Process;
use Swoole\Table;
use Swoole\Timer;

class WorkerProcess implements ProcessInterface
{
    /**
     * @var Process
     */
    protected $process;

    /**
     * @var string
     */
    protected $processName;

    /**
     * @var string
     */
    protected $processGroup;

    /**
     * @var int
     */
    protected $workerIndex;

    /**
     * @var \Swoole\Table
     */
    protected $infoTable;

    /**
     * @var SplQueue
     */
    protected $taskQueue;

    /** 
     * @var \Swoole\Atomic\Long
     */
    protected $taskIdAtomic;

    /** 
     * @var \Swoole\Table
     */
    protected $taskTable;

    public function __construct(string $processName, string $processGroup, int $workerIndex, Table $infoTable, Long $taskIdAtomic, Table $taskTable)
    {
        $this->processName = $processName;

        $this->processGroup = $processGroup;

        $this->workerIndex = $workerIndex;

        $this->infoTable = $infoTable;

        $this->taskIdAtomic = $taskIdAtomic;

        $this->taskTable = $taskTable;

        $this->taskQueue = new SplQueue;

        $this->process = new Process([$this, '__start'], false, Process::PIPE_WORKER, true);

        ProcessManager::addProcess($this, false);
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getProcessName(): string
    {
        return $this->processName;
    }

    public function getProcessGroup(): string
    {
        return $this->processGroup;
    }

    public function pushable()
    {
        return $this->infoTable->get($this->workerIndex, 'waiting') < Config::get('worker.task_queue_max');
    }

    public function shiftable()
    {
        return $this->infoTable->get($this->workerIndex, 'running') < Config::get('worker.running_max');
    }

    public function getProtocol()
    {
        return [
            'open_length_check'     => true,
            'package_max_length'    => 1024 * 1024 * 2,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 4,
        ];
    }

    protected function run(Process $p): void
    {
        $free_wait_time = Config::get('worker.free_wait_time', 0.001);
        while (true) {
            try {
                if ($this->taskQueue->count() == 0) {
                    Coroutine::sleep($free_wait_time);
                    continue;
                }

                if (!$this->shiftable()) {
                    Coroutine::sleep($free_wait_time);
                    continue;
                }

                $task = $this->taskQueue->shift();

                if (!$task) {
                    Coroutine::sleep($free_wait_time);
                    continue;
                }

                $taskId =  $this->taskIdAtomic->add(1);
                Coroutine::create(function ($task, $taskId) {
                    try {
                        $this->infoTable->decr($this->workerIndex, 'waiting', 1);
                        $this->infoTable->incr($this->workerIndex, 'running', 1);
                        $task->run();
                        $this->infoTable->incr($this->workerIndex, 'success', 1);
                    } catch (\Exception $e) {
                        $this->infoTable->incr($this->workerIndex, 'failure', 1);
                        $this->onException($e);
                    } finally {
                        $this->infoTable->decr($this->workerIndex, 'running', 1);
                    }
                }, $task, $taskId);
            } catch (Exception $e) {
                $this->onException($e);
            }
        }
    }

    public function __start(Process $p): void
    {
        $pTable = ProcessManager::getInfoTable();
        $pTable->set($p->pid, [
            'pid' => $p->pid,
            'hash' => spl_object_hash($p),
            'processName' => $this->processName,
            'processGroup' => $this->processGroup,
            'startTime' => time(),
        ]);

        Timer::tick(1 * 1000, function () use ($pTable, $p) {
            $pTable->set($p->pid, [
                'memoryUsage' => memory_get_usage(),
                'memoryPeakUsage' => memory_get_peak_usage(true)
            ]);
        });

        Process::signal(SIGTERM, function () use ($p) {
            try {
                $this->onSigTerm();
            } catch (Exception $e) {
                $this->onException($e);
            } finally {
                Timer::clearAll();
                Process::signal(SIGTERM, null);
                Event::exit();
            }
        });

        $maxExitWaitTime = Config::get('worker.max_exit_wait_time', 0.01);
        register_shutdown_function(function () use ($pTable, $p, $maxExitWaitTime) {
            if ($pTable) {
                $pTable->del($p->pid);
            }
            $schedule = new Scheduler();
            $schedule->add(function () use ($maxExitWaitTime) {
                $channel = new Channel(1);
                Coroutine::create(function () use ($channel) {
                    try {
                        $this->onShutDown();
                    } catch (Exception $e) {
                        $this->onException($e);
                    }
                    $channel->push(1);
                });
                $channel->pop($maxExitWaitTime);
                Timer::clearAll();
                Event::exit();
            });
            $schedule->start();
            Timer::clearAll();
            Event::exit();
        });

        $this->infoTable->set($this->workerIndex, [
            'pid' => $p->pid,
            'waiting' => 0,
            'running' => 0,
            'success' => 0,
            'failure' => 0,
            'exceed' => 0,
            'startTime' => time()
        ]);

        $this->taskTable->set($this->workerIndex, [
            'pid' => $p->pid,
            \BTSpider\Task\BootstrapTask::class => 0,
            \BTSpider\Task\FetchMetadataTask::class => 0,
            \BTSpider\Task\FindNodeTask::class => 0,
            \BTSpider\Task\GetPeersTask::class => 0,
            \BTSpider\Task\ResponseTask::class => 0,
            'startTime' => time()
        ]);

        try {
            Coroutine::create(function () use ($p) {
                $this->run($p);
            });
        } catch (Exception $e) {
            $this->onException($e);
        }

        $socket = $p->exportSocket();
        $socket->setProtocol($this->getProtocol());

        while (true) {
            $recv = $socket->recv();
            $data = unserialize($recv);
            if ($data && $data instanceof TaskInterface) {
                Coroutine::create(function () use ($data) {
                    try {
                        if ($this->pushable()) {
                            $this->taskQueue->push($data);
                            $this->infoTable->incr($this->workerIndex, 'waiting', 1);
                            $this->taskTable->incr($this->workerIndex, get_class($data), 1);
                        } else {
                            $this->infoTable->incr($this->workerIndex, 'exceed', 1);
                        }
                    } catch (Exception $e) {
                        $this->onException($e);
                    }
                });
            }
        }
    }

    protected function onException(Exception $e): void
    {
    }

    protected function onSigTerm(): void
    {
    }

    protected function onShutDown(): void
    {
    }
}
