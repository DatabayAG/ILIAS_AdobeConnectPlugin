<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Repository/classes/class.ilObjectPluginAccess.php';

/**
 * Access/Condition checking for AdobeVC object
 * @author     Michael Jansen <mjansen@databay.de>
 * @author     Björn Heyser <bheyser@databay.de>
 * @author     Jan Posselt <jposselt@databay.de>
 * @author     Nadia Ahmad <nahmad@databay.de>
 * @version    $Id$
 */
class ilObjAdobeConnectAccess extends ilObjectPluginAccess
{
	/**
	 * Checks wether a user may invoke a command or not
	 * (this method is called by ilAccessHandler::checkAccess)
	 * Please do not check any preconditions handled by
	 * ilConditionHandler here. Also don't do usual RBAC checks.
	 * @param    string $a_cmd        command (not permission!)
	 * @param    string $a_permission permission
	 * @param    int    $a_ref_id     reference id
	 * @param    int    $a_obj_id     object id
	 * @param    int    $a_user_id    user id (if not provided, current user is taken)
	 * @return    boolean        true, if everything is ok
	 */
	public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
	{
		/**
		 * @var $ilUser         ilObjUser
		 * @var $ilObjDataCache ilObjectDataCache
		 */
		global $ilUser, $ilObjDataCache;

		if(!$a_user_id)
		{
			$a_user_id = $ilUser->getId();
		}

		if($a_user_id == $ilObjDataCache->lookupOwner($a_obj_id))
		{
			return true;
		}

		switch($a_permission)
		{
			case 'visible':
				return true;
				break;

			case 'delete':
				return true;	
					break;
				
			case 'write':
			case 'edit_permission':
			case 'read':
				if(
					!self::_hasMemberRole($a_user_id, $a_ref_id)
					&&
					!self::_hasAdminRole($a_user_id, $a_ref_id)
				)
				{
					return false;
				}

				return true;
				break;
		}
	}

