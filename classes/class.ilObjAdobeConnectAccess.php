<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Repository/classes/class.ilObjectPluginAccess.php';

class ilObjAdobeConnectAccess extends ilObjectPluginAccess
{
    public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
    {
        global $DIC;
        $ilUser = $DIC->user();
        $ilObjDataCache = $DIC['ilObjDataCache'];
        
        if (!$a_user_id) {
            $a_user_id = $ilUser->getId();
        }
        
        if ($a_user_id == $ilObjDataCache->lookupOwner($a_obj_id)) {
            return true;
        }
        
        switch ($a_permission) {
            case 'visible':
            case 'edit_permission':
            case 'delete':
                return true;
                break;
            
            case 'write':
            case 'read':
                if (
                    !self::_hasMemberRole((int) $a_user_id, (int)$a_ref_id)
                    &&
                    !self::_hasAdminRole((int)$a_user_id, (int)$a_ref_id)
                ) {
                    return false;
                }
                
                return true;
                break;
        }
    }
    
    public static function _hasMemberRole(int $a_user_id, int $a_ref_id): bool
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();
        
        $roles = $rbacreview->getRoleListByObject($a_ref_id);
        $result = false;
        
        foreach ($roles as $role) {
            if (strpos($role['title'], 'il_xavc_member') !== false) {
                $result = $rbacreview->isAssigned($a_user_id, $role['rol_id']);
                
                break;
            }
        }
        return $result;
    }
    
    public static function _hasAdminRole(int $a_user_id, int $a_ref_id): bool
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();
        
        $roles = $rbacreview->getRoleListByObject($a_ref_id);
        $result = false;
        
        foreach ($roles as $role) {
            if (strpos($role['title'], 'il_xavc_admin') !== false) {
                $result = $rbacreview->isAssigned($a_user_id, $role['rol_id']);
                
                break;
            }
        }
        
        return $result;
    }
    
    public static function getLocalAdminRoleTemplateId(): int
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        // try reading permission template for local admin role
        $res = $ilDB->queryf('SELECT obj_id FROM object_data WHERE type = %s AND title = %s',
            array('text', 'text'), array('rolt', 'il_xavc_admin')
        );
        
        $admin_rolt_id = 0;
        while ($row = $ilDB->fetchObject($res)) {
            $admin_rolt_id = $row->obj_id;
            break;
        }
        
        if (!$admin_rolt_id) {
            $admin_rolt_id = self::initLocalAdminRoleTemplate();
        }
        
        return $admin_rolt_id;
    }
    
    public static function getLocalMemberRoleTemplateId(): int
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        // try reading permission template for local admin role
        $res = $ilDB->queryf('SELECT obj_id FROM object_data WHERE type = %s AND title = %s',
            array('text', 'text'), array('rolt', 'il_xavc_member')
        );
        
        $participant_rolt_id = 0;
        while ($row = $ilDB->fetchObject($res)) {
            $participant_rolt_id = $row->obj_id;
            break;
        }
        
        if (!$participant_rolt_id) {
            $participant_rolt_id = self::initLocalMemberRoleTemplate();
        }
        
        return $participant_rolt_id;
    }
    
    private static function initLocalAdminRoleTemplate(): int
    {
        $xavc_typ_id = self::checkObjectOperationPermissionsInitialized();
        
        global $DIC;
        $ilDB = $DIC->database();
        
        $admin_rolt_id = 0;
        
        $res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
            array('text', 'text'), array('rolt', 'il_xavc_admin'));
        
        while ($row = $ilDB->fetchObject($res)) {
            $admin_rolt_id = $row->obj_id;
            # break;
        }
        
        if ((int) $admin_rolt_id >= 0) {
            global $DIC;
            $rbacadmin = $DIC->rbac()->admin();
            
            // create local admin role template
            $admin_rolt_id = $ilDB->nextId('object_data');
            
            $ilDB->manipulateF("INSERT INTO object_data (obj_id, type, title, description, owner, create_date, last_update) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                array('integer', 'text', 'text', 'text', 'integer', 'timestamp', 'timestamp'),
                array(
                    $admin_rolt_id,
                    'rolt',
                    'il_xavc_admin',
                    'Administrator role template for Adobe Connect Interface Object',
                    -1,
                    ilUtil::now(),
                    ilUtil::now()
                )
            );
            
            // link permissions assignable on object's role folder
            $rolf_typ_id = 0;
            $res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
                array('text', 'text'), array('typ', 'rolf'));
            
            while ($row = $ilDB->fetchObject($res)) {
                $rolf_typ_id = (int) $row->obj_id;
                break;
            }
            $xavc_rolf_ops = array();
            $res = $ilDB->queryF("SELECT ops_id FROM rbac_ta WHERE typ_id = %s",
                array('integer'), array($rolf_typ_id));
            
            while ($row = $ilDB->fetchObject($res)) {
                $xavc_rolf_ops[] = (int) $row->ops_id;
            }
            if (!count($xavc_rolf_ops)) {
                throw new Exception('empty array $xavc_rolf_ops');
            }
            $rbacadmin->setRolePermission($admin_rolt_id, 'rolf', $xavc_rolf_ops, ROLE_FOLDER_ID);
            
            // link permissions assignable on object itself
            $xavc_obj_ops = array();
            $res = $ilDB->queryF("SELECT ops_id FROM rbac_ta WHERE typ_id = %s",
                array('integer'), array($xavc_typ_id));
            while ($row = $ilDB->fetchObject($res)) {
                $xavc_obj_ops[] = (int) $row->ops_id;
            }
            if (!count($xavc_obj_ops)) {
                throw new Exception('empty array$xavc_obj_ops');
            }
            $rbacadmin->setRolePermission($admin_rolt_id, 'xavc', $xavc_obj_ops, ROLE_FOLDER_ID);
            
            // assign local admin role template to (global?) role folder
            $rbacadmin->assignRoleToFolder($admin_rolt_id, ROLE_FOLDER_ID, 'n');
        }
        
        return $admin_rolt_id;
    }
    
    private static function initLocalMemberRoleTemplate(): int
    {
        // checks for surely initialized extra permissions for xavc
        // (and also returns obj_id of xavc type definition)
        $xavc_typ_id = self::checkObjectOperationPermissionsInitialized();
        
        global $DIC;
        $ilDB = $DIC->database();
        
        $member_rolt_id = 0;
        
        $res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
            array('text', 'text'), array('rolt', 'il_xavc_member'));
        
        while ($row = $ilDB->fetchObject($res)) {
            $member_rolt_id = (int) $row->obj_id;
            break;
        }
        
        if (!$member_rolt_id) {
            global $DIC;
            $rbacadmin = $DIC->rbac()->admin();
            
            // create local member role template
            $member_rolt_id = $ilDB->nextId('object_data');
            
            $ilDB->manipulateF("INSERT INTO object_data (obj_id, type, title, description, owner, create_date, last_update) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                array('integer', 'text', 'text', 'text', 'integer', 'timestamp', 'timestamp'),
                array(
                    $member_rolt_id,
                    'rolt',
                    'il_xavc_member',
                    'Member role template for Adobe Connect Interface Object',
                    -1,
                    ilUtil::now(),
                    ilUtil::now()
                )
            );
            
            // link permissions assignable on object
            $xavc_obj_ops = array();
            $res = $ilDB->queryF("SELECT rbac_ta.ops_id, rbac_operations.operation FROM rbac_ta LEFT JOIN rbac_operations ON rbac_ta.ops_id = rbac_operations.ops_id " .
                "WHERE rbac_ta.typ_id = %s AND rbac_operations.operation IN(%s,%s,%s)",
                array('integer', 'text', 'text', 'text'),
                array($xavc_typ_id, 'visible', 'read', 'member'));
            
            while ($row = $ilDB->fetchObject($res)) {
                $xavc_obj_ops[] = (int) $row->ops_id;
            }
            
            if (!count($xavc_obj_ops)) {
                throw new Exception('empty array $xavc_obj_ops');
            }
            
            $rbacadmin->setRolePermission($member_rolt_id, 'xavc', $xavc_obj_ops, ROLE_FOLDER_ID);
            
            // assign local member role template to (global?) role folder
            $rbacadmin->assignRoleToFolder($member_rolt_id, ROLE_FOLDER_ID, 'n');
        }
        
        return $member_rolt_id;
    }
    
    private static function checkObjectOperationPermissionsInitialized(): int
    {
        
        global $DIC;
        $ilDB = $DIC->database();
        
        // lookup obj_id of xavc type definition
        $xavc_typ_id = 0;
        $res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
            array('text', 'text'), array('typ', 'xavc'));
        while ($row = $ilDB->fetchObject($res)) {
            $xavc_typ_id = (int) $row->obj_id;
            #break;
        }
        
        //check initialized permissions
        $check = $ilDB->queryF('SELECT ops_id FROM rbac_ta WHERE typ_id = %s',
            array('integer'), array($xavc_typ_id));
        
        $init_ops = array();
        while ($row = $ilDB->fetchAssoc($check)) {
            $init_ops[] = $row['ops_id'];
        }
        //insert or update additional permissions for object type
        // general permissions: visible, read, write, delete, copy
        $xavc_ops_ids = array();
        $res_1 = $ilDB->queryF('
				SELECT ops_id, operation FROM rbac_operations
				WHERE class = %s
				AND (operation = %s
				OR operation = %s
				OR operation = %s
				OR operation = %s
				OR operation = %s)',
            array('text', 'text', 'text', 'text', 'text', 'text'),
            array('general', 'visible', 'read', 'write', 'delete', 'copy'));
        
        while ($row_1 = $ilDB->fetchAssoc($res_1)) {
            $xavc_ops_ids[$row_1['operation']] = (int) $row_1['ops_id'];
        }
        
        foreach ($xavc_ops_ids as $x_operation => $x_id) {
            if (!in_array($x_id, $init_ops)) {
                //insert missing operation
                $ilDB->insert('rbac_ta',
                    array(
                        'typ_id' => array('integer', $xavc_typ_id),
                        'ops_id' => array('integer', $x_id)
                    ));
            }
        }
        return (int) $xavc_typ_id;
    }
}
