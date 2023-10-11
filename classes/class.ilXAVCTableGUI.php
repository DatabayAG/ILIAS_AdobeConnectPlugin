<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Table/classes/class.ilTable2GUI.php';

class ilXAVCTableGUI extends ilTable2GUI
{
    public function __construct($a_parent_obj, $a_parent_cmd = "")
    {
        global $DIC;
        
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        
        parent::__construct($a_parent_obj, $a_parent_cmd);
        
        
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
    }
    
    public function fillRow($a_set)
    {
        foreach ($a_set as $key => $value) {
            $this->tpl->setVariable("VAL_" . strtoupper($key), $value);
        }
    }
}