	/**
	 * @static
	 * @param int $a_user_id
	 * @param int $a_ref_id
	 * @return bool
	 */
	public static function _hasMemberRole($a_user_id, $a_ref_id)
	{
		/**
		 * @var $rbacreview ilRbacReview
		 */
		global $rbacreview;

		$roles  = $rbacreview->getRoleListByObject($a_ref_id);
		$result = false;

		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_member') !== false)
			{
				$result = $rbacreview->isAssigned($a_user_id, $role['rol_id']);

				break;
			}
		}
		return $result;
	}

	/**
	 * @static
	 * @param int $a_user_id
	 * @param int $a_ref_id
	 * @return bool
	 */
	public static function _hasAdminRole($a_user_id, $a_ref_id)
	{
		/**
		 * @var $rbacreview ilRbacReview
		 */
		global $rbacreview;

		$roles  = $rbacreview->getRoleListByObject($a_ref_id);
		$result = false;

		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$result = $rbacreview->isAssigned($a_user_id, $role['rol_id']);

				break;
			}
		}

		return $result;
	}

	public static function getLocalAdminRoleTemplateId()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		// try reading permission template for local admin role
		$res = $ilDB->queryf('SELECT obj_id FROM object_data WHERE type = %s AND title = %s',
			array('text', 'text'), array('rolt', 'il_xavc_admin')
		);

		$admin_rolt_id = 0;
		while($row = $ilDB->fetchObject($res))
		{
			$admin_rolt_id = $row->obj_id;
			break;
		}

		if(!$admin_rolt_id)
		{
			$admin_rolt_id = self::initLocalAdminRoleTemplate();
		}

		return $admin_rolt_id;
	}

	public static function getLocalMemberRoleTemplateId()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		// try reading permission template for local admin role
		$res = $ilDB->queryf('SELECT obj_id FROM object_data WHERE type = %s AND title = %s',
			array('text', 'text'), array('rolt', 'il_xavc_member')
		);

		$participant_rolt_id = 0;
		while($row = $ilDB->fetchObject($res))
		{
			$participant_rolt_id = $row->obj_id;
			break;
		}

		if(!$participant_rolt_id)
		{
			$participant_rolt_id = self::initLocalMemberRoleTemplate();
		}

		return $participant_rolt_id;
	}


	private static function initLocalAdminRoleTemplate()
	{
		/**
		 * @var $ilDB ilDB
		 */

		$xavc_typ_id = self::checkObjectOperationPermissionsInitialized();

		global $ilDB;

		$admin_rolt_id = 0;

		$res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
			array('text', 'text'), array('rolt', 'il_xavc_admin'));

		while($row = $ilDB->fetchObject($res))
		{
			$admin_rolt_id = $row->obj_id;
			# break;
		}

		if((int)$admin_rolt_id >= 0)
		{
			/**
			 * @var $rbacadmin ilRbacAdmin
			 */
			global $rbacadmin;

			// create local admin role template
			$admin_rolt_id = $ilDB->nextId('object_data');

			$ilDB->manipulateF("INSERT INTO object_data (obj_id, type, title, description, owner, create_date, last_update) VALUES (%s, %s, %s, %s, %s, %s, %s)",
				array('integer', 'text', 'text', 'text', 'integer', 'timestamp', 'timestamp'),
				array($admin_rolt_id, 'rolt', 'il_xavc_admin', 'Administrator role template for Adobe Connect Interface Object', -1, ilUtil::now(), ilUtil::now())
			);

			// link permissions assignable on object's role folder
			$rolf_typ_id = 0;
			$res         = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
				array('text', 'text'), array('typ', 'rolf'));

			while($row = $ilDB->fetchObject($res))
			{
				$rolf_typ_id = (int)$row->obj_id;
				break;
			}
			$xavc_rolf_ops = array();
			$res           = $ilDB->queryF("SELECT ops_id FROM rbac_ta WHERE typ_id = %s",
				array('integer'), array($rolf_typ_id));

			while($row = $ilDB->fetchObject($res))
			{
				$xavc_rolf_ops[] = (int)$row->ops_id;
			}
			if(!count($xavc_rolf_ops)) throw new Exception('empty array $xavc_rolf_ops');
			$rbacadmin->setRolePermission($admin_rolt_id, 'rolf', $xavc_rolf_ops, ROLE_FOLDER_ID);

			// link permissions assignable on object itself
			$xavc_obj_ops = array();
			$res          = $ilDB->queryF("SELECT ops_id FROM rbac_ta WHERE typ_id = %s",
				array('integer'), array($xavc_typ_id));
			while($row = $ilDB->fetchObject($res))
			{
				$xavc_obj_ops[] = (int)$row->ops_id;
			}
			if(!count($xavc_obj_ops)) throw new Exception('empty array$xavc_obj_ops');
			$rbacadmin->setRolePermission($admin_rolt_id, 'xavc', $xavc_obj_ops, ROLE_FOLDER_ID);

			// assign local admin role template to (global?) role folder
			$rbacadmin->assignRoleToFolder($admin_rolt_id, ROLE_FOLDER_ID, 'n');
		}

		return $admin_rolt_id;
	}

	private static function initLocalMemberRoleTemplate()
	{
		// checks for surely initialized extra permissions for xavc
		// (and also returns obj_id of xavc type definition)
		$xavc_typ_id = self::checkObjectOperationPermissionsInitialized();
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$member_rolt_id = 0;

		$res = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
			array('text', 'text'), array('rolt', 'il_xavc_member'));

		while($row = $ilDB->fetchObject($res))
		{
			$member_rolt_id = (int)$row->obj_id;
			break;
		}

		if(!$member_rolt_id)
		{
			/**
			 * @var $rbacadmin ilRbacAdmin
			 * */
			global $rbacadmin;

			// create local member role template
			$member_rolt_id = $ilDB->nextId('object_data');

			$ilDB->manipulateF("INSERT INTO object_data (obj_id, type, title, description, owner, create_date, last_update) VALUES (%s, %s, %s, %s, %s, %s, %s)",
				array('integer', 'text', 'text', 'text', 'integer', 'timestamp', 'timestamp'),
				array($member_rolt_id, 'rolt', 'il_xavc_member', 'Member role template for Adobe Connect Interface Object', -1, ilUtil::now(), ilUtil::now())
			);

			// link permissions assignable on object
			$xavc_obj_ops = array();
			$res          = $ilDB->queryF("SELECT rbac_ta.ops_id, rbac_operations.operation FROM rbac_ta LEFT JOIN rbac_operations ON rbac_ta.ops_id = rbac_operations.ops_id " .
				"WHERE rbac_ta.typ_id = %s AND rbac_operations.operation IN(%s,%s,%s)",
				array('integer', 'text', 'text', 'text'),
				array($xavc_typ_id, 'visible', 'read', 'member'));

			while($row = $ilDB->fetchObject($res))
			{
				$xavc_obj_ops[] = (int)$row->ops_id;
			}

			if(!count($xavc_obj_ops)) throw new Exception('empty array $xavc_obj_ops');

			$rbacadmin->setRolePermission($member_rolt_id, 'xavc', $xavc_obj_ops, ROLE_FOLDER_ID);

			// assign local member role template to (global?) role folder
			$rbacadmin->assignRoleToFolder($member_rolt_id, ROLE_FOLDER_ID, 'n');
		}

		return $member_rolt_id;
	}

	private static function checkObjectOperationPermissionsInitialized()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		// lookup obj_id of xavc type definition
		$xavc_typ_id = 0;
		$res         = $ilDB->queryF("SELECT obj_id FROM object_data WHERE type = %s AND title = %s",
			array('text', 'text'), array('typ', 'xavc'));
		while($row = $ilDB->fetchObject($res))
		{
			$xavc_typ_id = (int)$row->obj_id;
			#break;
		}

		//check initialized permissions
		$check = $ilDB->queryF('SELECT ops_id FROM rbac_ta WHERE typ_id = %s',
			array('integer'), array($xavc_typ_id));

		$init_ops = array();
		while($row = $ilDB->fetchAssoc($check))
		{
			$init_ops[] = $row['ops_id'];
		}
		//insert or update additional permissions for object type
		// general permissions: visible, read, write, delete, copy
		$xavc_ops_ids = array();
		$res_1        = $ilDB->queryF('
				SELECT ops_id, operation FROM rbac_operations
				WHERE class = %s
				AND (operation = %s
				OR operation = %s
				OR operation = %s
				OR operation = %s
				OR operation = %s)',
			array('text', 'text', 'text', 'text', 'text', 'text'),
			array('general', 'visible', 'read', 'write', 'delete', 'copy'));

		while($row_1 = $ilDB->fetchAssoc($res_1))
		{
			$xavc_ops_ids[$row_1['operation']] = (int)$row_1['ops_id'];
		}

		foreach($xavc_ops_ids as $x_operation => $x_id)
		{
			if(!in_array($x_id, $init_ops))
			{
				//insert missing operation
				$ilDB->insert('rbac_ta',
					array(
						'typ_id' => array('integer', $xavc_typ_id),
						'ops_id' => array('integer', $x_id)
					));
			}
		}
		return $xavc_typ_id;
	}
}
