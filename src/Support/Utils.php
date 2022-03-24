<?php

namespace BTSpider\Support;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\BufferedOutput;

class Utils
{
    public static function logo()
    {
        return <<<LOGO
        ____ ___________       _     __         
        / __ )_  __/ ___/____  (_)___/ /__  _____
       / __  |/ /  \__ \/ __ \/ / __  / _ \/ ___/
      / /_/ // /  ___/ / /_/ / / /_/ /  __/ /    
     /_____//_/  /____/ .___/_/\__,_/\___/_/     
                     /_/                         
LOGO;
    }

    /**
     * @return bool
     */
    public static function isCygwinOS(): bool
    {
        return PHP_OS === 'CYGWIN';
    }

    /**
     * 获取随机节点ID
     * 
     * @param int $length
     * @param bool $srand
     * @return string
     */
    public static function randomBytes(int $length = 20, $srand = false): string
    {
        if ($srand) {
            mt_srand();
        }
        $str = '';
        for ($i = 0; $i < $length; $i++)
            $str .= chr(mt_rand(0, 255));
        return $str;
    }

    /**
     * 获取邻居节点ID
     * 
     * @param string $target
     * @param string $id
     * @return string
     */
    public static function neighborBytes(string $target, string $id): string
    {
        return substr($target, 0, 10) . substr($id, 10, 10);
    }

    /**
     * 节点二进制转为数组
     * 
     * @param string $nodesBinary
     * @return array
     */
    public static function getNodesByBytes(string $nodesBinary): array
    {
        $nodes = [];

        if (!is_string($nodesBinary) || strlen($nodesBinary) < 26 || strlen($nodesBinary) % 26 > 0) {
            return $nodes;
        }
        // 每次截取26字节进行解码
        foreach (str_split($nodesBinary, 26) as $s) {
            // 将截取到的字节进行字节序解码
            $r = unpack('a20id/Nip/nport', $s);
            $r['ip'] = long2ip($r['ip']);
            $nodes[] = $r;
        }
        return $nodes;
    }

    /**
     * 节点数组转为二进制
     * 
     * @param array $nodes
     * @return string
     */
    public static function getNodesBinaryByNodes(array $nodes): string
    {
        $nodesBinary = '';
        foreach ($nodes as $node) {
            $nodesBinary .= pack('a20Nn', $node['id'], ip2long($node['ip']), $node['port']);
        }
        return $nodesBinary;
    }

    /**
     * 生成状态面板
     * 
     * @param array $data
     * @return string
     */
    public static function getStatusTable(array $data)
    {
        $style = new TableCellStyle(['cellFormat' => '<info>%s</info>']);
        $headers = [new TableCell('Monitor Board', ['colspan' => 7, 'style' => new TableCellStyle(['align' => 'center', 'cellFormat' => '<info>%s</info>'])])];
        $rows = [];

        $swooleManagerHeaders = array_map(function ($item) use ($style) {
            return new TableCell($item, ['style' => $style]);
        }, ['MasterPid', 'ManagerPid', 'WrokerId', 'WrokerPid', 'RequestCount', 'StartTime']);
        array_unshift($swooleManagerHeaders,  new TableCell('Swoole', ['rowspan' => 2, 'style' => $style]));
        array_push($rows, $swooleManagerHeaders);
        $swooleManagerRows = [[$data['master_pid'], $data['manager_pid'], $data['worker_id'], $data['worker_pid'], $data['request_count'], date('Y-m-d H:i:s', $data['start_time'])]];
        array_push($rows, ...$swooleManagerRows);
        array_push($rows, new TableSeparator());

        $processManagerHeaders = array_map(function ($item) use ($style) {
            return new TableCell($item, ['style' => $style]);
        }, ['Pid', 'ProcessName', 'ProcessGroup', 'MemoryUsage', 'MemoryPeakUsage', 'StartTime']);
        array_unshift($processManagerHeaders, new TableCell('Process', ['rowspan' => count($data['process_tables'] ?? []) + 1, 'style' => $style]));
        array_push($rows, $processManagerHeaders);
        $processManagerRows = array_map(function ($item) {
            unset($item['hash']);
            $item['memoryUsage'] = bcdiv(bcdiv($item['memoryUsage'], 1024, 4), 1024, 2) . 'MB';
            $item['memoryPeakUsage'] = bcdiv(bcdiv($item['memoryPeakUsage'], 1024, 4), 1024, 2) . 'MB';
            $item['startTime'] = date('Y-m-d H:i:s', $item['startTime']);
            return array_values($item);
        }, $data['process_tables'] ?? []);
        array_push($rows, ...$processManagerRows);
        array_push($rows, new TableSeparator());

        $workerManagerHeaders =  array_map(function ($item) use ($style) {
            return new TableCell($item, ['style' => $style]);
        }, ['Pid', 'Waiting', 'Running', 'Success', 'Failure', 'Exceed']);
        array_unshift($workerManagerHeaders, new TableCell('Worker', ['rowspan' => count($data['worker_tables'] ?? []) + 1, 'style' => $style]));
        array_push($rows, $workerManagerHeaders);
        $workerManagerRows = array_map(function ($item) {
            unset($item['startTime']);
            return array_values($item);
        }, $data['worker_tables'] ?? []);
        array_push($rows, ...$workerManagerRows);
        array_push($rows, new TableSeparator());

        $taskManagerHeaders =  array_map(function ($item) use ($style) {
            return new TableCell($item, ['style' => $style]);
        }, ['Pid', 'BootstrapTask', 'FetchMetadataTask', 'FindNodeTask', 'GetPeersTask', 'ResponseTask']);
        array_unshift($taskManagerHeaders, new TableCell('Task', ['rowspan' => count($data['task_tables'] ?? []) + 1, 'style' => $style]));
        array_push($rows, $taskManagerHeaders);
        $taskManagerRows = array_map(function ($item) {
            unset($item['startTime']);
            return array_values($item);
        }, $data['task_tables'] ?? []);
        array_push($rows, ...$taskManagerRows);

        $bufferedOutput = new BufferedOutput();
        $table = new Table($bufferedOutput);
        $table->setHeaders($headers)->setRows($rows)->setStyle('symfony-style-guide')->render();
        return $bufferedOutput->fetch();
    }
}
