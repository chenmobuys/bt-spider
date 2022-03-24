<?php

namespace BTSpider\Service;

use RuntimeException;
use BTSpider\Support\Bencode;
use BTSpider\Support\Utils;
use Swoole\Coroutine\Client;

define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));

/**
 * @see http://www.bittorrent.org/beps/bep_0009.html
 */
class MDService
{
    private const BT_PROTOCOL = 'BitTorrent protocol';
    private const BT_MSG_ID = 20;
    private const EXT_HANDSHAKE_ID = 0;
    private const PIECE_LENGTH = 16384;
    private const UT_METADATA = 'ut_metadata';
    private const METADATA_SIZE = 'metadata_size';

    private const TIMEOUT = 1;

    /**
     * 从指定服务器上下载文件信息
     * 
     * @param [type] $ip
     * @param [type] $port
     * @param [type] $infoHash
     * @return array
     * @example 
     * [
     *      'files' => [
     *          [
     *              'length' => 99242890,
     *              'path' => [
     *                  '文件名'
     *              ]
     *          ]
     *      ],
     *      'name' => '名称',
     *      'piece length' => 4194304,
     *      'pieces' => '',
     * ]
     */
    public static function getMetadata($ip, $port, $infoHash)
    {
        $client = self::getClient($ip, $port);

        self::handshake($client, $infoHash);

        $handshakeExtRecvData = self::handshakeExt($client);

        return self::getMetadataInfo($client, $handshakeExtRecvData);
    }

