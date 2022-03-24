<?php

namespace BTSpider\Service;

use BTSpider\Support\Bencode;
use BTSpider\Support\Facades\Server;

/**
 * @see http://www.bittorrent.org/beps/bep_0005.html
 */
class DHTService
{
    private const T = 'bt';
    public const PING = 'ping';
    public const FIND_NODE = 'find_node';
    public const GET_PEERS = 'get_peers';
    public const ANNOUNCE_PEER = 'announce_peer';

    public static function ping($ip, $port, $nodeId)
    {
        self::sendto($ip, $port, ['t' => self::T, 'y' => 'q', 'q' => self::PING, 'a' => ['id' => $nodeId]]);
    }

    public static function pingReceive($t, $ip, $port, $nodeId)
    {
        self::sendto($ip, $port, ['t' => $t, 'y' => 'r', 'r' => ['id' => $nodeId]]);
    }

    public static function findNode($ip, $port, $nodeId, $targetNodeId)
    {
        self::sendto($ip, $port, ['t' => self::T, 'y' => 'q', 'q' => self::FIND_NODE, 'a' => ['id' => $nodeId, 'target' => $targetNodeId]]);
    }

    public static function findNodeReceive($t, $ip, $port, $nodeId, $nodes)
    {
        self::sendto($ip, $port, ['t' => $t, 'y' => 'r', 'r' => ['id' => $nodeId, 'nodes' => $nodes]]);
    }

    public static function getPeers($ip, $port, $nodeId, $infoHash)
    {
        self::sendto($ip, $port, ['t' => self::T, 'y' => 'q', 'q' => self::GET_PEERS, 'a' => ['id' => $nodeId, 'info_hash' => $infoHash]]);
    }

    public static function getPeersReceive($t, $ip, $port, $nodeId, $nodes, $token)
    {
        self::sendto($ip, $port, ['t' => $t, 'y' => 'r', 'r' => ['id' => $nodeId, 'nodes' => $nodes, 'token' => $token]]);
    }

    public static function announcePeer($ip, $port, $nodeId, $infoHash, $infoPort, $token)
    {
        self::sendto($ip, $port, ['t' => self::T, 'y' => 'q', 'q' => self::ANNOUNCE_PEER, 'a' => ['id' => $nodeId, 'info_hash' => $infoHash, 'port' => $infoPort, 'token' => $token]]);
    }

    public static function announcePeerReceive($t, $ip, $port, $nodeId)
    {
        self::sendto($ip, $port, ['t' => $t, 'y' => 'r', 'r' => ['id' => $nodeId]]);
    }

    public static function sendto($ip, $port, $param)
    {
        $data = Bencode::encode($param);
        if (PHP_OS === 'CYGWIN') {
            // 在 CYGWIN 环境下，使用 PHP 自带 sockets 扩展
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_sendto($socket, $data, strlen($data), 0, $ip, $port);
            socket_close($socket);
        } else {
            Server::getServer()->sendto($ip, $port, $data);
        }
    }
}
