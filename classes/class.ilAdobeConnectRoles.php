<?php

/**
* Class ilAdobeConnectRoles
* Local Roles : Administrator, Member
 * 
* @author Nadia Matuschek <nmatuschek@databay.de>
*/
class ilAdobeConnectRoles
{
	private $db = null;
	private $ref_id = 0;
	
	public function __construct($a_ref_id)
	{
		global $DIC;
		
		$this->db = $DIC->database();
		$this->ref_id = $a_ref_id;
	}

	// Setter/Getter
	public function setRefId($a_ref_id)
	{
		$this->ref_id = $a_ref_id;
	}
	public function getRefId()
	{
		return $this->ref_id;
	}

	// Local Administrator Role
	public function addAdministratorRole($a_usr_id)
	{
		global $DIC;
		
		$rbacreview = $DIC->rbac()->review();
		$rbacadmin = $DIC->rbac()->admin(); 
		$lng= $DIC->language();
		
		$role_list = $rbacreview->getRoleListByObject($this->getRefId());
		if(!$role_list)
		{
			ilUtil::sendFailure($lng->txt('missing_rolelist'));
			return false;

		}
		$a_rol_id = null;
		foreach ($role_list as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$a_rol_id = $role['obj_id'];
				break;
			}
		}
		
		if((int)$a_rol_id)
		{		
			$rbacadmin->assignUser($a_rol_id, $a_usr_id);
			return true;
		}
		else
		return false;	
	}
	
	public function detachAdministratorRole($a_usr_id)
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();
		$rbacadmin = $DIC->rbac()->admin();
		
		$role_list = $rbacreview->getRoleListByObject($this->getRefId());
		$a_rol_id = null;
		
		foreach ($role_list as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$a_rol_id = $role['obj_id'];
				break;
			}
		}
		
		if((int)$a_rol_id)
		{		
			$rbacadmin->deassignUser($a_rol_id, $a_usr_id);
			return true;
		}		
		return false;
	}
	
	public function isAdministrator($a_user_id)
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();

		$roles = $rbacreview->getRoleListByObject($this->getRefId());
		$assigned_users = null;
		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$assigned_users = $rbacreview->assignedUsers($role['rol_id']);
				break;
			}
		}
		if(in_array($a_user_id,$assigned_users))		
			return true;
		else return false;
	}
	
	public function getCurrentAdministrators()
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();

		$roles = $rbacreview->getRoleListByObject($this->getRefId());
		$assigned_users = array();
		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$assigned_users = $rbacreview->assignedUsers($role['rol_id']);
				break;
			}
		}

		$admins = array();
		foreach($assigned_users as $user)
		{
			$admins[] = ilObjUser::_lookupName($user);
		}
		return $admins;
	}

	// Local Member-Role
	public function addMemberRole($a_usr_id)
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();
		$rbacadmin = $DIC->rbac()->admin();

		$role_list = $rbacreview->getRoleListByObject($this->getRefId());
		
		$a_rol_id = null;
		
		foreach ($role_list as $role)
		{
			if(strpos($role['title'], 'il_xavc_member') !== false)
			{
				$a_rol_id = $role['obj_id'];
				break;
			}
		}

		if((int)$a_rol_id)
		{
			$rbacadmin->assignUser($a_rol_id, $a_usr_id);
			return true;
		}
		return false;
	}

	public function detachMemberRole($a_usr_id)
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();
		$rbacadmin = $DIC->rbac()->admin();

		$role_list = $rbacreview->getRoleListByObject($this->getRefId());
		$a_rol_id = null;
		foreach ($role_list as $role)
		{
			if(strpos($role['title'], 'il_xavc_member') !== false)
			{
				$a_rol_id = $role['obj_id'];
				break;
			}
		}

		if((int)$a_rol_id)
		{
			$rbacadmin->deassignUser($a_rol_id, $a_usr_id);
			return true;
		}

		return false;
	}

	public function getCurrentMembers()
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();

		$roles = $rbacreview->getRoleListByObject($this->getRefId());
		$assigned_users = array();
		
		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_member') !== false)
			{
				$assigned_users = $rbacreview->assignedUsers($role['rol_id']);
				break;
			}
		}
		$members = array();
		foreach($assigned_users as $user)
		{
			$members[] = ilObjUser::_lookupName($user);
		}
		return $members;
	}

	public function getUsers()
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();

		$roles = $rbacreview->getRoleListByObject($this->getRefId());
		$admins = array();
		$members = array();
		foreach($roles as $role)
		{
			if(strpos($role['title'], 'il_xavc_admin') !== false)
			{
				$admins = $rbacreview->assignedUsers($role['rol_id']);
				
			}
			if(strpos($role['title'], 'il_xavc_member') !== false)
			{
				$members = $rbacreview->assignedUsers($role['rol_id']);
			}
		}
		$assigned_users = array_unique(array_merge($admins, $members));
		return $assigned_users;
	}
	
}
