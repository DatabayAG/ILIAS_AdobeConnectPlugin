<?php

include_once('./Services/Membership/classes/class.ilParticipants.php');
require_once 'Services/User/Gallery/classes/class.ilAbstractUsersGalleryCollectionProvider.php';

/**
 * Class ilAdobeConnectContainerParticipants
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilAdobeConnectContainerParticipants extends ilParticipants
{
	/**
	 * @param int $a_obj_id
	 * @return ilParticipants|mixed|null|object
	 */
	public static function getInstanceByObjId($a_obj_id)
	{
		$type = ilObject::_lookupType($a_obj_id);
		
		switch($type)
		{
			case 'crs':
				include_once './Modules/Course/classes/class.ilCourseParticipants.php';
				return ilCourseParticipants::_getInstanceByObjId($a_obj_id);
			
			case 'grp':
				include_once './Modules/Group/classes/class.ilGroupParticipants.php';
				return ilGroupParticipants::_getInstanceByObjId($a_obj_id);
			
			case 'sess':
				include_once './Modules/Session/classes/class.ilSessionParticipants.php';
				return ilSessionParticipants::_getInstanceByObjId($a_obj_id);
			
			default:
				include_once 'class.ilAdobeConnectParticipantsNullObject.php';
				return new ilAdobeConnectParticipantsNullObject();
		}
	}
}