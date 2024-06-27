<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilAdobeConnectPlugin extends ilRepositoryObjectPlugin
{
    public const CTYPE = 'Services';
    public const CNAME = 'Repository';
    public const SLOT_ID = 'robj';
    public const PNAME = 'AdobeConnect';
    private static $instance;

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    public static function getInstance()
    {
        global $DIC;

        if (self::$instance === null) {
            $component_factory = $DIC['component.factory'];
            $plugin_obj = $component_factory->getPlugin('xavc');
            return self::$instance =  $plugin_obj;
        }

        return self::$instance;
    }

    protected function uninstallCustom(): void
    {
        global $DIC;

        $ilDB = $DIC->database();
        
        if ($ilDB->tableExists('rep_robj_xavc_data')) {
            $ilDB->dropTable('rep_robj_xavc_data');
        }
        
        if ($ilDB->tableExists('rep_robj_xavc_settings')) {
            $ilDB->dropTable('rep_robj_xavc_settings');
        }
        
        if ($ilDB->tableExists('rep_robj_xavc_users')) {
            $ilDB->dropTable('rep_robj_xavc_users');
        }
        
        if ($ilDB->tableExists('rep_robj_xavc_members')) {
            $ilDB->dropTable('rep_robj_xavc_members');
        }
        
        if ($ilDB->tableExists('rep_robj_xavc_gloperm')) {
            $ilDB->dropTable('rep_robj_xavc_gloperm');
        }
        
        if ($ilDB->sequenceExists('rep_robj_xavc_gloperm')) {
            $ilDB->dropSequence('rep_robj_xavc_gloperm');
        }

        if ($ilDB->tableExists('rep_robj_xavc_tpl')) {
            $ilDB->dropTable('rep_robj_xavc_tpl');
        }

        foreach (['il_xavc_admin', 'il_xavc_member'] as $tpl) {
            $obj_ids = ilObject::_getIdsForTitle($tpl, 'rolt');
            foreach ($obj_ids as $obj_id) {
                $obj = ilObjectFactory::getInstanceByObjId($obj_id, false);
                if (!($obj instanceof ilObjRoleTemplate)) {
                    continue;
                }
                $obj->delete();
            }
        }
    }
}
