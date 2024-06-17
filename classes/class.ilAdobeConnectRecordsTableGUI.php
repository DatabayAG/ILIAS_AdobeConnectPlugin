<?php
/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilAdobeConnectRecordsTableGUI extends ilTable2GUI
{
    public const MODE_VIEW = 1;
    public const MODE_EDIT = 2;
    protected array $visibleOptionalColumns = array();
    protected array $optionalColumns = array();
    
    private int $viewMode = self::MODE_VIEW;
    
    private $template_context = '';
    
    public function __construct($a_parent_obj, $a_parent_cmd = '', $a_template_context = '', $view_mode)
    {
        $this->setId('xavc_cnt_' . $a_parent_obj->object->getId() . '_' . $view_mode);
        $this->setPrefix('xavc_cnt_' . $a_parent_obj->object->getId() . '_' . $view_mode);
        $this->viewMode = $view_mode;
        $this->template_context = $a_template_context;
        
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
        
    }
    
    public function setViewMode($mode)
    {
        $this->viewMode = (int) $mode;
        return $this;
    }
    
    public function getViewMode(): int
    {
        return $this->viewMode;
    }
    
    public function init()
    {
        $this->setFormAction($this->parent_obj->ctrl->getFormAction($this->parent_obj, 'showContent'));
        
        $this->addColumn($this->parent_obj->pluginObj->txt('content_name'), 'title', '50%');
        $this->addColumn($this->parent_obj->pluginObj->txt('content_type'), 'type');
        $this->addColumn($this->parent_obj->pluginObj->txt('content_date_created'), 'date_created');
        if ($this->viewMode == self::MODE_EDIT) {
            $this->addColumn($this->parent_obj->lng->txt('actions'), 'actions', '1%');
        }
        
        $this->setRowTemplate('tpl.meeting_record_row.html', $this->parent_obj->pluginObj->getDirectory());
        
        $this->setDefaultOrderField('type');
        $this->setDefaultOrderDirection('desc');
        
        $this->setShowRowsSelector(true);
        return $this;
    }
    
    protected function formatCellValue($column, array $row)
    {
        return $row[$column];
    }
    
    public function fillRow($a_set): void
    {
        foreach ($a_set as $key => $value) {
            $value = $this->formatCellValue($key, array($key => $value));
            $this->tpl->setVariable(strtoupper($key), $this->formatField($key, $value));
        }
    }
    
    protected function formatField(string $field, string $content): string
    {
        switch ($field) {
            case 'date_created':
                $content = ilDatePresentation::formatDate(new ilDateTime($content, IL_CAL_UNIX));
                break;
        }
        
        return $content;
    }
    
    public function numericOrdering(string $field): bool
    {
        switch ($field) {
            case 'date_created':
                return true;
        }
        
        return false;
    }
}