<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Table/classes/class.ilTable2GUI.php';

abstract class ilAdobeConnectTableGUI extends ilTable2GUI
{
    protected ilCtrl $ctrl;
    
    protected array $visibleOptionalColumns = array();
    
    /**
     * @var ilAdobeConnectTableDataProvider
     */
    protected $provider;
    
    protected array $optionalColumns = array();
    
    protected array $filter = array();
    
    protected array $optional_filter = array();
    
    /**
     * Set the provider to be used for data retrieval.
     * @params    ilAdobeConnectTableDataProvider $mapper
     */
    public function setProvider(ilAdobeConnectTableDataProvider $provider)
    {
        $this->provider = $provider;
    }
    
    /**
     * Get the registered provider instance
     * @return ilAdobeConnectTableDataProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }
    
    protected function isColumnVisible(string $column): bool
    {
        if (array_key_exists($column, $this->optionalColumns) && !isset($this->visibleOptionalColumns[$column])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * This method can be used to prepare values for sorting (e.g. translations), to filter items etc.
     * It is called before sorting and segmentation.
     * @param array $data
     * @return array
     */
    protected function prepareData(array &$data)
    {
    }
    
    /**
     * This method can be used to manipulate the data of a row after sorting and segmentation
     * @param array $data
     * @return array
     */
    protected function prepareRow(array &$row)
    {
    }
    
    /**
     * Define a final formatting for a cell value
     * @param mixed $column
     * @param array $row
     * @return mixed
     */
    protected function formatCellValue($column, array $row)
    {
        return $row[$column];
    }
    
    final protected function fillRow($row)
    {
        $this->prepareRow($row);
        
        foreach ($this->getStaticData() as $column) {
            $value = $this->formatCellValue($column, $row);
            $this->tpl->setVariable('VAL_' . strtoupper($column), $value);
        }
        
        foreach ($this->optionalColumns as $index => $definition) {
            if (!$this->isColumnVisible($index)) {
                continue;
            }
            
            $this->tpl->setCurrentBlock('optional_column');
            $value = $this->formatCellValue($index, $row);
            if ((string) $value === '') {
                $this->tpl->touchBlock('optional_column');
            } else {
                $this->tpl->setVariable('OPTIONAL_COLUMN_VAL', $value);
            }
            
            $this->tpl->parseCurrentBlock();
        }
    }
    
    /**
     * Return an array of all static (always visible) data fields in a row.
     * For each key there has to be a variable name VAL_<COLUMN_KEY> in your defined row template.
     * Example:
     *     return array('title', 'checkbox');
     *     There have to be two template variables: VAL_TITLE and VAL_CHECKBOX
     * @return array
     * @abstract
     */
    abstract protected function getStaticData();
    
    /**
     * @throws ilException
     */
    public function populate()
    {
        if (!$this->getExternalSegmentation() && $this->getExternalSorting()) {
            $this->determineOffsetAndOrder(true);
        } else {
            if ($this->getExternalSegmentation() || $this->getExternalSorting()) {
                $this->determineOffsetAndOrder();
            }
        }
        
        $params = array();
        if ($this->getExternalSegmentation()) {
            $params['limit'] = $this->getLimit();
            $params['offset'] = $this->getOffset();
        }
        if ($this->getExternalSorting()) {
            $params['order_field'] = $this->getOrderField();
            $params['order_direction'] = $this->getOrderDirection();
        }
        
        $this->determineSelectedFilters();
        $filter = $this->filter;
        
        foreach ($this->optional_filter as $key => $value) {
            if ($this->isFilterSelected($key)) {
                $filter[$key] = $value;
            }
        }
        
        $data = $this->getProvider()->getList($params, $filter);
        
        if (!count($data['items']) && $this->getOffset() > 0 && $this->getExternalSegmentation()) {
            $this->resetOffset();
            $params['limit'] = $this->getLimit();
            $params['offset'] = $this->getOffset();
            $data = $this->getProvider()->getList($params, $filter);
        }
        
        $this->prepareData($data);
        
        $this->setData($data['items']);
        if ($this->getExternalSegmentation()) {
            $this->setMaxCount($data['cnt']);
        }
    }
}