<?php

namespace BTSpider\Managers;

use BTSpider\Support\RoutingTable;
use BTSpider\Service\DHTService;
use BTSpider\Support\Bencode;
use BTSpider\Support\Facades\Config;
use BTSpider\Support\Facades\Process;
use BTSpider\Support\Facades\Worker;
use BTSpider\Support\Utils;
use BTSpider\Task\BootstrapTask;
use BTSpider\Task\FetchMetadataTask;
use BTSpider\Task\FindNodeTask;
use BTSpider\Task\GetPeersTask;
use BTSpider\Task\ResponseTask;
use Swoole\Server;
use Swoole\Timer;

class ServerEvent
{
    public static function start(Server $server)
    {
        if ($statsFile = Config::get('stats_file')) {
            Timer::tick(1000, function () use ($statsFile, $server) {
                $statusData = static::getStatusData($server);
                file_put_contents($statsFile, Utils::getStatusTable($statusData));
            });
        }
    }

    public static function workerStart(Server $server, $worker_id)
    {
        Timer::tick(Config::get('find_node_interval', 3000), function () {
            Worker::task(new BootstrapTask);
        });
    }

    public static function workerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        swoole_error_log(SWOOLE_LOG_ERROR, 'Worker Error');
    }

    public static function workerExit(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        swoole_error_log(SWOOLE_LOG_ERROR, 'Worker Exit');
    }

    public static function packet(Server $server, string $data, array $clientInfo)
    {
        $data = Bencode::decode($data);
        if (!isset($data['y'])) {
            return;
        }
        if (!isset($data[$data['y']])) {
            return;
        }

        // 客户端信息
        $clientAddress = $clientInfo['address'];
        $clientPort = $clientInfo['port'];
        $serverPort = $clientInfo['server_port'];

        // 当前端口对应路由表
        /** @var RoutingTable $table */
        $table = Config::get('tables')->get($serverPort);
        $nodeId = $table->getNodeId();

        switch ($data['y']) {
            case 'q':   // 请求
                $q = $data[$data['y']];
                if (!isset($data['a'])) {
                    break;
                }
                switch ($q) {
                    case DHTService::PING:
                        $targetNodeId = Utils::neighborBytes($data['a']['id'] ?: Utils::randomBytes(), $nodeId);
                        Worker::task(new ResponseTask('pingReceive', [$data['t'], $clientAddress, $clientPort, $targetNodeId]));
                        Worker::task(new FindNodeTask($clientAddress, $clientPort, $data['a']['id']));
                        break;
                    case  DHTService::FIND_NODE:
                        $targetNodeId = Utils::neighborBytes($data['a']['id'] ?: Utils::randomBytes(), $nodeId);
                        Worker::task(new ResponseTask('findNodeReceive', [$data['t'], $clientAddress, $clientPort, $targetNodeId, '']));
                        Worker::task(new FindNodeTask($clientAddress, $clientPort, $data['a']['id']));
                        break;
                    case DHTService::GET_PEERS:
                        $token = substr($data['a']['info_hash'], 0, 2);
                        $targetNodeId = Utils::neighborBytes($data['a']['id'] ?: Utils::randomBytes(), $nodeId);
                        $table->addNode(['id' => $data['a']['id'], 'ip' => $clientAddress, 'port' => $clientPort]);
                        Worker::task(new ResponseTask('getPeersReceive', [$data['t'], $clientAddress, $clientPort, $targetNodeId, '', $token]));
                        Worker::task(new FindNodeTask($clientAddress, $clientPort, $data['a']['id']));
                        Worker::task(new GetPeersTask($data['a']['info_hash']));
                        break;
                    case DHTService::ANNOUNCE_PEER:
                        Worker::task(new ResponseTask('announcePeerReceive', [$data['t'], $clientAddress, $clientPort, $nodeId]));
                        if ($data['a']['token'] = substr($data['a']['info_hash'], 0, 2)) {
                            Worker::task(new FetchMetadataTask($clientAddress, $data['a']['port'], $data['a']['info_hash']));
                        }
                        break;
                }
                break;
            case 'r':   // 回复
                if (isset($data['r']['nodes'])) {
                    $nodes = Utils::getNodesByBytes($data['r']['nodes']);
                    foreach ($nodes as $node) {
                        $table->addNode(['id' => $node['id'], 'ip' => $node['ip'], 'port' => $node['port']]);
                        Worker::task(new FindNodeTask($node['ip'], $node['port'], $node['id']));
                    }
                }
                break;
        }
    }

    public static function beforeReload()
    {
        swoole_error_log(SWOOLE_LOG_INFO, '重启中...');
    }

    public static function afterReload()
    {
        swoole_error_log(SWOOLE_LOG_INFO, '重启成功');
    }

    private static function getStatusData(Server $server)
    {
        return [
            'master_pid' => $server->master_pid,
            'manager_pid' => $server->manager_pid,
            'worker_id' => $server->worker_id,
            'worker_pid' => $server->worker_pid,
            'start_time' => $server->stats()['start_time'],
            'request_count' => $server->stats()['request_count'],
            'process_tables' => array_values(iterator_to_array(Process::getInfoTable())),
            'worker_tables' => array_values(iterator_to_array(Worker::getInfoTable())),
            'task_tables' => array_values(iterator_to_array(Worker::getTaskTable())),
        ];
    }
}
