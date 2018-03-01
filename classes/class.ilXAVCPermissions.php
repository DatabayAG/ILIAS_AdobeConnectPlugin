<?php

class ilXAVCPermissions
{
	private $permissions = array();
	
	public function __construct()
	{
	}

	public static function getPermissionsArray()
	{
		global $DIC; 
		$ilDB = $DIC->database();
		
		$res = $ilDB->query('SELECT * FROM rep_robj_xavc_gloperm ORDER BY id, permission');
		
		$permissions = array();
		
		while($row = $ilDB->fetchAssoc($res))
		{
			$permissions[$row['permission']][$row['role']] = $row['has_access'];
		}
		
		return $permissions;
 	}


	public function setPermissions($permissions)
	{
		global $DIC;
		$ilDB = $DIC->database();

		// reset all permissions
		$ilDB->update('rep_robj_xavc_gloperm',
		array('has_access' => array('integer', 0)),
		array('has_access' => array('integer', 1)));
		
		//update new permissions
		foreach($permissions as $permission => $roles)
		{
			foreach($roles as $role)
			{

				$ilDB->update('rep_robj_xavc_gloperm',
				array('has_access' => array('integer', 1)),
				array('permission' => array('text', $permission),
					  'role'	   => array('text', $role)));
			}
		}
	}

	/**
	 * @param integer $user_id
	 * @param integer $ref_id
	 * @param integer $permission_id
	 * @return bool
	 */
	public static function hasAccess($user_id, $ref_id, $permission)
	{
		global $DIC;
		$ilDB = $DIC->database();
		
		//lookupRole
		$res = $ilDB->queryF('
			SELECT has_access FROM rep_robj_xavc_members mem
			LEFT JOIN rep_robj_xavc_gloperm perm ON mem.xavc_status = perm.role
			WHERE mem.user_id = %s AND mem.ref_id = %s
			AND perm.permission = %s', 
			array('integer', 'integer', 'text'), array($user_id, $ref_id, $permission));
		
		$access = false;
		
		while($row = $ilDB->fetchAssoc($res))
		{
			$access = (bool)$row['has_access'];
		}
		
		return (bool)$access;
	}

	/**
	 * @param $permission  AdobeConnectPermissions
	 * @param $role   host | mini-host | view | denied 
	 * @return int 
	 */
	public static function lookupPermission($permission, $role)
	{
		global $DIC;
		$ilDB = $DIC->database();
		
		$res = $ilDB->queryF('
			SELECT has_access FROM rep_robj_xavc_gloperm 
			WHERE permission = %s AND role = %s',
			array('text', 'text'), array($permission, $role));
		
		$row = $ilDB->fetchAssoc($res);
		
		return (int)$row['has_access'];
	}
}