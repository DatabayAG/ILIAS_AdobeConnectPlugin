<?php

class ilAdobeConnectUsersGalleryCollectionProvider extends ilAbstractUsersGalleryCollectionProvider
{
    /**
     * @var ilParticipants
     */
    protected $participants;

    protected array $users = [];

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

        foreach ([[$unique_users, false, '']] as $users) {
            $group = $this->getPopulatedGroup($this->getUsers($users[0]));
            $group->setHighlighted($users[1]);
            $group->setLabel($users[2]);
            $groups[] = $group;
        }

        return $groups;
    }

    public function getUsers(): array
    {
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
