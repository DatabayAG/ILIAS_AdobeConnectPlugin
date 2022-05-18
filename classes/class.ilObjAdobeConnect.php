<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
include_once("./Services/Calendar/classes/class.ilDateTime.php");

/**
 * Main application class for Adobe Connect repository object
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilObjAdobeConnect extends ilObjectPlugin
{

    public const ACCESS_LEVEL_PRIVATE = 'denied';  // no guests !
    public const ACCESS_LEVEL_PROTECTED = 'remove';
    public const ACCESS_LEVEL_PUBLIC = 'view-hidden';

    /**
     * default role id, User-Role by default
     * @var int
     */
    public const RBAC_DEFAULT_ROLE_ID = 4;

    /**
     * guest role id
     * @var int
     */
    public const RBAC_GUEST_ROLE_ID = 5;

    /**
     *  Meeting id
     * @var String
     */
    private $sco_id;
    /**
     *  Meeting start date
     * @var ilDateTime
     */
    private $start_date;
    /**
     *  Meeting duration
     * @var array
     */
    private $duration;
    /**
     * Meeting instructions
     * @var String
     */
    private $instructions = null;

    /**
     * @var null
     */
    private $contact_info = null;

    /**
     * @var int
     */
    private $permanent_room = 0;

    /***
     * @var string
     */
    private $access_level = self::ACCESS_LEVEL_PROTECTED;

    /**
     * @var int
     */
    private $read_contents = 0;

    /**
     * @var int
     */
    private $read_records = 0;

    /**
     * @var int
     */
    private $folder_id = 0;

    /**
     *  Meeting URL
     * @var String
     */
    private $url;
    /**
     *  Meeting contents
     * @var ilAdobeConnectContents
     */
    private $contents;
    /**
     *  Adobe Connect admin login
     * @var String
     */
    private $adminLogin;
    /**
     *  Adobe Connect admin password
     * @var String
     */
    private $adminPass;

    /**
     * @var null|void
     */
    public $externalLogin;
    /**
     * @var ilAdobeConnectDfnXMLAPI|ilAdobeConnectXMLAPI|ilSwitchAaiXMLAPI
     */
    public $xmlApi;

    /**
     * @var
     */
    private $permission;

    /**
     * @var null
     */
    public $assignment_mode = null;
    /**
     * @var null
     */
    public $end_date = null;

    /**
     * @var null
     */
    public $pluginObj = null;

    /**
     * @var null
     */
    public $participants = null;

    /**
     * @var bool
     */
    public $use_meeting_template = false;

    /**
     * @var string
     */
    public $ac_language = 'de';
    /**
     * @var bool
     */
    public $html_client = false;

    /**
     * Constructor
     * @access    public
     */
    public function __construct($a_ref_id = 0)
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();

        parent::__construct($a_ref_id);
        $this->ref_id = $a_ref_id;
        $this->pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');

        if (!$ilCtrl->isAsynch()) {
            $this->pluginObj->includeClass('class.ilAdobeConnectContents.php');
            $this->contents = new ilAdobeConnectContents();
        }

        $instance = ilAdobeConnectServer::_getInstance();
        $this->adminLogin = $instance->getLogin();
        $this->adminPass = $instance->getPasswd();
        $this->externalLogin = $this->checkExternalUser();

        $this->xmlApi = ilXMLApiFactory::getApiByAuthMode();
    }

    /**
     *
     */
    private function initParticipantsObject()
    {
        if ($this->getRefId() > 0) {
            global $DIC;
            $tree = $DIC->repositoryTree();

            $this->pluginObj->includeClass('class.ilAdobeConnectContainerParticipants.php');

            $parent_ref = $tree->checkForParentType($this->getRefId(), 'grp');
            if (!$parent_ref) {
                $parent_ref = $tree->checkForParentType($this->getRefId(), 'crs');
            }

            $object_id = ilObject::_lookupObjectId($parent_ref);
            $this->participants = ilAdobeConnectContainerParticipants::getInstanceByObjId($object_id);
        }
    }

    /**
     * @return ilAdobeConnectContainerParticipants|null
     */
    public function getParticipantsObject()
    {
        if (!$this->participants instanceof ilAdobeConnectContainerParticipants) {
            $this->initParticipantsObject();
        }

        return $this->participants;
    }

    /**
     * @param int $user_id
     * @return null|void
     */
    public function checkExternalUser($user_id = 0)
    {
        global $DIC;
        $ilUser = $DIC->user();

        if (!(isset($user_id) && $user_id > 0)) {
            $user_id = $ilUser->getId();
        }

        //check if there is a xavc-login already saved in ilias-db
        $this->pluginObj->includeClass('class.ilXAVCMembers.php');
        $tmp_xavc_login = ilXAVCMembers::_lookupXAVCLogin($user_id);

        if (!$tmp_xavc_login) {
            $this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
            $externalLogin = ilAdobeConnectUserUtil::generateXavcLoginName($user_id);
            ilXAVCMembers::addXAVCUser($user_id, $externalLogin);
        } else {
            // get saved login-data
            $externalLogin = $tmp_xavc_login;
        }
        return $externalLogin;
    }

    /**
     * Get type.
     */
    public final function initType()
    {
        $this->setType("xavc");
    }

    /**
     * Rollback function for creation workflow
     * @access    private
     */
    private function creationRollback()
    {
        $this->delete();
    }

    public function doCloneObject($new_obj, $a_target_id, $a_copy_id = null)
    {
        parent::doCloneObject($new_obj, $a_target_id, $a_copy_id); // TODO: Change the autogenerated stub
    }

    /**
     * Create plugin specific data
     * @access    public
     */
    public function doCreate()
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();

        $cmdClass = $ilCtrl->getCmdClass();
        $cmd = $ilCtrl->getCmd();

        if ($cmdClass == 'ilobjectcopygui') {
            $clone_ref_id = $this->getRefId();

            $now = new ilDateTime(time(), IL_CAL_UNIX);
            $this->start_date = new ilDateTime($now->getUnixTime() - 7200, IL_CAL_UNIX);
            $this->duration = array('hours' => 1, 'minutes' => 0);

            $this->publishCreationAC();
            return;
        } else {
            if (isset($_POST['tpl_id']) && (int) $_POST['tpl_id'] > 0) {
                $tpl_id = (int) $_POST['tpl_id'];
            } else {
                throw new ilException('no_template_id_given');
            }

            include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
            $templates = ilSettingsTemplate::getAllSettingsTemplates("xavc");

            foreach ($templates as $template) {
                if ((int) $template['id'] == $tpl_id) {
                    $template_settings = array();
                    if ($template['id']) {
                        $objTemplate = new ilSettingsTemplate($template['id']);
                        $template_settings = $objTemplate->getSettings();
                    }
                }
            }

            // reuse existing ac-room
            if (isset($_POST['creation_type']) && $_POST['creation_type'] == 'existing_vc' && $template_settings['reuse_existing_rooms']['hide'] == '0') {
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

            if (strlen($_POST['instructions']) > 0) {
                $post_instructions = (string) $_POST['instructions'];
            } else {
                if (strlen($_POST['instructions_2']) > 0) {
                    $post_instructions = (string) $_POST['instructions_2'];
                } else {
                    if (strlen($_POST['instructions_3']) > 0) {
                        $post_instructions = (string) $_POST['instructions_3'];
                    }
                }
            }

            if (strlen($_POST['contact_info']) > 0) {
                $post_contact = (string) $_POST['contact_info'];
            } else {
                if (strlen($_POST['contact_info_2']) > 0) {
                    $post_contact = (string) $_POST['contact_info_2'];
                } else {
                    if (strlen($_POST['contact_info_3']) > 0) {
                        $post_contact = (string) $_POST['contact_info_3'];
                    }
                }
            }

            $this->setInstructions($post_instructions);
            $this->setContactInfo($post_contact);
            $this->setAcLanguage($_POST['ac_language']);
            $this->setUseHtmlClient($_POST['html_client']);

            if (isset($_POST['time_type_selection']) && $_POST['time_type_selection'] == 'permanent_room') {
                $this->setPermanentRoom(1);
            } else {
                if (!isset($_POST['time_type_selection']) && ilAdobeConnectServer::getSetting('default_perm_room') == 1) {
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

            $this->pluginObj->includeClass('class.ilXAVCPermissions.php');
            $this->setReadContents(ilXAVCPermissions::lookupPermission(AdobeConnectPermissions::PERM_READ_CONTENTS,
                'view'));
            $this->setReadRecords(ilXAVCPermissions::lookupPermission(AdobeConnectPermissions::PERM_READ_RECORDS,
                'view'));

            $this->externalLogin = $this->checkExternalUser();

            $folder_id = $this->getFolderIdByLogin($this->externalLogin);
            $this->setFolderId($folder_id);
        }

        try {
            if (isset($_POST['start_date']) && is_string($_POST['start_date']) && strlen($_POST['start_date']) > 0 && $template_settings['start_date']['hide'] == '0') {
                $this->start_date = new ilDateTime($_POST['start_date'], IL_CAL_DATETIME);
            } else {
                if (isset($_POST['start_date']) && is_array($_POST['start_date']) && $template_settings['start_date']['hide'] == '0') {
                    $this->start_date = new ilDateTime($_POST['start_date']['date'] . ' ' . $_POST['start_date']['time'],
                        IL_CAL_DATETIME);
                } else {
                    $this->start_date = new ilDateTime(time() + 120, IL_CAL_UNIX);
                }
            }

            // duration
            if (isset($_POST['duration']['hh']) && isset($_POST['duration']['mm'])
                && ($_POST['duration']['hh'] > 0 || $_POST['duration']['mm'] > 0)
                && $template_settings['duration']['hide'] == '0') {
                $this->duration = array
                (
                    'hours' => $_POST['duration']['hh'],
                    'minutes' => $_POST['duration']['mm']
                );
            } else {
                $this->duration = array('hours' => (int) $template_settings['duration']['value'], 'minutes' => 0);
            }

            //end_date
            $this->end_date = $this->getEnddate();

            $concurrent_vc = count($this->checkConcurrentMeetingDates());
            $max_rep_obj_vc = ilAdobeConnectServer::getSetting('ac_interface_objects');
            if ((int) $max_rep_obj_vc > 0 && $concurrent_vc >= $max_rep_obj_vc) {
                throw new ilException('xavc_reached_number_of_connections');
            }

            $this->setUseMeetingTemplate($_POST['use_meeting_template'] == '1' ? true : false);
            $this->publishCreationAC();
        } catch (ilException $e) {
            $this->creationRollback();
            throw new ilException($this->txt($e->getMessage()));
        }
    }

    public function useExistingVC($obj_id, $sco_id)
    {
        global $DIC;
        $ilUser = $DIC->user();
        $ilDB = $DIC->database();

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
            $this->xmlApi->addUser
            (
                $this->externalLogin,
                $ilUser->getEmail(),
                $ilUser->getPasswd(),
                $ilUser->getFirstName(),
                $ilUser->getLastName(),
                $session
            );
        }

        $this->xmlApi->updateMeetingParticipant($sco_id, $this->externalLogin, $session, 'host');

        $start_date = time();
        $end_date = strtotime('+2 hours');

        $ilDB->insert('rep_robj_xavc_data',
            array(
                'id' => array('integer', $obj_id),
                'sco_id' => array('integer', $sco_id),
                'start_date' => array('integer', $start_date),
                'end_date' => array('integer', $end_date),
                'folder_id' => array('integer', $folder_id)
            )
        );
    }

    /**
     * @throws ilException
     */
    protected function publishCreationAC()
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

        global $DIC;
        $ilDB = $DIC->database();

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
        $arr_meeting = $this->xmlApi->addMeeting
        (
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

        if (ilAdobeConnectServer::getSetting('user_assignment_mode') != ilAdobeConnectServer::ASSIGN_USER_SWITCH) {
            //Normal Case (not SWITCH aai)

            if ($this->externalLogin == null) {
                throw new ilException('xavc_external_login_error');
            } else {
                $this->xmlApi->addUser
                (
                    $this->externalLogin,
                    $ownerObj->getEmail(),
                    $ownerObj->getPasswd(),
                    $ownerObj->getFirstName(),
                    $ownerObj->getLastName(),
                    $session);
            }
            $this->xmlApi->updateMeetingParticipant($meeting_id, $this->externalLogin, $session, 'host');
        } else {
            //In the SWITCH aai case, every user already exists thanks to "cave"

            //Add ILIAS-user himself
            $this->xmlApi->addMeetingHost($meeting_id, $ownerObj->getEmail(), $session);
            //Add technical user
            $this->xmlApi->updateMeetingParticipant($meeting_id, ilAdobeConnectServer::getSetting('login'), $session,
                'host');
        }

        $this->xmlApi->updatePermission($meeting_id, $session, $access_level);

        $ilDB->insert('rep_robj_xavc_data',
            array(
                'id' => array('integer', $obj_id),
                'sco_id' => array('integer', $meeting_id),
                'start_date' => array('integer', $start_date->getUnixTime()),
                'end_date' => array('integer', $end_date->getUnixTime()),
                'instructions' => array('text', $instructions),
                'contact_info' => array('text', $contact_info),
                'permanent_room' => array('integer', (int) $permanent_room),
                'perm_read_contents' => array('integer', (int) $this->getReadContents()),
                'perm_read_records' => array('integer', (int) $this->getReadRecords()),
                'folder_id' => array('integer', $folder_id),
                'url_path' => array('text', $meeting_url),
                'language' => array('text', $this->getAcLanguage()),
                'html_client' => array('integer', $this->isHtmlClientEnabled())
            )
        );
    }

    /**
     * @param integer $ref_id ref_id of ilias ac-object
     * @param integer $sco_id
     * @param array   $member_ids
     */
    public function addCrsGrpMembers($ref_id, $sco_id, $member_ids = null)
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

        $this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
        $xavcRoles = new ilAdobeConnectRoles($ref_id);

        if (ilAdobeConnectServer::getSetting('user_assignment_mode') != ilAdobeConnectServer::ASSIGN_USER_SWITCH) {
            foreach ($all_participants as $user_id) {
                $this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');

                //check if there is an adobe connect account at the ac-server
                $ilAdobeConnectUser = new ilAdobeConnectUserUtil($user_id);
                $ilAdobeConnectUser->ensureAccountExistance();

                // add to desktop
                if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
                    self::addToFavourites((int) $user_id, (int) $ref_id);
                }
            }
        }

        // receive breeze session
        $session = $this->xmlApi->getBreezeSession();

        $this->pluginObj->includeClass('class.ilXAVCMembers.php');

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

                $this->xmlApi->updateMeetingParticipant($sco_id, ilXAVCMembers::_lookupXAVCLogin($user_id), $session,
                    $status);
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

                $this->xmlApi->updateMeetingParticipant($sco_id, ilXAVCMembers::_lookupXAVCLogin($user_id), $session,
                    $status);
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

                $this->xmlApi->updateMeetingParticipant($sco_id, ilXAVCMembers::_lookupXAVCLogin($user_id), $session,
                    $status);
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

            $this->xmlApi->updateMeetingParticipant($sco_id, ilXAVCMembers::_lookupXAVCLogin($owner_id), $session,
                $status);
        }
    }

    public function deleteCrsGrpMembers($sco_id, $delete_user_ids)
    {
        $this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
        $this->pluginObj->includeClass('class.ilXAVCMembers.php');

        $xavcRoles = new ilAdobeConnectRoles($this->getRefId());

        if (is_array($delete_user_ids) && count($delete_user_ids) > 0) {
            foreach ($delete_user_ids as $usr_id) {
                $xavcRoles->detachMemberRole($usr_id);

                ilXAVCMembers::deleteXAVCMember($usr_id, $this->getRefId());
                $xavc_login = ilXAVCMembers::_lookupXAVCLogin($usr_id);

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
    public function doRead()
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();
        $ilDB = $DIC->database();

        if ($ilCtrl->isAsynch()) {
            return;
        }

        $set = $ilDB->query("SELECT * FROM rep_robj_xavc_data " .
            " WHERE id = " . $ilDB->quote($this->getId(), "integer")
        );

        while ($rec = $ilDB->fetchAssoc($set)) {
            $this->sco_id = $rec["sco_id"];
            $this->instructions = $rec['instructions'];
            $this->contact_info = $rec['contact_info'];
            $this->permanent_room = $rec['permanent_room'];
            $this->read_contents = $rec['perm_read_contents'];
            $this->read_records = $rec['perm_read_records'];
            $this->folder_id = $rec['folder_id'];
            $this->url = $rec['url_path'];
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
                if ($this->xmlApi->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI) {
                    //the parameter meeting is used for the switchaai-case
                    $this->url = substr($this->xmlApi->getURLByType($this->sco_id, $this->folder_id, $session,
                        'meeting'), 0, -1);
                } else {
                    $this->url = substr($this->xmlApi->getURL($this->sco_id, $this->folder_id, $session), 0, -1);
                }
            }

            $date_begin = $this->xmlApi->getStartDate($this->sco_id, $this->folder_id, $session);
            $this->start_date = new ilDateTime(strtotime($date_begin), IL_CAL_UNIX);
            $date_end_string = $this->xmlApi->getEndDate($this->sco_id, $this->folder_id, $session);
            $end_date = new ilDateTime(strtotime($date_end_string), IL_CAL_UNIX);
            $this->end_date = $end_date;
            $unix_duration = $end_date->getUnixTime() - $this->start_date->getUnixTime();

            $hours = floor($unix_duration / 3600);
            $minutes = floor(($unix_duration - $hours * 3600) / 60);
            $this->duration = array("hours" => $hours, "minutes" => $minutes);

            $this->pluginObj->includeClass('class.ilAdobeConnectContents.php');
            $this->contents = new ilAdobeConnectContents();

            $this->access_level = $this->xmlApi->getPermissionId($this->sco_id, $session);
        }
        $this->initParticipantsObject();
    }

    /**
     * Update data
     */
    public function doUpdate()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $end_date = new ilDateTime($this->start_date->getUnixTime() + $this->duration["hours"] * 3600 + $this->duration["minutes"] * 60,
                IL_CAL_UNIX);
            $this->xmlApi->updateMeeting($this->sco_id, $this->getTitle(), $this->getDescription(),
                date('Y-m-d', $this->start_date->getUnixTime()), date('H:i', $this->start_date->getUnixTime()),
                date('Y-m-d', $end_date->getUnixTime()), date('H:i', $end_date->getUnixTime()), $session,
                $this->getAcLanguage(), $this->isHtmlClientEnabled());

            $this->xmlApi->updatePermission($this->sco_id, $session, $this->permission);
        }

        $ilDB->update('rep_robj_xavc_data',
            array(
                'start_date' => array('integer', $this->getStartdate()->getUnixTime()),
                'end_date' => array('integer', $this->getEnddate()->getUnixTime()),
                'instructions' => array('text', $this->getInstructions()),
                'contact_info' => array('text', $this->getContactInfo()),
                'permanent_room' => array('integer', $this->getPermanentRoom()),
                'perm_read_contents' => array('integer', $this->getReadContents()),
                'perm_read_records' => array('integer', $this->getReadRecords()),
                'language' => array('text', $this->getAcLanguage()),
                'html_client' => array('integer', $this->isHtmlClientEnabled())
            ),
            array('sco_id' => array('integer', $this->getScoId())));

    }

    /**
     * Delete data from db and from Adobe Connect server
     */
    public function doDelete()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $session = $this->xmlApi->getBreezeSession();
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->deleteMeeting($this->sco_id, $session);
        }

        $ilDB->manipulateF('DELETE FROM rep_robj_xavc_data WHERE id = %s',
            array('integer'), array($this->getId()));

        $ilDB->manipulateF('DELETE FROM rep_robj_xavc_members WHERE sco_id = %s',
            array('integer'), array($this->sco_id));
    }

    /**
     * Do Cloning
     */
    public function doClone($a_target_id, $a_copy_id, $new_obj)
    {
        global $DIC;
        $ilUser = $DIC->user();
        $ilDB = $DIC->database();

        // to avoid date-conflicts:
        // start_date = now - 2h
        // duration = 1h

        $now = new ilDateTime(time(), IL_CAL_UNIX);
        $this->start_date = new ilDateTime($now->getUnixTime() - 7200, IL_CAL_UNIX);
        //$this->start_date = new ilDateTime(0, IL_CAL_UNIX);
        $this->duration = array('hours' => 1, 'minutes' => 0);

        $new_obj->setStartDate($this->getStartDate());

        $new_obj->setInstructions($this->getInstructions());
        $new_obj->setContactInfo($this->getContactInfo());
        $new_obj->setPermanentRoom($this->getPermanentRoom());
        $new_obj->setReadContents($this->getReadContents());
        $new_obj->setReadRecords($this->getReadRecords());
        $new_obj->setDuration($this->getDuration());
        $new_obj->setURL($this->getURL());
        $new_obj->setScoId($this->getScoId());
        $new_obj->setFolderId($this->getFolderId());
        $new_obj->setAcLanguage($this->getAcLanguage());
        $new_obj->setUseHtmlClient($this->isHtmlClientEnabled());
        $new_obj->update();

        // add xavc-member,  assign roles
        $new_obj_id = $new_obj->getId();
        $res = $ilDB->queryF('SELECT sco_id FROM rep_robj_xavc_data WHERE id = %s',
            array('integer'), array($new_obj_id));

        $row = $ilDB->fetchAssoc($res);
        $new_sco_id = $row['sco_id'];

        $this->pluginObj->includeClass('class.ilXAVCMembers.php');
        $this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');

        $xavcMemberObj = new ilXAVCMembers($new_obj->getRefId(), $ilUser->getId());
        $xavcMemberObj->setPresenterStatus();
        $xavcMemberObj->setScoId($new_sco_id);
        $xavcMemberObj->insertXAVCMember();

        $xavc_role = new ilAdobeConnectRoles($new_obj->getRefId());
        $xavc_role->addAdministratorRole($ilUser->getId());

        if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
            self::addToFavourites($ilUser->getId(), $new_obj->getRefId());
        }
    }

    /*
    * Set/Get Methods for our virtual classroom properties
    */

    /**
     *  Sets meeting start date
     * @param ilDateTime $a_val
     */
    public function setStartDate($a_val)
    {
        $this->start_date = $a_val;
    }

    /**
     *  Returns meeting start date
     * @return ilDateTime
     */
    public function getStartDate()
    {
        return $this->start_date;
    }

    /**
     *  Sets meeting contents
     * @param ilAdobeConnectContents $a_val
     */
    public function setContents($a_val)
    {
        $this->contents = $a_val;
    }

    /**
     *  Returns meeting contents
     * @return ilAdobeConnectContents
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     *  Sets meeting duration
     * @param array $a_val
     */
    public function setDuration($a_val)
    {
        $this->duration = $a_val;
    }

    /**
     *  Returns meeting duration
     * @return array
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     *  Sets meeting id
     * @param String $a_val
     */
    public function setScoId($a_val)
    {
        $this->sco_id = $a_val;
    }

    /**
     *  Returns meeting id
     * @return String
     */
    public function getScoId()
    {
        return $this->sco_id;
    }

    /**
     *  Sets meeting URL
     * @param String $a_val
     */
    public function setUrl($a_val)
    {
        $this->url = $a_val;
    }

    /**
     *  Returns meeting URL
     * @return String
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function setPermission($a_permission)
    {
        $this->permission = $a_permission;
    }

    public function getPermission()
    {
        return $this->permission;
    }

    /**
     * @param String $instructions
     */
    public function setInstructions($instructions)
    {
        $this->instructions = $instructions;
    }

    /**
     * @return String
     */
    public function getInstructions()
    {
        return $this->instructions;
    }

    /**
     * @param null $contact_info
     */
    public function setContactInfo($contact_info)
    {
        $this->contact_info = $contact_info;
    }

    /**
     * @return null
     */
    public function getContactInfo()
    {
        return $this->contact_info;
    }

    /**
     * @param int $permanent_room
     */
    public function setPermanentRoom($permanent_room)
    {
        $this->permanent_room = $permanent_room;
    }

    /**
     * @return int
     */
    public function getPermanentRoom()
    {
        return $this->permanent_room;
    }

    /**
     * @param int $read_contents
     */
    public function setReadContents($read_contents)
    {
        $this->read_contents = $read_contents;
    }

    /**
     * @return int
     */
    public function getReadContents()
    {
        return $this->read_contents;
    }

    /**
     * @param int $read_records
     */
    public function setReadRecords($read_records)
    {
        $this->read_records = $read_records;
    }

    /**
     * @return int
     */
    public function getReadRecords()
    {
        return $this->read_records;
    }

    /**
     * @param int $folder_id
     */
    public function setFolderId($folder_id)
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @return int
     */
    public function getFolderId()
    {
        return $this->folder_id;
    }

    /**
     *  Returns meeting end date
     * @return ilDateTime
     */
    public function getEndDate()
    {
        $end_date = new ilDateTime($this->start_date->getUnixTime(), IL_CAL_UNIX);
        $end_date->increment(ilDateTime::HOUR, $this->duration["hours"]);
        $end_date->increment(ilDateTime::MINUTE, $this->duration["minutes"]);
        return $end_date;
    }

    /*
    * Contents functions
    */

    /**
     *  Reads contents from Adobe Connect server
     * @param string $by_type null|content|record
     * @return bool
     */
    public function readContents($by_type = null)
    {
        $session = $this->xmlApi->getBreezeSession();

        $ids = array();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $ids = ($this->xmlApi->getContentIds($this->sco_id, $session) ? $this->xmlApi->getContentIds($this->sco_id,
                $session) : array());

            foreach ($ids as $id) {
                $date_created = $this->xmlApi->getDateCreated($id, $this->sco_id, $session);

                $date_end = $this->xmlApi->getDateEnd($id, $this->sco_id, $session);
                if ($date_end == '') {
                    $type = 'content';
                } else {
                    $type = 'record';
                }

                if ($by_type == null || $by_type == $type) {
                    $attributes = array(
                        "sco-id" => $id,
                        "name" => $this->xmlApi->getName($id, $this->sco_id, $session),
                        "url" => $this->xmlApi->getURL($id, $this->sco_id, $session),
                        "date-created" => new ilDateTime(substr($date_created, 0, 10) . " " . substr($date_created, 11,
                                8), IL_CAL_DATETIME),
                        "date-end" => $date_end,
                        "description" => $this->xmlApi->getDescription($id, $this->sco_id, $session),
                        "type" => $type
                    );
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
    public function readRecords()
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $ids = $this->xmlApi->getRecordIds($this->sco_id, $session);
            foreach ($ids as $id) {
                $date_created = $this->xmlApi->getDateCreated($id, $this->sco_id, $session);
                $attributes_records = array(
                    "sco-id" => $id,
                    "name" => $this->xmlApi->getName($id, $this->getScoId(), $session),
                    "url" => $this->xmlApi->getURL($id, $this->sco_id, $session),
                    "date-created" => new ilDateTime(substr($date_created, 0, 10) . " " . substr($date_created, 11, 8),
                        IL_CAL_DATETIME),
                    "duration" => $this->xmlApi->getDuration($id, $this->sco_id, $session),
                    "description" => $this->xmlApi->getDescription($id, $this->sco_id, $session),
                    "type" => "record"
                );
                $this->contents->addContent($attributes_records);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     *  Returns the contents containing the search criteria
     * @param array $search_criteria
     * @return array
     */
    public function searchContent($search_criteria)
    {
        return $this->contents->search($search_criteria);
    }

    /**
     *  Returns the content associated with the identifier
     * @param String $sco_id
     * @return ilAdobeConnectContent
     */
    public function getContent($sco_id)
    {
        $contents = $this->searchContent(array("sco-id" => $sco_id));

        return $contents[0];
    }

    /**
     *  Adds a content to the Adobe Connect server
     * @param String $title
     * @param String $description
     * @return String
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function addContent($title = "untitled", $description = "")
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->addContent($this->sco_id, $title, $description, $session);
        }
    }

    /**
     * Updates a content on the Adobe Connect server
     * @param String $sco_id
     * @param String $title
     * @param String $description
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function updateContent($sco_id, $title, $description)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->updateContent($sco_id, $title, $description, $session);
        }
    }

    /**
     *  Removes a content from the Adobe Connect server
     * @param String $sco_id
     */
    public function deleteContent($sco_id)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $this->xmlApi->deleteContent($sco_id, $session);
        }
    }

    /**
     *  Uploads a content to the Adobe Connect server
     * @param String $sco_id
     * @return String
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
     * @return array
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
     * @param String $login
     * @return boolean              Returns true if everything is ok
     */
    public function addParticipant($login)
    {
        $session = $this->xmlApi->getBreezeSession();

        //check if adobe connect account exists
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $participant = $this->xmlApi->addMeetingParticipant($this->sco_id, $login, $session);
            return $participant;
        }
    }

    /**
     *  Add a new participant to the meeting
     * @param String $login
     * @param String $status
     * @return boolean Returns true if everything is ok
     */
    public function addSwitchParticipant($login, $status)
    {
        $session = $this->xmlApi->getBreezeSession();
        $participant = $this->xmlApi->updateMeetingParticipantByTechnicalUser($this->getScoId(), $login, $session,
            $status);
        return $participant;
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
     * @param String $login
     * @return boolean          Returns true if everything is ok
     */
    public function deleteParticipant($login)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->deleteMeetingParticipant($this->sco_id, $login, $session);
        }
    }

    /**
     *  Check whether a user is host in this virtual classroom.
     * @param String $login
     * @return boolean
     */
    public function isParticipant($login)
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            return $this->xmlApi->isParticipant($login, $this->sco_id, $session);
        }
    }

    public function getPermissionId()
    {
        $session = $this->xmlApi->getBreezeSession();

        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $permission = $this->xmlApi->getPermissionId($this->sco_id, $session);
        }
        return $permission;
    }

    // LOCAL ROLES FOR ILIAS
    public function initDefaultRoles()
    {
        global $DIC;

        $rbacadmin = $DIC->rbac()->admin();
        $rbacreview = $DIC->rbac()->review();

        include_once 'class.ilObjAdobeConnectAccess.php';
        include_once './Services/AccessControl/classes/class.ilObjRole.php';

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
        $rbacadmin->grantPermission(self::RBAC_GUEST_ROLE_ID, array(2), $this->getRefId());

        $roles = array(
            $admin_role->getId(),
            $member_role->getId()
        );

        return $roles ? $roles : array();
    }

    /**
     * Returns all meetings that takes place during the current meeting object
     * @return boolean
     */
    public function checkConcurrentMeetingDates()
    {
        require_once dirname(__FILE__) . '/class.ilAdobeConnectQuota.php';
        $quota = new ilAdobeConnectQuota();

        return $quota->checkConcurrentMeetingDates($this->getEndDate(), $this->getStartDate(),
            $this->getId() ? $this->getId() : null);
    }

    public static function getObjectData($obj_id)
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = 'SELECT * FROM rep_robj_xavc_data WHERE id = %s';
        $types = array('integer');
        $values = array($obj_id);

        $res = $ilDB->queryF($query, $types, $values);

        return $ilDB->fetchObject($res);
    }

    /**
     * Returns a List of Meetings that takes place in the time between $startDate and $endDate.
     * A Meeting is in range if $startDate > start_date < $endDate or $startDate > end_date < $endDate.
     * @param integer $startDate unixtimestamp
     * @param integer $endDate   unixtimestamp
     * @return array
     */
    public static function getMeetingsInRange($startDate, $endDate) : array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = 'SELECT * FROM rep_robj_xavc_data WHERE (start_date > %s AND start_date < %s) OR (end_date > %s AND end_date < %s) ORDER BY start_date';
        $types = array('integer', 'integer', 'integer', 'integer');
        $values = array($startDate, $endDate, $startDate, $endDate);

        $res = $ilDB->queryF($query, $types, $values);

        $objects = array();

        while ($row = $ilDB->fetchObject($res)) {
            if (ilObject::_hasUntrashedReference($row->id)) {
                $objects[] = $row;
            }
        }

        return $objects;
    }

    public static function getLocalScos() : array
    {
        global $DIC;
        $ilDB = $DIC->database();
        $local_scos = array();
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

        $res = $ilDB->queryF('SELECT sco_id FROM rep_robj_xavc_data WHERE id = %s',
            array('integer'), array($a_obj_id));

        $row = $ilDB->fetchAssoc($res);

        return $row['sco_id'];
    }

    public static function getScosByFolderId($folder_id) : array
    {
        $instance = ilAdobeConnectServer::_getInstance();
        $adminLogin = $instance->getLogin();
        $adminPass = $instance->getPasswd();

        $xmlApi = ilXMLApiFactory::getApiByAuthMode();

        $session = $xmlApi->getBreezeSession();

        if ($session != null && $xmlApi->login($adminLogin, $adminPass, $session)) {
            $scos = $xmlApi->getScosByFolderId($folder_id, $session);
        }
        return $scos;
    }

    public function getFolderIdByLogin($externalLogin) : ?string
    {
        $session = $this->xmlApi->getBreezeSession();
        if (ilAdobeConnectServer::getSetting('use_user_folders') == 1) {
            $folder_id = $this->xmlApi->lookupUserFolderId($externalLogin, $session);

            if (!$folder_id) {
                $folder_id = $this->xmlApi->createUserFolder($externalLogin, $session);
            }
        } else {
            $folder_id = $this->xmlApi->getShortcuts("my-meetings", $session);
        }
        return $folder_id;
    }

    /**
     * @param $sco_id
     * @return array
     */
    public function getContentIconAttribute($sco_id) : array
    {
        $session = $this->xmlApi->getBreezeSession();

        $icons = array();
        if ($session != null && $this->xmlApi->login($this->adminLogin, $this->adminPass, $session)) {
            $icons[] = $this->xmlApi->getContentIconAttribute($sco_id, $this->sco_id, $session);
        }
        return $icons;
    }

    /**
     * @param string $url
     * @param string $filePath
     * @param string $title
     * @throws \ilAdobeConnectContentUploadException
     */
    public function uploadFile($url, $filePath, $title = '') : void
    {
        if (function_exists('curl_file_create')) {
            $curlFile = curl_file_create($filePath);
        } else {
            $curlFile = '@' . realpath($filePath);
        }

        $postData = array('file' => $curlFile);
        if (strlen($title) > 0) {
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

        $this->pluginObj->includeClass('class.ilAdobeConnectContentUploadException.php');
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

    /**
     * @return bool
     */
    public function isUseMeetingTemplate() : bool
    {
        return $this->use_meeting_template;
    }

    /**
     * @param bool $use_meeting_template
     */
    public function setUseMeetingTemplate($use_meeting_template) : void
    {
        $this->use_meeting_template = $use_meeting_template;
    }

    /**
     * @return string
     */
    public function getAcLanguage()
    {
        return $this->ac_language;
    }

    /**
     * @param string $language ISO 639-1 two-letter code
     */
    public function setAcLanguage($ac_language)
    {
        $this->ac_language = strtolower($ac_language);
    }

    /**
     * @return bool
     */
    public function isHtmlClientEnabled()
    {
        return $this->html_client;
    }

    /**
     * @param bool $html_client
     */
    public function setUseHtmlClient($html_client)
    {
        $this->html_client = (bool) $html_client;
    }

    /**
     * @param int $user_id
     * @param int $ref_id
     */
    public static function addToFavourites(int $user_id, int $ref_id) : void
    {
        $favourites = new ilFavouritesManager();
        $favourites->add($user_id, $ref_id);
    }

    /**
     * @param int $user_id
     * @param int $ref_id
     */
    public static function removeFromFavourites(int $user_id, int $ref_id) : void
    {
        $favourites = new ilFavouritesManager();
        $favourites->remove($user_id, $ref_id);
    }
}
