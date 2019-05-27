<?php

include_once './Services/User/Gallery/classes/class.ilAbstractUsersGalleryCollectionProvider.php';
include_once './Services/User/classes/class.ilUserUtil.php';
 
/**
 * ilAdobeConnectUsersGalleryCollectionProvider
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */ 
class ilAdobeConnectUsersGalleryCollectionProvider extends ilAbstractUsersGalleryCollectionProvider
{
	/** @var ilObjAdobeConnect */
	protected $object;

	/** @var int */
	protected $numTotalUsers = 0;

	/** @var int */
	protected $numValidIterations = 0;

	/**
	 * ilAdobeConnectUsersGalleryCollectionProvider constructor.
	 * @param ilObjAdobeConnect $object
	 */
	public function __construct(ilObjAdobeConnect $object)
	{
		$this->object = $object;
	}

	/**
	 * @return array|ilUsersGalleryUserCollection[]
	 */
	public function getGroupedCollections()
	{
		$groups = [];

		$group = $this->getPopulatedGroup($this->getUsers((int) $this->object->getRefId()));
		$group->setHighlighted(false);
		$groups[] = $group;

		return $groups;
	}

	/**
	 * @param int $refId
	 * @return array
	 */
	public function getUsers(int $refId) : array 
	{
		$this->pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$xavcRoles = new ilAdobeConnectRoles($refId);
		$members = $xavcRoles->getUsers();

		$users = [];

		foreach ($members as $member_id) {
			if (!($usr_obj = ilObjectFactory::getInstanceByObjId($member_id, false))) {
				continue;
			}

			if (!$usr_obj->getActive()) {
				continue;
			}

			if (null === $this->offset || $this->numValidIterations >= $this->offset) {
				if (null !== $this->limit && $this->numTotalUsers >= $this->limit) {
					break;
				}

				$users[$usr_obj->getId()] = $usr_obj;
				++$this->numTotalUsers;
			}

			++$this->numValidIterations;
		}

		return $users;
	}
	
	/**
	 * @return string
	 */
	public function getUserCssClass()
	{
		return 'ilBuddySystemRemoveWhenUnlinked';
	}
	
}