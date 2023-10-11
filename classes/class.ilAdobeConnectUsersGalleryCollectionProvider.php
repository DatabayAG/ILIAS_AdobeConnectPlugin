<?php

include_once './Services/User/Gallery/classes/class.ilAbstractUsersGalleryCollectionProvider.php';
include_once './Services/User/classes/class.ilUserUtil.php';

class ilAdobeConnectUsersGalleryCollectionProvider extends ilAbstractUsersGalleryCollectionProvider
{
    /**
     * @var ilParticipants
     */
    protected $participants;
    
    protected array $users = array();
    
    public function __construct(ilParticipants $participants)
    {
        $this->participants = $participants;
    }
    
    public function getGroupedCollections(): array
    {
        $groups = [];
        $admins = $this->participants->getAdmins();
        $tutors = $this->participants->getTutors();
        $members = $this->participants->getMembers();
        
        $unique_users = array_unique(array_merge($admins, $tutors, $members));
        
        foreach ([array($unique_users, false, '')] as $users) {
            $group = $this->getPopulatedGroup($this->getUsers($users[0]));
            $group->setHighlighted($users[1]);
            $group->setLabel($users[2]);
            $groups[] = $group;
        }
        
        return $groups;
    }
    
    public function getUsers(): array
    {
        $this->pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
        
        $this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
        $xavcRoles = new ilAdobeConnectRoles($_GET['ref_id']);
        $members = $xavcRoles->getUsers();
        $users = [];
        // MEMBERS
        if (count($members)) {
            foreach ($members as $member_id) {
                if (!($usr_obj = ilObjectFactory::getInstanceByObjId($member_id, false))) {
                    continue;
                }
                
                if (!$usr_obj->getActive()) {
                    continue;
                }
                
                $users[$usr_obj->getId()] = $usr_obj;
                $this->users[$usr_obj->getId()] = true;
                
            }
        }
        return $users;
    }
    
}