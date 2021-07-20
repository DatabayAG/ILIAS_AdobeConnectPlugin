<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once 'Services/Repository/classes/class.ilRepositoryObjectPlugin.php';
include_once dirname(__FILE__) . '/class.ilXMLApiFactory.php';

/**
 * Adobe Connect plugin for ILIAs
 * @author Félix Paulano
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilAdobeConnectPlugin extends ilRepositoryObjectPlugin
{
    /**
     * Return the plugin name
     * @return String
     */
    public function getPluginName()
    {
        return "AdobeConnect";
    }
    
    /**
     * @param $string a_type
     * @param $string $a_size
     * @return string
     */
    public static function _getIcon($a_type, $a_size)
    {
        return ilPlugin::_getImagePath(
            IL_COMP_SERVICE, 'Repository', 'robj',
            ilPlugin::lookupNameForId(IL_COMP_SERVICE, 'Repository', 'robj', $a_type),
            'icon_' . $a_type . '.svg'
        );
    }
    
    /**
     *
     */
    protected function uninstallCustom()
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
        
        foreach (array('cb_extended', 'cb_simple') as $settings_tpl) {
            $ilDB->manipulateF(
                'DELETE FROM adm_settings_template WHERE type = %s  AND title = %s',
                array('text', 'text'),
                array('xavc', $settings_tpl)
            );
        }
        
        foreach (array('il_xavc_admin', 'il_xavc_member') as $tpl) {
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
