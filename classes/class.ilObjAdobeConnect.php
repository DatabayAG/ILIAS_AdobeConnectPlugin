<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class ilObjAdobeConnect extends ilObjectPlugin
{
    use ilAdobeConnectRequestTrait;

    public const ACCESS_LEVEL_PRIVATE = 'denied';  // no guests !
    public const ACCESS_LEVEL_PROTECTED = 'remove';
    public const ACCESS_LEVEL_PUBLIC = 'view-hidden';

    public const RBAC_DEFAULT_ROLE_ID = 4;
    public const RBAC_GUEST_ROLE_ID = 5;
    private int $sco_id = 0;
    private ?ilDateTime $start_date;
    private array $duration = [];
    private ?string $instructions = null;
    private $contact_info = null;
    private int $permanent_room = 0;
    private string $access_level = self::ACCESS_LEVEL_PROTECTED;
    private int $read_contents = 0;
    private int $read_records = 0;
    private int $folder_id = 0;
    private ?string $url;
    private ?ilAdobeConnectContents $contents;
    private string $adminLogin;
    private string $adminPass;
    private ilCtrlInterface $ctrl;
    public ?string $externalLogin;
    /**
     * @var ilAdobeConnectDfnXMLAPI|ilAdobeConnectXMLAPI
     */
    public $xmlApi;
    private $permission;
    public $assignment_mode = null;
    public ?ilDateTime $end_date = null;

    public $pluginObj = null;
    public $participants = null;
    public bool $use_meeting_template = false;

    public string $ac_language = 'de';
    public bool $html_client = false;
    /**
     * @var ilXAVCTemplates[]
     */
    public array $xavc_templates = [];

    public function __construct($a_ref_id = 0)
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tree = $DIC->repositoryTree();
        $this->user = $DIC->user();
        $this->db = $DIC->database();

        parent::__construct($a_ref_id);
        $this->ref_id = $a_ref_id;
        $this->pluginObj = ilAdobeConnectPlugin::getInstance();

        if (!$this->ctrl->isAsynch()) {
            $this->contents = new ilAdobeConnectContents();
        }

        $instance = ilAdobeConnectServer::_getInstance();
        $this->adminLogin = $instance->getLogin();
        $this->adminPass = $instance->getPasswd();
        $this->externalLogin = $this->checkExternalUser();

        $this->xmlApi = ilXMLApiFactory::getApiByAuthMode();
    }

    private function initParticipantsObject(): void
    {
        if ($this->getRefId() > 0) {
            $parent_ref = $this->tree->checkForParentType($this->getRefId(), 'grp');
            if (!$parent_ref) {
                $parent_ref = $this->tree->checkForParentType($this->getRefId(), 'crs');
            }

            $object_id = ilObject::_lookupObjectId($parent_ref);
            $this->participants = ilAdobeConnectContainerParticipants::getInstanceByObjId($object_id);
        }
    }

    public function getParticipantsObject()
    {
        if (!$this->participants instanceof ilAdobeConnectContainerParticipants) {
            $this->initParticipantsObject();
        }

        return $this->participants;
    }

    public function checkExternalUser(int $user_id = 0): string
    {
        if (!(isset($user_id) && $user_id > 0)) {
            $user_id = $this->user->getId();
        }

        //check if there is a xavc-login already saved in ilias-db
        $tmp_xavc_login = (string) ilXAVCMembers::_lookupXAVCLogin($user_id);

        if (!$tmp_xavc_login) {
            $externalLogin = ilAdobeConnectUserUtil::generateXavcLoginName($user_id);
            ilXAVCMembers::addXAVCUser($user_id, $externalLogin);
        } else {
            // get saved login-data
            $externalLogin = $tmp_xavc_login;
        }
        return (string) $externalLogin;
    }

    final public function initType(): void
    {
        $this->setType("xavc");
    }

    /**
     * Rollback function for creation workflow
     */
    private function creationRollback(): void
    {
        $this->delete();
    }

    public function doCloneObject($new_obj, $a_target_id, $a_copy_id = null): void
    {
        parent::doCloneObject($new_obj, $a_target_id, $a_copy_id);
    }

    public function doCreate(bool $clone_mode = false): void
    {
        $cmdClass = $this->ctrl->getCmdClass();
        $cmd = $this->ctrl->getCmd();

        if ($cmdClass == 'ilobjectcopygui') {
            $clone_ref_id = $this->getRefId();

            $now = new ilDateTime(time(), IL_CAL_UNIX);
            $this->start_date = new ilDateTime($now->getUnixTime() - 7200, IL_CAL_UNIX);
            $this->duration = ['hours' => 1, 'minutes' => 0];

            $this->publishCreationAC();
            return;
        } else {
            if (isset($_POST['tpl_id']) && (string) $_POST['tpl_id'] > 0) {
                $tpl_id = (string) $_POST['tpl_id'];
            } else {
                throw new ilException('no_template_id_given');
            }

            foreach (ilXAVCTemplates::XAVC_TEMPLATES as $type) {
                $this->xavc_templates[$type] = ilXAVCTemplates::_getInstanceByType($type);
            }
            $template_settings = $this->xavc_templates[$tpl_id];

            // reuse existing ac-room
            if (isset($_POST['creation_type'])
                && $_POST['creation_type'] == 'existing_vc'
                && $template_settings->getReuseExistingRoomsHide() == '0') {
                // 1. the sco-id will be assigned to this new ilias object
                $sco_id = (int) $_POST['available_rooms'];
                try {
                    $this->useExistingVC($this->getId(), $sco_id);
                } catch (ilException $e) {
                    $this->creationRollback();
                    throw new ilException($this->txt($e->getMessage()));
                }
                return;
            }

            $post_instructions = $this->retrieveStringFrom(self::$REQUEST_POST, 'instructions');
            $post_instructions .= $this->retrieveStringFrom(self::$REQUEST_POST, 'instructions_2');
            $post_instructions .= $this->retrieveStringFrom(self::$REQUEST_POST, 'instructions_3');

            $post_contact = $this->retrieveStringFrom(self::$REQUEST_POST, 'contact_info');
            $post_contact .= $this->retrieveStringFrom(self::$REQUEST_POST, 'contact_info_2');
            $post_contact .= $this->retrieveStringFrom(self::$REQUEST_POST, 'contact_info_3');

            $this->setInstructions($post_instructions);
            $this->setContactInfo($post_contact);
            $this->setAcLanguage($_POST['ac_language']);
            $this->setUseHtmlClient($_POST['html_client']);

            if (isset($_POST['time_type_selection']) && $_POST['time_type_selection'] == 'permanent_room') {
                $this->setPermanentRoom(1);
            } else {
                if (!isset($_POST['time_type_selection']) && ilAdobeConnectServer::getSetting(
                        'default_perm_room'
                    ) == 1) {
                    $this->setPermanentRoom(1);
                } else {
                    $this->setPermanentRoom(0);
                }
            }

            if (isset($_POST['access_level'])) {
                $this->setPermission($_POST['access_level']);
            } else {
                $this->setPermission(ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED);
            }

            $this->setReadContents(
                ilXAVCPermissions::lookupPermission(
                    AdobeConnectPermissions::PERM_READ_CONTENTS,
                    'view'
                )
            );
            $this->setReadRecords(
                ilXAVCPermissions::lookupPermission(
                    AdobeConnectPermissions::PERM_READ_RECORDS,
                    'view'
                )
            );

            $this->externalLogin = $this->checkExternalUser();

            $folder_id = $this->getFolderIdByLogin($this->externalLogin);
            $this->setFolderId($folder_id);
        }

        try {
            $start_date_string = $this->retrieveStringFrom(self::$REQUEST_POST, 'start_date');
            $start_date_array = $this->retrieveListOfStringFrom(self::$REQUEST_POST, 'start_date');

            if ($start_date_string != '' && $template_settings->getStartDateHide() == '0') {
                $this->start_date = new ilDateTime(strtotime($start_date_string), IL_CAL_UNIX);
            } else {
                if (array_key_exists('date', $start_date_array)
                    && array_key_exists('time', $start_date_array)
                    && $template_settings->getStartDateHide() == '0') {
                    $this->start_date = new ilDateTime(
                        $start_date_array['date'] . ' ' . $start_date_array['time'],
                        IL_CAL_DATETIME
                    );
                } else {
                    $this->start_date = new ilDateTime(time() + 120, IL_CAL_UNIX);
                }
            }

            // duration
            if (isset($_POST['duration']['hh']) && isset($_POST['duration']['mm'])
                && ($_POST['duration']['hh'] > 0 || $_POST['duration']['mm'] > 0)
                && $template_settings->getDurationHide() == '0') {
                $this->duration = [
                    'hours' => $_POST['duration']['hh'],
                    'minutes' => $_POST['duration']['mm']
                ];
            } else {
                $this->duration = ['hours' => (int) $template_settings['duration']['value'], 'minutes' => 0];
            }

            //end_date
            $this->end_date = $this->getEnddate();

            $concurrent_vc = count($this->checkConcurrentMeetingDates());
            $max_rep_obj_vc = ilAdobeConnectServer::getSetting('ac_interface_objects');
            if ((int) $max_rep_obj_vc > 0 && $concurrent_vc >= $max_rep_obj_vc) {
                throw new ilException('xavc_reached_number_of_connections');
            }

            $this->setUseMeetingTemplate($_POST['use_meeting_template'] == '1');
            $this->publishCreationAC();
        } catch (ilException $e) {
            $this->creationRollback();
            throw new ilException($this->txt($e->getMessage()));
        }
    }

    public function useExistingVC($obj_id, $sco_id): void
    {
        // receive breeze session
        $session = $this->xmlApi->getBreezeSession();
        if (!$session) {
            throw new ilException('xavc_connection_error');
        }

        // access check
        if (!$this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            throw new ilException('xavc_authentication_error');
        }

        // receive folder id
        $this->externalLogin = $this->checkExternalUser();
        $folder_id = $this->getFolderIdByLogin($this->externalLogin);

        if (!$folder_id) {
            throw new ilException('xavc_folder_not_available');
        }

        if (!$sco_id) {
            throw new ilException('xavc_meeting_creation_error');
        }

        if (!$this->xmlApi->getName($sco_id, $folder_id, $session)) {
            throw new ilException('xavc_meeting_not_available');
        }

        if ($this->externalLogin == null) {
            throw new ilException('xavc_external_login_error');
        } else {
            $this->xmlApi->addUser(
                $this->externalLogin,
                $this->user->getEmail(),
                $this->user->getPasswd(),
                $this->user->getFirstName(),
                $this->user->getLastName(),
                $session
            );
        }

        $this->xmlApi->updateMeetingParticipant($sco_id, $this->externalLogin, $session, 'host');

        $start_date = time();
        $end_date = strtotime('+2 hours');

        $this->db->insert(
            'rep_robj_xavc_data',
            [
                'id' => ['integer', $obj_id],
                'sco_id' => ['integer', $sco_id],
                'start_date' => ['integer', $start_date],
                'end_date' => ['integer', $end_date],
                'folder_id' => ['integer', $folder_id]
            ]
        );
    }

    /**
     * @throws ilException
     */
    protected function publishCreationAC(): void
    {
        $obj_id = $this->getId();
        $title = $this->getTitle();
        $description = $this->getDescription();
        $start_date = $this->getStartDate();
        $end_date = $this->getEnddate();
        $instructions = $this->getInstructions();
        $contact_info = $this->getContactInfo();
        $permanent_room = $this->getPermanentRoom();
        $access_level = $this->getPermission() ? $this->getPermission() : self::ACCESS_LEVEL_PROTECTED;
        $read_contents = $this->getReadContents();
        $read_records = $this->getReadRecords();
        $folder_id = $this->getFolderId();
        $lang = $this->getAcLanguage();
        $html_client = $this->isHtmlClientEnabled();

        $owner_id = ilObject::_lookupOwner($obj_id);
        $ownerObj = new ilObjUser($owner_id);

        // receive breeze session
        $session = $this->xmlApi->getBreezeSession();
        if (!$session) {
            throw new ilException('xavc_connection_error');
        }

        // access check
        if (!$this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            throw new ilException('xavc_authentication_error');
        }

        // receive folder id
        $this->externalLogin = $this->checkExternalUser($ownerObj->getId());

        $folder_id = $this->getFolderIdByLogin($this->externalLogin);

        if (!$folder_id) {
            throw new ilException('xavc_folder_not_available');
        }

        $obj_title_suffix_enabled = ilAdobeConnectServer::getSetting('obj_title_suffix');
        if ($obj_title_suffix_enabled) {
            $title = $title . '_' . CLIENT_ID . '_' . $obj_id;
        }

        $source_sco_id = 0;
        if ($this->isUseMeetingTemplate()) {
            $source_sco_id = ilAdobeConnectServer::getSetting('template_sco_id');
        }

        // create meeting room
        $arr_meeting = $this->xmlApi->addMeeting(
            $title,
            $description,
            date('Y-m-d', $start_date->getUnixTime()),
            date('H:i', $start_date->getUnixTime()),
            date('Y-m-d', $end_date->getUnixTime()),
            date('H:i', $end_date->getUnixTime()),
            $folder_id,
            $session,
            $source_sco_id,
            $lang,
            $html_client
        );

        $meeting_id = $arr_meeting['meeting_id'];
        $meeting_url = $arr_meeting['meeting_url'];

        if (!$meeting_id) {
            throw new ilException('xavc_meeting_creation_error');
        }

        if ($this->externalLogin == null) {
            throw new ilException('xavc_external_login_error');
        } else {
            $this->xmlApi->addUser(
                $this->externalLogin,
                $ownerObj->getEmail(),
                $ownerObj->getPasswd(),
                $ownerObj->getFirstname(),
                $ownerObj->getLastname(),
                $session
            );
        }
        $this->xmlApi->updateMeetingParticipant($meeting_id, $this->externalLogin, $session, 'host');

        $this->xmlApi->updatePermission($meeting_id, $session, $access_level);

        $this->db->insert(
            'rep_robj_xavc_data',
            [
                'id' => ['integer', $obj_id],
                'sco_id' => ['integer', $meeting_id],
                'start_date' => ['integer', $start_date->getUnixTime()],
                'end_date' => ['integer', $end_date->getUnixTime()],
                'instructions' => ['text', $instructions],
                'contact_info' => ['text', $contact_info],
                'permanent_room' => ['integer', (int) $permanent_room],
                'perm_read_contents' => ['integer', (int) $this->getReadContents()],
                'perm_read_records' => ['integer', (int) $this->getReadRecords()],
                'folder_id' => ['integer', $folder_id],
                'url_path' => ['text', $meeting_url],
                'language' => ['text', $this->getAcLanguage()],
                'html_client' => ['integer', $this->isHtmlClientEnabled()]
            ]
        );
    }

    /**
     * @param int        $ref_id ref_id of ilias ac-object
     * @param int        $sco_id
     * @param array|null $member_ids
     */
    public function addCrsGrpMembers(int $ref_id, int $sco_id, array $member_ids = null): void
    {
        $oParticipants = $this->getParticipantsObject();
        if (count($oParticipants->getParticipants()) == 0) {
            return;
        }

        $role_map = ilAdobeConnectServer::getRoleMap();

        /** @var $oParticipants  ilGroupParticipants | ilCourseParticipants */
        $admins = $oParticipants->getAdmins();
        $tutors = $oParticipants->getTutors();
        $members = $oParticipants->getMembers();

        if (is_array($member_ids) && count($member_ids) > 0) {
            $all_participants = $member_ids;

            $admins = array_uintersect($member_ids, $admins, 'strcmp');
            $tutors = array_uintersect($member_ids, $tutors, 'strcmp');
            $members = array_uintersect($member_ids, $members, 'strcmp');
        } else {
            $all_participants = array_unique(array_merge($admins, $tutors, $members));
        }

        $xavcRoles = new ilAdobeConnectRoles($ref_id);

        foreach ($all_participants as $user_id) {
            //check if there is an adobe connect account at the ac-server
            $ilAdobeConnectUser = new ilAdobeConnectUserUtil((int) $user_id);
            $ilAdobeConnectUser->ensureAccountExistence();

            // add to desktop
            if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
                self::addToFavourites((int) $user_id, (int) $ref_id);
            }
        }

        // receive breeze session
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            foreach ($admins as $user_id) {
                if ($user_id == $this->getOwner()) {
                    continue;
                }

                $xavcRoles->addAdministratorRole($user_id);

                $is_member = ilXAVCMembers::_isMember($user_id, $ref_id);
                // local member table
                $xavcMemberObj = new ilXAVCMembers($ref_id, $user_id);

                $status = $role_map[$oParticipants->getType() . '_admin'];

                $xavcMemberObj->setStatus($status);
                $xavcMemberObj->setScoId($sco_id);

                if ($is_member) {
                    $xavcMemberObj->updateXAVCMember();
                } else {
                    $xavcMemberObj->insertXAVCMember();
                }

                $this->xmlApi->updateMeetingParticipant(
                    $sco_id,
                    ilXAVCMembers::_lookupXAVCLogin((int) $user_id),
                    $session,
                    $status
                );
            }

            foreach ($tutors as $user_id) {
                if ($user_id == $this->getOwner()) {
                    continue;
                }

                $xavcRoles->addAdministratorRole($user_id);

                $is_member = ilXAVCMembers::_isMember($user_id, $ref_id);
                // local member table
                $xavcMemberObj = new ilXAVCMembers($ref_id, $user_id);

                $status = $role_map[$oParticipants->getType() . '_tutor'];

                $xavcMemberObj->setStatus($status);
                $xavcMemberObj->setScoId($sco_id);

                if ($is_member) {
                    $xavcMemberObj->updateXAVCMember();
                } else {
                    $xavcMemberObj->insertXAVCMember();
                }

                $this->xmlApi->updateMeetingParticipant(
                    $sco_id,
                    ilXAVCMembers::_lookupXAVCLogin((int) $user_id),
                    $session,
                    $status
                );
            }

            foreach ($members as $user_id) {
                if ($user_id == $this->getOwner()) {
                    continue;
                }

                $xavcRoles->addMemberRole($user_id);
                $is_member = ilXAVCMembers::_isMember($user_id, $ref_id);
                // local member table
                $xavcMemberObj = new ilXAVCMembers($ref_id, $user_id);

                $status = $role_map[$oParticipants->getType() . '_member'];

                $xavcMemberObj->setStatus($status);
                $xavcMemberObj->setScoId($sco_id);

                if ($is_member) {
                    $xavcMemberObj->updateXAVCMember();
                } else {
                    $xavcMemberObj->insertXAVCMember();
                }

                $this->xmlApi->updateMeetingParticipant(
                    $sco_id,
                    ilXAVCMembers::_lookupXAVCLogin((int) $user_id),
                    $session,
                    $status
                );
            }

            $owner_id = ilObject::_lookupOwner($oParticipants->getObjId());

            $xavcRoles->addAdministratorRole($owner_id);

            $is_member = ilXAVCMembers::_isMember($owner_id, $ref_id);
            // local member table
            $xavcMemberObj = new ilXAVCMembers($ref_id, $owner_id);

            $status = $role_map[$oParticipants->getType() . '_owner'];
            $xavcMemberObj->setStatus($status);

            $xavcMemberObj->setScoId($sco_id);

            if ($is_member) {
                $xavcMemberObj->updateXAVCMember();
            } else {
                $xavcMemberObj->insertXAVCMember();
            }

            $this->xmlApi->updateMeetingParticipant(
                $sco_id,
                ilXAVCMembers::_lookupXAVCLogin((int) $owner_id),
                $session,
                $status
            );
        }
    }

    public function deleteCrsGrpMembers($sco_id, $delete_user_ids): void
    {
        $xavcRoles = new ilAdobeConnectRoles($this->getRefId());

        if (is_array($delete_user_ids) && count($delete_user_ids) > 0) {
            foreach ($delete_user_ids as $usr_id) {
                $xavcRoles->detachMemberRole((int) $usr_id);

                ilXAVCMembers::deleteXAVCMember((int) $usr_id, $this->getRefId());
                $xavc_login = ilXAVCMembers::_lookupXAVCLogin((int) $usr_id);

                $session = $this->xmlApi->getBreezeSession();

                if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
                    $this->xmlApi->deleteMeetingParticipant($sco_id, $xavc_login, $session);
                }

                //remove from pd
                self::removeFromFavourites((int) $usr_id, $this->getRefId());
            }
        }
    }

    /**
     * Read data from db and from Adobe Connect server
     */
    public function doRead(): void
    {
        if ($this->ctrl->isAsynch()) {
            return;
        }

        $set = $this->db->query(
            "SELECT * FROM rep_robj_xavc_data " .
            " WHERE id = " . $this->db->quote($this->getId(), "integer")
        );

        while ($rec = $this->db->fetchAssoc($set)) {
            $this->sco_id = $rec["sco_id"];
            $this->instructions = $rec['instructions'];
            $this->contact_info = $rec['contact_info'];
            $this->permanent_room = $rec['permanent_room'];
            $this->read_contents = $rec['perm_read_contents'];
            $this->read_records = $rec['perm_read_records'];
            $this->folder_id = $rec['folder_id'];
            $this->url = (string) $rec['url_path'];
            $this->ac_language = $rec['language'];
            $this->html_client = $rec['html_client'];
        }

        if ($this->sco_id == null) {
            #$this->ilias->raiseError($this->lng->txt("err_no_valid_sco_id_given"),$this->ilias->error_obj->MESSAGE);
        }

        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            //only read url via api, if url in database is empty
            if (!$this->url) {
                $this->url = substr($this->xmlApi->getURL($this->sco_id, $this->folder_id, $session), 0, -1);
            }

            $date_begin_string = $this->xmlApi->getStartDate($this->sco_id, $this->folder_id, $session);
            if ($date_begin_string != '' && $date_begin_string != null) {
                $date_begin_string = strtotime($date_begin_string);
            }

            $this->start_date = new ilDateTime($date_begin_string, IL_CAL_UNIX);

            $date_end_string = $this->xmlApi->getEndDate($this->sco_id, $this->folder_id, $session);
            if ($date_end_string != '' && $date_end_string != null) {
                $date_end_string = strtotime($date_end_string);
            }

            $this->end_date = new ilDateTime($date_end_string, IL_CAL_UNIX);
            $unix_duration = $this->end_date->getUnixTime() - $this->start_date->getUnixTime();

            $hours = floor($unix_duration / 3600);
            $minutes = floor(($unix_duration - $hours * 3600) / 60);
            $this->duration = ["hours" => $hours, "minutes" => $minutes];

            $this->contents = new ilAdobeConnectContents();

            $this->access_level = $this->xmlApi->getPermissionId($this->sco_id, $session);
        }
        $this->initParticipantsObject();
    }

    /**
     * Update data
     */
    public function doUpdate(): void
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $end_date = new ilDateTime(
                $this->start_date->getUnixTime() + $this->duration["hours"] * 3600 + $this->duration["minutes"] * 60,
                IL_CAL_UNIX
            );
            $this->xmlApi->updateMeeting(
                $this->sco_id,
                $this->getTitle(),
                $this->getDescription(),
                date('Y-m-d', $this->start_date->getUnixTime()),
                date('H:i', $this->start_date->getUnixTime()),
                date('Y-m-d', $end_date->getUnixTime()),
                date('H:i', $end_date->getUnixTime()),
                $session,
                $this->getAcLanguage(),
                $this->isHtmlClientEnabled()
            );

            $this->xmlApi->updatePermission($this->sco_id, $session, $this->permission);
        }

        $this->db->update(
            'rep_robj_xavc_data',
            [
                'start_date' => ['integer', $this->getStartDate()->getUnixTime()],
                'end_date' => ['integer', $this->getEndDate()->getUnixTime()],
                'instructions' => ['text', $this->getInstructions()],
                'contact_info' => ['text', $this->getContactInfo()],
                'permanent_room' => ['integer', $this->getPermanentRoom()],
                'perm_read_contents' => ['integer', $this->getReadContents()],
                'perm_read_records' => ['integer', $this->getReadRecords()],
                'language' => ['text', $this->getAcLanguage()],
                'html_client' => ['integer', $this->isHtmlClientEnabled()]
            ],
            ['sco_id' => ['integer', $this->getScoId()]]
        );
    }

    /**
     * Delete data from db and from Adobe Connect server
     */
    public function doDelete(): void
    {
        $session = $this->xmlApi->getBreezeSession();
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->deleteMeeting($this->sco_id, $session);
        }

        $this->db->manipulateF(
            'DELETE FROM rep_robj_xavc_data WHERE id = %s',
            ['integer'],
            [$this->getId()]
        );

        $this->db->manipulateF(
            'DELETE FROM rep_robj_xavc_members WHERE sco_id = %s',
            ['integer'],
            [$this->sco_id]
        );
    }

    /**
     * Do Cloning
     */
    public function doClone($a_target_id, $a_copy_id, $new_obj): void
    {
        // to avoid date-conflicts:
        // start_date = now - 2h
        // duration = 1h

        $now = new ilDateTime(time(), IL_CAL_UNIX);
        $this->start_date = new ilDateTime($now->getUnixTime() - 7200, IL_CAL_UNIX);
        //$this->start_date = new ilDateTime(0, IL_CAL_UNIX);
        $this->duration = ['hours' => 1, 'minutes' => 0];

        $new_obj->setStartDate($this->getStartDate());

        $new_obj->setInstructions($this->getInstructions());
        $new_obj->setContactInfo($this->getContactInfo());
        $new_obj->setPermanentRoom($this->getPermanentRoom());
        $new_obj->setReadContents($this->getReadContents());
        $new_obj->setReadRecords($this->getReadRecords());
        $new_obj->setDuration($this->getDuration());
        $new_obj->setURL($this->getUrl());
        $new_obj->setScoId($this->getScoId());
        $new_obj->setFolderId($this->getFolderId());
        $new_obj->setAcLanguage($this->getAcLanguage());
        $new_obj->setUseHtmlClient($this->isHtmlClientEnabled());
        $new_obj->update();

        // add xavc-member,  assign roles
        $new_obj_id = $new_obj->getId();
        $res = $this->db->queryF(
            'SELECT sco_id FROM rep_robj_xavc_data WHERE id = %s',
            ['integer'],
            [$new_obj_id]
        );

        $row = $this->db->fetchAssoc($res);
        $new_sco_id = $row['sco_id'];

        $xavcMemberObj = new ilXAVCMembers($new_obj->getRefId(), $this->user->getId());
        $xavcMemberObj->setPresenterStatus();
        $xavcMemberObj->setScoId($new_sco_id);
        $xavcMemberObj->insertXAVCMember();

        $xavc_role = new ilAdobeConnectRoles($new_obj->getRefId());
        $xavc_role->addAdministratorRole($this->user->getId());

        if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
            self::addToFavourites($this->user->getId(), $new_obj->getRefId());
        }
    }

    /**
     *  Sets meeting start date
     * @param ilDateTime $a_val
     */
    public function setStartDate(ilDateTime $a_val): void
    {
        $this->start_date = $a_val;
    }

    /**
     *  Returns meeting start date
     * @return ilDateTime
     */
    public function getStartDate(): ?ilDateTime
    {
        return $this->start_date;
    }

    /**
     *  Sets meeting contents
     * @param ilAdobeConnectContents $a_val
     */
    public function setContents($a_val): void
    {
        $this->contents = $a_val;
    }

    /**
     *  Returns meeting contents
     * @return ilAdobeConnectContents
     */
    public function getContents(): ?ilAdobeConnectContents
    {
        return $this->contents;
    }

    /**
     *  Sets meeting duration
     * @param array $a_val
     */
    public function setDuration(array $a_val): void
    {
        $this->duration = $a_val;
    }

    /**
     *  Returns meeting duration
     * @return array
     */
    public function getDuration(): array
    {
        return $this->duration;
    }

    /**
     *  Sets meeting id
     */
    public function setScoId($a_val): void
    {
        $this->sco_id = (int) $a_val;
    }

    /**
     *  Returns meeting id
     */
    public function getScoId(): int
    {
        return $this->sco_id;
    }

    /**
     *  Sets meeting URL
     */
    public function setUrl(string $a_val): void
    {
        $this->url = $a_val;
    }

    /**
     *  Returns meeting URL
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setPermission($a_permission): void
    {
        $this->permission = $a_permission;
    }

    public function getPermission()
    {
        return $this->permission;
    }

    public function setInstructions($instructions): void
    {
        $this->instructions = $instructions;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setContactInfo($contact_info): void
    {
        $this->contact_info = $contact_info;
    }

    public function getContactInfo()
    {
        return $this->contact_info;
    }

    /**
     * @param int $permanent_room
     */
    public function setPermanentRoom(int $permanent_room): void
    {
        $this->permanent_room = $permanent_room;
    }

    public function getPermanentRoom(): int
    {
        return $this->permanent_room;
    }

    public function setReadContents(int $read_contents): void
    {
        $this->read_contents = $read_contents;
    }

    public function getReadContents(): int
    {
        return $this->read_contents;
    }

    public function setReadRecords(int $read_records): void
    {
        $this->read_records = $read_records;
    }

    public function getReadRecords(): int
    {
        return $this->read_records;
    }

    public function setFolderId(int $folder_id): void
    {
        $this->folder_id = $folder_id;
    }

    public function getFolderId(): int
    {
        return $this->folder_id;
    }

    /**
     *  Returns meeting end date
     */
    public function getEndDate(): ilDateTime
    {
        $end_date = new ilDateTime($this->start_date->getUnixTime(), IL_CAL_UNIX);
        $end_date->increment(ilDateTime::HOUR, $this->duration['hours']);
        $end_date->increment(ilDateTime::MINUTE, $this->duration['minutes']);
        return $end_date;
    }

    /*
    * Contents functions
    */

    /**
     *  Reads contents from Adobe Connect server
     * @param string|null $by_type null|content|record
     * @return bool
     */
    public function readContents(string $by_type = null): bool
    {
        $session = $this->xmlApi->getBreezeSession();

        $ids = [];

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $ids = ($this->xmlApi->getContentIds($this->sco_id, $session) ? $this->xmlApi->getContentIds(
                $this->sco_id,
                $session
            ) : []);

            foreach ($ids as $id) {
                $date_created = $this->xmlApi->getDateCreated($id, $this->sco_id, $session);

                $date_end = $this->xmlApi->getDateEnd($id, $this->sco_id, $session);
                if ($date_end == '') {
                    $type = 'content';
                } else {
                    $type = 'record';
                }

                if ($by_type == null || $by_type == $type) {
                    $attributes = [
                        'sco-id' => $id,
                        'name' => $this->xmlApi->getName($id, $this->sco_id, $session),
                        'url' => $this->xmlApi->getURL($id, $this->sco_id, $session),
                        'date-created' => new ilDateTime(
                            substr($date_created, 0, 10) . " " . substr(
                                $date_created,
                                11,
                                8
                            ), IL_CAL_DATETIME
                        ),
                        'date-end' => $date_end,
                        'description' => $this->xmlApi->getDescription($id, $this->sco_id, $session),
                        'type' => $type
                    ];
                    $this->contents->addContent($attributes);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     *  Reads records from Adobe Connect server
     */
    public function readRecords(): bool
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $ids = $this->xmlApi->getRecordIds($this->sco_id, $session);
            foreach ($ids as $id) {
                $date_created = $this->xmlApi->getDateCreated($id, $this->sco_id, $session);
                $attributes_records = [
                    'sco-id' => $id,
                    'name' => $this->xmlApi->getName($id, $this->getScoId(), $session),
                    'url' => $this->xmlApi->getURL($id, $this->sco_id, $session),
                    'date-created' => new ilDateTime(
                        substr($date_created, 0, 10) . " " . substr($date_created, 11, 8),
                        IL_CAL_DATETIME
                    ),
                    'duration' => $this->xmlApi->getDuration($id, $this->sco_id, $session),
                    'description' => $this->xmlApi->getDescription($id, $this->sco_id, $session),
                    'type' => 'record'
                ];
                $this->contents->addContent($attributes_records);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     *  Returns the contents containing the search criteria
     */
    public function searchContent(array $search_criteria): array
    {
        return $this->contents->search($search_criteria);
    }

    /**
     *  Returns the content associated with the identifier
     * @param  $sco_id
     * @return ilAdobeConnectContent
     */
    public function getContent($sco_id): ilAdobeConnectContent
    {
        $contents = $this->searchContent(['sco-id' => $sco_id]);

        return $contents[0];
    }

    /**
     *  Adds a content to the Adobe Connect server
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function addContent($title = 'untitled', $description = ''): string
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->addContent($this->sco_id, $title, $description, $session);
        }

        return '';
    }

    /**
     * Updates a content on the Adobe Connect server
     * @param string $sco_id
     * @param string $title
     * @param string $description
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function updateContent($sco_id, $title, $description): void
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->updateContent($sco_id, $title, $description, $session);
        }
    }

    /**
     *  Removes a content from the Adobe Connect server
     * @param string $sco_id
     */
    public function deleteContent($sco_id): void
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->deleteContent($sco_id, $session);
        }
    }

    /**
     *  Uploads a content to the Adobe Connect server
     * @param string $sco_id
     */
    public function uploadContent($sco_id)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->uploadContent($sco_id, $session);
        }
    }

    /*
    *   Participants functions
    */

    /**
     *  Returns meeting hosts
     */
    public function getParticipants()
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->getMeetingsParticipants($this->sco_id, $session);
        } else {
            return null;
        }
    }

    /**
     *  Add a new host to the meeting
     * @param string $login
     * @return bool              Returns true if everything is ok
     */
    public function addParticipant($login): bool
    {
        $session = $this->xmlApi->getBreezeSession();

        //check if adobe connect account exists
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->addMeetingParticipant($this->sco_id, $login, $session);
        }
    }

    public function updateParticipant($login, $permission)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->updateMeetingParticipant($this->sco_id, $login, $session, $permission);
        }
    }

    /**
     *  Deletes a host from the meeting
     * @param string $login
     */
    public function deleteParticipant(string $login): void
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->deleteMeetingParticipant($this->sco_id, $login, $session);
        }
    }

    /**
     *  Check whether a user is host in this virtual classroom.
     */
    public function isParticipant($login)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->isParticipant($login, $this->sco_id, $session);
        }
    }

    public function getPermissionId(): string
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $permission = $this->xmlApi->getPermissionId($this->sco_id, $session);
        }
        return $permission;
    }

    // LOCAL ROLES FOR ILIAS
    public function initDefaultRoles(): void
    {
        global $DIC;

        $rbacadmin = $DIC->rbac()->admin();
        $rbacreview = $DIC->rbac()->review();

        ilObjAdobeConnectAccess::getLocalAdminRoleTemplateId();
        ilObjAdobeConnectAccess::getLocalMemberRoleTemplateId();

        $admin_role = ilObjRole::createDefaultRole(
            'il_xavc_admin_' . $this->getRefId(),
            'Admin of Adobe Connect object with obj_no.' . $this->getId(),
            'il_xavc_admin',
            $this->getRefId()
        );

        $member_role = ilObjRole::createDefaultRole(
            'il_xavc_member_' . $this->getRefId(),
            'Member of Adobe Connect object with obj_no.' . $this->getId(),
            'il_xavc_member',
            $this->getRefId()
        );

        $ops = $rbacreview->getOperationsOfRole($member_role->getId(), 'xavc', $this->getRefId());

        // Set view permission for users
        $rbacadmin->grantPermission(self::RBAC_DEFAULT_ROLE_ID, $ops, $this->getRefId());
        // Set view permission for guests
        $rbacadmin->grantPermission(self::RBAC_GUEST_ROLE_ID, [2], $this->getRefId());
    }

    /**
     * Returns all meetings that takes place during the current meeting object
     */
    public function checkConcurrentMeetingDates(): array
    {
        $quota = new ilAdobeConnectQuota();

        return $quota->checkConcurrentMeetingDates(
            $this->getEndDate(),
            $this->getStartDate(),
            $this->getId() ? $this->getId() : null
        );
    }

    public static function getObjectData($obj_id)
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = 'SELECT * FROM rep_robj_xavc_data WHERE id = %s';
        $types = ['integer'];
        $values = [$obj_id];

        $res = $ilDB->queryF($query, $types, $values);

        return $ilDB->fetchObject($res);
    }

    /**
     * Returns a List of Meetings that takes place in the time between $startDate and $endDate.
     * A Meeting is in range if $startDate > start_date < $endDate or $startDate > end_date < $endDate.
     * @param int $startDate unixtimestamp
     * @param int $endDate   unixtimestamp
     * @return array
     */
    public static function getMeetingsInRange(int $startDate, int $endDate): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = 'SELECT * FROM rep_robj_xavc_data WHERE (start_date > %s AND start_date < %s) OR (end_date > %s AND end_date < %s) ORDER BY start_date';
        $types = ['integer', 'integer', 'integer', 'integer'];
        $values = [$startDate, $endDate, $startDate, $endDate];

        $res = $ilDB->queryF($query, $types, $values);

        $objects = [];

        while ($row = $ilDB->fetchObject($res)) {
            if (ilObject::_hasUntrashedReference($row->id)) {
                $objects[] = $row;
            }
        }

        return $objects;
    }

    public static function getLocalScos(): array
    {
        global $DIC;

        $ilDB = $DIC->database();
        $local_scos = [];
        $res = $ilDB->query('SELECT sco_id FROM rep_robj_xavc_data');

        while ($row = $ilDB->fetchAssoc($res)) {
            $local_scos[] = $row['sco_id'];
        }

        return $local_scos;
    }

    public static function _lookupScoId($a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC->database();

        $res = $ilDB->queryF(
            'SELECT sco_id FROM rep_robj_xavc_data WHERE id = %s',
            ['integer'],
            [$a_obj_id]
        );

        $row = $ilDB->fetchAssoc($res);

        return $row['sco_id'];
    }

    public static function getScosByFolderId($folder_id): array
    {
        $instance = ilAdobeConnectServer::_getInstance();
        $adminLogin = $instance->getLogin();
        $adminPass = $instance->getPasswd();

        $xmlApi = ilXMLApiFactory::getApiByAuthMode();

        $session = $xmlApi->getBreezeSession();
        $scos = [];

        if ($session != null && $xmlApi->login($adminLogin, $adminPass, $session)) {
            $scos = $xmlApi->getScosByFolderId($folder_id, $session);
        }

        return $scos;
    }

    public function getFolderIdByLogin($externalLogin): ?string
    {
        $session = $this->xmlApi->getBreezeSession();
        if (ilAdobeConnectServer::getSetting('use_user_folders') == 1) {
            $folder_id = $this->xmlApi->lookupUserFolderId($externalLogin, $session);

            if (!$folder_id) {
                $folder_id = $this->xmlApi->createUserFolder($externalLogin, $session);
            }
        } else {
            $folder_id = $this->xmlApi->getShortcuts('my-meetings', $session);
        }
        return $folder_id;
    }

    public function getContentIconAttribute($sco_id): array
    {
        $session = $this->xmlApi->getBreezeSession();

        $icons = [];
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $icons[] = $this->xmlApi->getContentIconAttribute($sco_id, $this->sco_id, $session);
        }
        return $icons;
    }

    /**
     * @throws \ilAdobeConnectContentUploadException
     */
    public function uploadFile(string $url, string $filePath, string $title = ''): void
    {
        if (function_exists('curl_file_create')) {
            $curlFile = curl_file_create($filePath);
        } else {
            $curlFile = '@' . realpath($filePath);
        }

        $postData = ['file' => $curlFile];
        if ($title != '') {
            $postData['name'] = $title;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        $postResult = curl_exec($curl);
        curl_close($curl);

        try {
            $GLOBALS['ilLog']->write("AdobeConnect: addContent result ...");
            $GLOBALS['ilLog']->write($postResult);

            $xml = simplexml_load_string($postResult);

            if (!($xml instanceof \SimpleXMLElement)) {
                throw new \ilAdobeConnectContentUploadException('add_cnt_err');
            }

            if (strtolower($xml->status['code']) !== 'ok') {
                throw new \ilAdobeConnectContentUploadException('add_cnt_err');
            }
        } catch (\Exception $e) {
            $GLOBALS['ilLog']->write($e->getMessage());

            throw new \ilAdobeConnectContentUploadException('add_cnt_err');
        }
    }

    public function isUseMeetingTemplate(): bool
    {
        return $this->use_meeting_template;
    }

    public function setUseMeetingTemplate(bool $use_meeting_template): void
    {
        $this->use_meeting_template = $use_meeting_template;
    }

    public function getAcLanguage(): string
    {
        return $this->ac_language;
    }

    /**
     * @param string $language ISO 639-1 two-letter code
     */
    public function setAcLanguage(string $ac_language): void
    {
        $this->ac_language = strtolower($ac_language);
    }

    public function isHtmlClientEnabled(): bool
    {
        return $this->html_client;
    }

    public function setUseHtmlClient($html_client): void
    {
        $this->html_client = (bool) $html_client;
    }

    public static function addToFavourites(int $user_id, int $ref_id): void
    {
        $favourites = new ilFavouritesManager();
        $favourites->add($user_id, $ref_id);
    }

    public static function removeFromFavourites(int $user_id, int $ref_id): void
    {
        $favourites = new ilFavouritesManager();
        $favourites->remove($user_id, $ref_id);
    }
}
