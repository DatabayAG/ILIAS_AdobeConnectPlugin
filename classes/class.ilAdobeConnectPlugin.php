<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

//include_once 'Services/Repository/classes/class.ilRepositoryObjectPlugin.php';
//include_once dirname(__FILE__) . '/class.ilXMLApiFactory.php';

class ilAdobeConnectPlugin extends ilRepositoryObjectPlugin
{
    public const CTYPE = 'Services';
    public const CNAME = 'Repository';
    public const SLOT_ID = 'robj';
    public const PNAME = 'AdobeConnect';
    private static ilPlugin $instance;

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    public static function getInstance()
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        /** @var ilComponentRepository $component_repository */
        $component_repository = $DIC['component.repository'];
        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC['component.factory'];

        $plugin_info = $component_repository->getComponentByTypeAndName(
            self::CTYPE,
            self::CNAME
        )->getPluginSlotById(self::SLOT_ID)->getPluginByName(self::PNAME);

        self::$instance = $component_factory->getPlugin($plugin_info->getId());

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
        
        foreach (['cb_extended', 'cb_simple'] as $settings_tpl) {
            $ilDB->manipulateF(
                'DELETE FROM adm_settings_template WHERE type = %s  AND title = %s',
                ['text', 'text'],
                ['xavc', $settings_tpl]
            );
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
