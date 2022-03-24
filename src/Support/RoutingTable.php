<?php

namespace BTSpider\Support;

use Swoole\Table;

/**
 * 路由表-SwooleTable
 */
class RoutingTable
{
    // 主分支最大深度
    const MAIN_LEVEL_MAX = 160;
    // 旁分支最大深度
    const SIDE_LEVEL_MAX = 10;
    // BUCKET桶大小
    const BUCKET_SIZE = 8;
    // NODE_ID长度
    const NODE_ID_LENGTH = 20;

    // 当前节点ID
    private $nodeId;

    /** @var \Swoole\Table */
    private $tables;

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->nodeId = Utils::randomBytes(self::NODE_ID_LENGTH);
        $this->tables = new Table(self::MAIN_LEVEL_MAX * 45);
        $this->tables->column('id', Table::TYPE_STRING, 20);
        $this->tables->column('ip', Table::TYPE_STRING, 16);
        $this->tables->column('port', Table::TYPE_INT);
        $this->tables->column('score', Table::TYPE_INT);
        $this->tables->create();
    }

    /**
     * 获取路由表数据
     *
     * @return \Swoole\Table
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * 获取当前路由表的根节点
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * 批量添加节点
     * @var Node[] $nodes 节点数组
     */
    public function addNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * 添加节点
     * @var array $node 节点
     * @return bool
     */
    public function addNode(array $node): bool
    {
        if (array_keys($node) != ['id', 'ip', 'port']) {
            return false;
        }
        $logicDistance = $this->logicDistance($node['id']);
        if ($logicDistance === 0) {
            return false;
        }
        $nodes = $this->getNodesByLogicDistance($logicDistance);
        $validNodes = array_filter($nodes);
        $validNodesCount = count($validNodes);
        $existsNodes = array_filter($nodes, function ($item) use ($node) {
            return $node['id'] === $item['id'];
        });

        if ($existsNodes) {
            $existsNodeIndex = current(array_keys($existsNodes));
            $this->tables->incr($logicDistance . '_' . $existsNodeIndex, 'score');
            return true;
        } else if ($validNodesCount < self::BUCKET_SIZE) {
            $this->tables->set($logicDistance . '_' . $validNodesCount, $node);
            return true;
        } else {
            $this->tables->set($logicDistance . '_' . mt_rand(0, self::BUCKET_SIZE - 1), $node);
            return true;
        }
        return false;
    }

    /**
     * @var string $nodeId
     */
    public function delNodeByNodeId($nodeId)
    {
        if (strlen($nodeId) != self::NODE_ID_LENGTH) {
            return false;
        }
        $logicDistance = $this->logicDistance($nodeId);
        $nodes = $this->getNodesByLogicDistance($logicDistance);
        $existsNodes = array_filter($nodes, function ($item) use ($nodeId) {
            return $nodeId === $item['id'];
        });
        if ($existsNodes) {
            $existsNodeIndex = current(array_keys($existsNodes));
            unset($nodes[$existsNodeIndex]);
            $this->setNodesByLogicDistance($logicDistance, $nodes);
            return true;
        }

        return false;
    }

    /**
     * 
     * @param @nodeId
     * @return array $nodes
     */
    public function getTopNodesByNodeId($nodeId = null, $size = self::BUCKET_SIZE)
    {
        if (is_null($nodeId)) {
            $nodes = [];
            for ($i = 1; $i <= self::MAIN_LEVEL_MAX; $i++) {
                $existNodes = $this->getNodesByLogicDistance($i);
                $existNodes = array_slice(array_filter($existNodes), 0, $size - count($nodes));
                $nodes = array_merge($nodes, $existNodes);
                if (count($nodes) === self::BUCKET_SIZE) {
                    break;
                }
            }
            return $nodes;
        } else {
            $nodes = [];
            foreach ($this->tables as $value) {
                $nodes[] = $value;
            }
            usort($nodes, function ($node1, $node2) use ($nodeId) {
                $dist1 = $this->distance($node1['id'], $nodeId);
                $dist2 = $this->distance($node2['id'], $nodeId);
                if ($dist1 === $dist2) {
                    return 0;
                }
                return $dist1 < $dist2 ? -1 : 1;
            });
            return array_slice($nodes, 0, $size);
        }
    }

    /**
     * 查找同一逻辑距离的节点
     *
     * @param int $logicDistance
     * @return array
     */
    private function getNodesByLogicDistance(int $logicDistance): array
    {
        $nodes = [];
        for ($i = 0; $i < self::BUCKET_SIZE; $i++) {
            if ($node = $this->tables->get($logicDistance . '_' . $i)) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private function setNodesByLogicDistance(int $logicDistance, array $nodes)
    {
        for ($i = 0; $i < self::BUCKET_SIZE; $i++) {
            $this->tables->set($logicDistance . '_' . $i, $nodes[$i]);
        }
    }

    private function distance($nodeId1, $nodeId2 = null)
    {
        return is_null($nodeId2) ? ($this->nodeId ^ $nodeId1) : ($nodeId1 ^ $nodeId2);
    }

    private function logicDistance($nodeId1, $nodeId2 = null): int
    {
        $dist = $this->distance($nodeId1, $nodeId2);
        for ($i = 0; $i < self::MAIN_LEVEL_MAX / 8; $i++) {
            $byte = ord($dist[$i]);
            if ($byte !== 0) {
                for ($j = 8; $j >= 1; $j--) {
                    $mask = 1 << ($j - 1);
                    if (($byte & $mask) === $mask)
                        return 8 * (self::MAIN_LEVEL_MAX / 8 - $i - 1) + $j;
                }
                assert('THIS MAY NEVER HAPPEN');
            }
        }
        return 0;
    }
}
