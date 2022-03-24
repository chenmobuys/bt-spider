<?php

namespace BTSpider\Managers;

use BTSpider\Support\Contracts\ProcessInterface;
use Swoole\Process;
use Swoole\Server;
use Swoole\Table;

class ProcessManager
{
    /**
     * @var \Swoole\Table
     */
    protected $infoTable;

    /**
     * @var \BTSpider\Contracts\ProcessInterface
     */
    protected $processList = [];

    public function __construct()
    {
        $this->infoTable = new Table(2048);
        $this->infoTable->column('pid', Table::TYPE_INT, 8);
        $this->infoTable->column('hash', Table::TYPE_STRING, 32);
        $this->infoTable->column('processName', Table::TYPE_STRING, 50);
        $this->infoTable->column('processGroup', Table::TYPE_STRING, 50);
        $this->infoTable->column('memoryUsage', Table::TYPE_INT, 8);
        $this->infoTable->column('memoryPeakUsage', Table::TYPE_INT, 16);
        $this->infoTable->column('startTime', Table::TYPE_FLOAT, 8);
        $this->infoTable->create();
    }

    public function getInfoTable(): Table
    {
        return $this->infoTable;
    }

    public function getProcessByPid(int $pid)
    {
        $info = $this->infoTable->get($pid);
        if ($info) {
            $hash = $info['hash'];
            if (isset($this->processList[$hash])) {
                return $this->processList[$hash];
            }
        }
        return null;
    }

    public function getProcessByName(string $name): array
    {
        $ret = [];
        foreach ($this->processList as $process) {
            if ($process->getProcessName() === $name) {
                $ret[] = $process;
            }
        }

        return $ret;
    }

    public function getProcessByGroup(string $group): array
    {
        $ret = [];
        foreach ($this->processList as $process) {
            if ($process->getProcessGroup() === $group) {
                $ret[] = $process;
            }
        }

        return $ret;
    }

    public function kill($pidOrGroupName, $sig = SIGTERM): array
    {
        $list = [];
        if (is_numeric($pidOrGroupName)) {
            $info = $this->infoTable->get($pidOrGroupName);
            if ($info) {
                $list[$pidOrGroupName] = $pidOrGroupName;
            }
        } else {
            foreach ($this->infoTable as $key => $value) {
                if ($value['group'] == $pidOrGroupName) {
                    $list[$key] = $value;
                }
            }
        }
        $this->clearPid($list);
        foreach ($list as $pid => $value) {
            Process::kill($pid, $sig);
        }
        return $list;
    }

    public function info($pidOrGroupName = null)
    {
        $list = [];
        if ($pidOrGroupName == null) {
            foreach ($this->infoTable as $pid => $value) {
                $list[$pid] = $value;
            }
        } else if (is_numeric($pidOrGroupName)) {
            $info = $this->infoTable->get($pidOrGroupName);
            if ($info) {
                $list[$pidOrGroupName] = $info;
            }
        } else {
            foreach ($this->infoTable as $key => $value) {
                if ($value['group'] == $pidOrGroupName) {
                    $list[$key] = $value;
                }
            }
        }

        $sort = array_column($list, 'group');
        array_multisort($sort, SORT_DESC, $list);
        foreach ($list as $key => $value) {
            unset($list[$key]);
            $list[$value['pid']] = $value;
        }
        return $this->clearPid($list);
    }

    public function addProcess(ProcessInterface $process, bool $autoRegister = true)
    {
        $hash = spl_object_hash($process->getProcess());
        $this->autoRegister[$hash] = $autoRegister;
        $this->processList[$hash] = $process;
        return $this;
    }

    public function attachToServer(Server $server)
    {
        foreach ($this->processList as $hash => $process) {
            if ($this->autoRegister[$hash] === true) {
                $server->addProcess($process->getProcess());
            }
        }
    }

    public function pidExist(int $pid)
    {
        return Process::kill($pid, 0);
    }

    protected function clearPid(array $list)
    {
        foreach ($list as $pid => $value) {
            if (!$this->pidExist($pid)) {
                $this->infoTable->del($pid);
                unset($list[$pid]);
            }
        }
        return $list;
    }
}
