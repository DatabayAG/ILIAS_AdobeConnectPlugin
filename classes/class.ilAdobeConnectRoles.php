<?php

/**
*
* Class ilAdobeConnectRoles
*
* Local Roles : Administrator, Member
*
* @author Nadia Ahmad <nahmad@databay.de>
*
* @version $Id:$
* 
*/

class ilAdobeConnectRoles
{
	private $db = null;
	private $ref_id = 0;
	
	public function __construct($a_ref_id)
	{
		global $ilDB;
		
		$this->db = $ilDB;
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
		/**
		 * @var $rbacreview ilRbacReview
		 * @var $rbacadmin 	ilRbacAdmin
		 * @var $lng 		$lng
		 */
		global $rbacreview, $rbacadmin, $lng;
		
		$role_folder_id = $rbacreview->getRoleFolderIdOfObject($this->getRefId());
		if(!$role_folder_id)
		{
			ilUtil::sendFailure($lng->txt('missing_rolefolder'));
			return false;
		}

		$role_list = $rbacreview->getRoleListByObject($role_folder_id);
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
		/**
		 * @var $rbacreview ilRbacReview
		 * @var $rbacadmin ilRbacAdmin
		 */		
		global $rbacreview, $rbacadmin;
		
		$role_folder_id = $rbacreview->getRoleFolderIdOfObject($this->getRefId());
		$role_list = $rbacreview->getRoleListByObject($role_folder_id);
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
		/**
		 * @var $rbacreview ilRbacReview
		 *
		 */
		global $rbacreview;

		$role_folder = $rbacreview->getRoleFolderOfObject($this->getRefId());
		$roles = $rbacreview->getRoleListByObject($role_folder['child']);
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
		/**
		 * @var $rbacreview ilRbacReview
		 */
		
		global $rbacreview;

		$role_folder = $rbacreview->getRoleFolderOfObject($this->getRefId());
		$roles = $rbacreview->getRoleListByObject($role_folder['child']);
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
		/**
		 * @var $rbacreview ilRbacReview
		 * @var $rbacadmin ilRbacAdmin
		 */
		global $rbacreview, $rbacadmin;

		$role_folder_id = $rbacreview->getRoleFolderIdOfObject($this->getRefId());
		$role_list = $rbacreview->getRoleListByObject($role_folder_id);
		
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
		/**
		 * @var $rbacreview ilRbacReview
		 * @var $rbacadmin ilRbacAdmin
		 */
		global $rbacreview, $rbacadmin;

		$role_folder_id = $rbacreview->getRoleFolderIdOfObject($this->getRefId());
		$role_list = $rbacreview->getRoleListByObject($role_folder_id);
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
		/**
		 * @var $rbacreview ilRbacReview
		 */
		global $rbacreview;

		$role_folder = $rbacreview->getRoleFolderOfObject($this->getRefId());

		$roles = $rbacreview->getRoleListByObject($role_folder['child']);
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
		/**
		 * @var $rbacreview ilRbacReview
		 */
		global $rbacreview;

		$role_folder = $rbacreview->getRoleFolderOfObject($this->getRefId());
		$roles = $rbacreview->getRoleListByObject($role_folder['child']);
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
