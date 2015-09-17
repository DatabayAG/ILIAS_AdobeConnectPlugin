<?php

include_once './Services/User/classes/class.ilAbstractGalleryUsers.php';
include_once './Services/User/classes/class.ilUserUtil.php';
 
/**
 * ilAdobeConnectGalleryUsers
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */ 
class ilAdobeConnectGalleryUsers extends ilAbstractGalleryUsers
{
	/**
	 * @return mixed
	 */
	public function getGalleryUsers()
	{
		$this->pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
		
		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$xavcRoles = new ilAdobeConnectRoles($_GET['ref_id']);
		$members = $xavcRoles->getUsers();

		// MEMBERS
		if(count($members))
		{
			foreach($members as $member_id)
			{
				if(!($usr_obj = ilObjectFactory::getInstanceByObjId($member_id, false)))
				{
					continue;
				}

				if(!$usr_obj->getActive())
				{
					continue;
				}

				$user_data[$usr_obj->getId()] = array(
					'id'   => $usr_obj->getId(),
					'user' => $usr_obj
				);
			}
		}
		return $user_data;
	}

	/**
	 * @return string
	 */
	public function getUserCssClass()
	{
		return 'ilBuddySystemRemoveWhenUnlinked';
	}
}