    private static function handshake($client, $infoHash)
    {
        $handshakeSendData = chr(strlen(self::BT_PROTOCOL))
            . self::BT_PROTOCOL
            . "\x00\x00\x00\x00\x00\x10\x00\x00"
            . $infoHash
            . Utils::randomBytes();
        if (!self::send($client, $handshakeSendData)) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE 发送信息失败。');
        }
        if (!($handshakeRecvData = self::recv($client, 4096))) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE 接收信息失败。');
        }
        $handshakeRecvDataHeaderLength =  ord(substr($handshakeRecvData, 0, 1));
        if ($handshakeRecvDataHeaderLength != strlen(self::BT_PROTOCOL)) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE 返回信息校验失败，原因：协议长度不正确。');
        }
        $handshakeRecvDataHeader = substr($handshakeRecvData, 1, $handshakeRecvDataHeaderLength);
        if ($handshakeRecvDataHeader != self::BT_PROTOCOL) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE 返回信息校验失败，原因：协议不正确。');
        }
        $handshakeRecvDataInfoHash = substr($handshakeRecvData, 1 + $handshakeRecvDataHeaderLength + 8, 20);
        if ($handshakeRecvDataInfoHash != $infoHash) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE 返回信息校验失败，原因：INFO_HASH 不一致。');
        }
    }

    private static function handshakeExt($client)
    {
        $handshakeExtData = chr(self::BT_MSG_ID)
            . chr(self::EXT_HANDSHAKE_ID)
            . Bencode::encode(['m' => ['ut_metadata' => 1]]);
        $handshakeExtDataLength = pack("I", strlen($handshakeExtData));
        if (!BIG_ENDIAN) {
            $handshakeExtDataLength = strrev($handshakeExtDataLength);
        }
        $handshakeExtSendData = $handshakeExtDataLength . $handshakeExtData;
        if (!self::send($client, $handshakeExtSendData)) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE_EXE 发送消息失败。');
        }
        if (!$handshakeExtRecvData = self::recv($client, 4096)) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE_EXE 接收消息失败。');
        }

        return $handshakeExtRecvData;
    }

    private function getMetadataInfo($client, $handshakeExtRecvData)
    {
        $UTMetadataIndex = strpos($handshakeExtRecvData, self::UT_METADATA) + strlen(self::UT_METADATA) + 1;
        $UTMetadata = (int) ($handshakeExtRecvData[$UTMetadataIndex] ?? -1);

        if ($UTMetadata === -1) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE_EXT 返回信息校验失败，原因：UT_METADATA 信息不存在。');
        }

        $metadataSizeIndex = strpos($handshakeExtRecvData, self::METADATA_SIZE) + strlen(self::METADATA_SIZE) + 1;
        $metadataSizeTemp = substr($handshakeExtRecvData, $metadataSizeIndex);
        $metadataSize = (int) substr($metadataSizeTemp, 0, strpos($metadataSizeTemp, "e"));

        if ($metadataSize > self::PIECE_LENGTH * 1000) {
            throw new RuntimeException('获取 METADATA 时，HANDSHAKE_EXT 返回信息校验失败，原因：METADATA_SIZE 不能大于' . (self::PIECE_LENGTH * 1000) . '。');
        }

        $metadataEncodeData = '';
        $pieceCount = ceil($metadataSize / self::PIECE_LENGTH);

        for ($i = 0; $i < $pieceCount; $i++) {
            $metadataEncodeData .= self::getPieceOfMetadata($client, $UTMetadata, $i);
        }
        $metadataData = Bencode::decode($metadataEncodeData);

        if (is_null($metadataData)) {
            throw new RuntimeException('获取 METADATA 时，数据解析失败。');
        }

        return $metadataData;
    }

    private static function getPieceOfMetadata($client, $UTMetadata, $piece)
    {
        $pieceOfMetadataData = chr(self::BT_MSG_ID)
            . chr($UTMetadata)
            . Bencode::encode(['msg_type' => 0, 'piece' => $piece]);
        $pieceOfMetadataDataLength = pack('I', strlen($pieceOfMetadataData));
        if (!BIG_ENDIAN) {
            $pieceOfMetadataDataLength = strrev($pieceOfMetadataDataLength);
        }
        $pieceOfMetadataSendData = $pieceOfMetadataDataLength . $pieceOfMetadataData;

        if (!self::send($client, $pieceOfMetadataSendData)) {
            throw new RuntimeException('获取 METADATA 时，PIECE 发送信息失败。');
        }

        if (Utils::isCygwinOS()) {
            $pieceOfMetadataRecvDataLength = self::recv($client, 4);

            if ($pieceOfMetadataRecvDataLength === false) {
                throw new RuntimeException('获取 METADATA 时，PIECE 返回信息校验失败。原因：数据不正确。[1]');
            }
            if (strlen($pieceOfMetadataRecvDataLength) != 4) {
                throw new RuntimeException('获取 METADATA 时，PIECE 返回信息校验失败。原因：数据不正确。[2]');
            }
            if (($pieceOfMetadataRecvDataLength = (int) unpack('N', $pieceOfMetadataRecvDataLength)[1] ?? 0) == 0) {
                throw new RuntimeException('获取 METADATA 时，PIECE 返回信息校验失败。原因：数据不正确。[3]');
            }
            if ($pieceOfMetadataRecvDataLength > self::PIECE_LENGTH * 1000) {
                throw new RuntimeException('获取 METADATA 时，PIECE 返回信息校验失败。原因：数据不正确。[4]');
            }

            $pieceOfMetadataRecvData = '';
            while (true) {
                if ($pieceOfMetadataRecvDataLength > 8192) {
                    if (($recvTemp = self::recv($client, 8192)) == false) {
                        throw new RuntimeException('获取 METADATA 时，PIECE 接收信息中断。[1]');
                    } else {
                        $pieceOfMetadataRecvData .= $recvTemp;
                        $pieceOfMetadataRecvDataLength = $pieceOfMetadataRecvDataLength - 8192;
                    }
                } else {
                    if (($recvTemp = self::recv($client, $pieceOfMetadataRecvDataLength)) == false) {
                        throw new RuntimeException('获取 METADATA 时，PIECE 接收信息中断。[2]');
                    } else {
                        $pieceOfMetadataRecvData .= $recvTemp;
                        break;
                    }
                }
            }
        } else {
            // 设置通信协议，\Swoole\Client 会根据协议返回完整数据
            $client->set([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0, //第 N 个字节是包长度的值
                'package_body_offset' => 4, //第几个字节开始计算长度
                'package_max_length' => 1024 * 1024 * 20, //协议最大长度
            ]);
            $pieceOfMetadataRecvData = self::recv($client);
        }

        $ee = substr($pieceOfMetadataRecvData, 0, strpos($pieceOfMetadataRecvData, 'ee') + 2);
        $dict = Bencode::decode(substr($ee, strpos($pieceOfMetadataRecvData, 'd')));

        if(($dict['msg_type'] ?? null) == 2) {
            throw new RuntimeException('获取 METADATA 时，对方服务器拒绝返回数据。');
        }

        if (($dict['msg_type'] ?? null) != 1) {
            throw new RuntimeException('获取 METADATA 时，PIECE 信息解析异常。[1]');
        }

        $pieceOfMetadata = substr($pieceOfMetadataRecvData, strpos($pieceOfMetadataRecvData, 'ee') + 2);

        if (strlen($pieceOfMetadata) > self::PIECE_LENGTH) {
            throw new RuntimeException('获取 METADATA 时，PIECE 信息解析异常。[2]');
        }

        return $pieceOfMetadata;
    }

    private static function getClient($ip, $port)
    {
        $connectErrorMessage = '获取 METADATA 时，连接服务器失败。服务器信息：' . $ip . ':' . $port . '。';
        if (Utils::isCygwinOS()) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!@socket_connect($socket, $ip, $port)) {
                throw new RuntimeException($connectErrorMessage);
            }
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => self::TIMEOUT, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::TIMEOUT, 'usec' => 0]);
            return $socket;
        } else {
            $client = new Client(SWOOLE_SOCK_TCP);
            if (!@$client->connect($ip, $port, self::TIMEOUT)) {
                throw new RuntimeException($connectErrorMessage);
            }
            return $client;
        }
    }

    private static function send($client, $data)
    {
        if ($client instanceof Client) {
            return $client->send($data);
        } else {
            return socket_send($client, $data, strlen($data), 0);
        }
    }

    private static function recv($client, $length = null)
    {
        if ($client instanceof Client) {
            return $client->recv();
        } else {
            socket_recv($client, $data, $length, 0);
            return $data;
        }
    }
}
