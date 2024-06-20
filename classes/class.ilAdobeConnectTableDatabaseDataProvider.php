<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

abstract class ilAdobeConnectTableDatabaseDataProvider implements ilAdobeConnectTableDataProvider
{
    
    protected ilDBInterface $db;
    protected $parent_obj;
    
    public function __construct(ilDBInterface $db, $parent_obj)
    {
        $this->db = $db;
        $this->parent_obj = $parent_obj;
    }
    
    abstract protected function getSelectPart(array $filter): string;
    abstract protected function getFromPart(array $filter): string;
    abstract protected function getWherePart(array $filter): string;
    abstract protected function getGroupByPart(): string;
    abstract protected function getHavingPart(array $filter): string;
    abstract protected function getOrderByPart(array $params): string;
    abstract protected function getAdditionalItems($data): array;
    
    /**
     * @throws InvalidArgumentException
     */
    public function getList(array $params, array $filter): array
    {
        $data = [
            'items' => [],
            'cnt' => 0
        ];
        
        $select = $this->getSelectPart($filter);
        $where = $this->getWherePart($filter);
        $from = $this->getFromPart($filter);
        $order = $this->getOrderByPart($params);
        $group = $this->getGroupByPart();
        $having = $this->getHavingPart($filter);

        if (isset($params['group'])) {
            if (!is_string($params['group'])) {
                throw new InvalidArgumentException('Please provide a valid group field parameter.');
            }
            
            $group = $params['group'];
        }
        
        if (isset($params['limit'])) {
            if (!is_numeric($params['limit'])) {
                throw new InvalidArgumentException('Please provide a valid numerical limit.');
            }
            
            if (!isset($params['offset'])) {
                $params['offset'] = 0;
            } else {
                if (!is_numeric($params['offset'])) {
                    throw new InvalidArgumentException('Please provide a valid numerical offset.');
                }
            }
            
            $this->db->setLimit($params['limit'], $params['offset']);
        }
        
        $where = strlen($where) ? 'WHERE ' . $where : '';
        $query = "SELECT {$select} FROM {$from} {$where}";
        
        if (strlen($group)) {
            $query .= " GROUP BY {$group}";
        }
        
        if (strlen($having)) {
            $query .= " HAVING {$having}";
        }
        
        if (strlen($order)) {
            $query .= " ORDER BY {$order}";
        }
        
        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $data['items'][] = $row;
        }
        $data = $this->getAdditionalItems($data);
        
        
        if (isset($params['limit'])) {
            $cnt_sql = "SELECT COUNT(*) cnt FROM ({$query}) subquery";
            $row_cnt = $this->db->fetchAssoc($this->db->query($cnt_sql));
            $data['cnt'] = $row_cnt['cnt'];
        }
        
        return $data;
    }
}
