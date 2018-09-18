<?php

include_once("./Services/Repository/classes/class.ilObjectPluginGUI.php");
include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
include_once 'Services/Search/classes/class.ilQueryParser.php';
include_once 'Services/Search/classes/class.ilObjectSearchFactory.php';
include_once './Services/User/classes/class.ilPublicUserProfileGUI.php';
include_once('Services/Search/classes/class.ilRepositorySearchGUI.php');
require_once dirname(__FILE__) . '/../interfaces/interface.AdobeConnectPermissions.php';
require_once './Services/User/Gallery/classes/class.ilUsersGalleryGUI.php';

/**
* User Interface class for Adobe Connect repository object.
*
* User interface classes process GET and POST parameter and call
* application classes to fulfill certain tasks.
*
* @author Nadia Matuschek <nmatuschek@databay.de>
* @author Martin Studer <ms@studer-raimann.ch>
*
* $Id$
*
* Integration into control structure:
* - The GUI class is called by ilRepositoryGUI
* - GUI classes used by this class are ilPermissionGUI (provides the rbac
*   screens) and ilInfoScreenGUI (handles the info screen).
*
* @ilCtrl_isCalledBy ilObjAdobeConnectGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
* @ilCtrl_Calls ilObjAdobeConnectGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
*
*/
class ilObjAdobeConnectGUI extends ilObjectPluginGUI implements AdobeConnectPermissions
{
	const CONTENT_MOD_EDIT = 1;
    const CONTENT_MOD_ADD = 2;

    const CREATION_FORM_TA_COLS = 60;
    const CREATION_FORM_TA_ROWS = 5;

	/** @var $tabs ilTabsGUI */
	public $tabs;
	/** @var $ctrl ilCtrl */
	public $ctrl;
	/** @var $access ilAccessHandler */
	public $access;

	/** @var $tpl $tpl */
	public $tpl;
	/** @var $lng $lng */
	public $lng;
	/** @var $user ilObjUser */
	public $user;
	/** @var $pluginObj ilPlugin */
	public $pluginObj;
	/** @var $form ilPropertyFormGUI */
	public $form;
	/** @var $cform ilPropertyFormGUI */
	public $cform;
	/** @var $csform ilPropertyFormGUI */
	public $csform;
	
	/** @var bool is_record  for initEditForm hide/show file upload form  */
	public $is_record = false;

	/**
	* Initialisation
	*/
	protected function afterConstructor()
	{
        global $DIC; 

		$this->pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
        $this->form = new ilPropertyFormGUI();

        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
		$this->lng = $DIC->language();
		$this->user = $DIC->user();
        $this->tpl = $DIC->ui()->mainTemplate();
		if(is_object($this->object))
		{
			$this->tpl->setDescription($this->object->getLongDescription());
		}
	}

	/**
	* Gets type.
	*/
	public final function getType()
	{
		return "xavc";
	}

    /**
     *  Handles all commmands of this class, centralizes permission checks
     *
     * @param String $cmd
     */
	public function performCommand($cmd)
	{
		$next_class = $this->ctrl->getNextClass($this);
		switch($next_class)
		{
			case 'ilpublicuserprofilegui':
				require_once './Services/User/classes/class.ilPublicUserProfileGUI.php';
				$profile_gui = new ilPublicUserProfileGUI($_GET["user"]);
				$profile_gui->setBackUrl($this->ctrl->getLinkTarget($this, "showMembersGallery"));
				$this->tabs->activateTab('participants');
				$this->__setSubTabs('participants');
				$this->tabs->activateSubTab("editParticipants");
				$html = $this->ctrl->forwardCommand($profile_gui);

				$this->tpl->setVariable("ADM_CONTENT", $html);
				break;
			case 'ilcommonactiondispatchergui':
				require_once 'Services/Object/classes/class.ilCommonActionDispatcherGUI.php';
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;
			case 'ilrepositorysearchgui':
				$this->tabs->setTabActive('participants');
				$rep_search = new ilRepositorySearchGUI();
				$rep_search->setCallback(
					$this,
					'addAsMember',
					array(
						'add_member' => $this->lng->txt('member'),
						'add_admin'	=> $this->lng->txt('administrator')
					)
				);
				$this->ctrl->setReturn($this, 'editParticipants');
				$this->ctrl->forwardCommand($rep_search);
				break;

			default:
				switch ($cmd)
				{
//		            case "editContents":
					case "editProperties":		// list all commands that need write permission here
					case "updateProperties":
					case "assignRolesAfterCreate":

						$this->checkPermission("write");
						$this->$cmd();
		                break;

					// list all commands that need read permission here
					case "addParticipant":
					case "detachParticipant":
					case "searchContentFile":
					case 'cancelSearchContentFile':
					case "showFileSearchResult":
					case "addContentFromILIAS":
					case "askDeleteContents":
					case "deleteContents":
					case "uploadFile":
					case "showUploadFile":
					case "editItem":
					case "editRecord":
					case "updateContent":
					case "updateRecord":
					case "showAddContent":
					case "addContent":
					case "assignAdmin":
					case "performDetachAdmin":
					case "performDetachMember":
					case "performAddCrsGrpMembers":
					case "addAsMember":
					case "detachMember":
					case "detachAdmin":
					case "addCrsGrpMembers":
					case 'showContent':
					case "editParticipants":
					case "updateParticipants":
					case 'performSso':
					case 'requestAdobeConnectContent':
					case "viewContents":
		            case "viewRecords":
					case "showMembersGallery":
					case "performCrsGrpTrigger":
						$this->checkPermission("read");
						$this->$cmd();
						break;
					case "join":
					case "leave":
						$this->checkPermission("visible");
						$this->$cmd();
						break;
						
					default:  
						$this->showContent();
						break;
				}
				break;
		}
	}

	public function assignRolesAfterCreate()
	{
		global $DIC; 
		$ilUser = $DIC->user();

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');

		$xavcMemberObj = new ilXAVCMembers($this->object->getRefId(), $ilUser->getId());
		$xavcMemberObj->setPresenterStatus();
		$xavcMemberObj->setScoId($this->object->getScoId());
		$xavcMemberObj->insertXAVCMember();

		$xavc_role = new ilAdobeConnectRoles($this->object->getRefId());
		$xavc_role->addAdministratorRole($ilUser->getId());

		if(ilAdobeConnectServer::getSetting('add_to_desktop') == 1)
		{
			ilObjUser::_addDesktopItem($ilUser->getId(), $this->object->getRefId(), 'xavc');
		}

		$this->object->addCrsGrpMembers($this->object->getRefId(), $this->object->getScoId());

		$this->editProperties();
	}

	/**
	* After object has been created -> jump to this command
	*/
	public function getAfterCreationCmd()
	{
		return "assignRolesAfterCreate";
	}

	/**
	* Gets standard command
	*/
	public function getStandardCmd()
	{
	}

	/**
	* Sets tabs
	*/
	public function setTabs()
	{
		global $DIC; 
		$ilUser = $DIC->user();
		
		$user_id = $ilUser->getId();
		$ref_id = $this->object->getRefId();

		$this->pluginObj->includeClass("class.ilXAVCPermissions.php");
		$this->pluginObj->includeClass("class.ilObjAdobeConnectAccess.php");
		$is_member = ilObjAdobeConnectAccess::_hasMemberRole($user_id, $ref_id);
		$is_admin = ilObjAdobeConnectAccess::_hasAdminRole($user_id, $ref_id);

		// tab for the "show contents" command
		if ($this->access->checkAccess("read", "", $this->object->getRefId()) || $is_member)
		{
			$this->tabs->addTab("contents", $this->txt("adobe_meeting_room"), $this->ctrl->getLinkTarget($this, "showContent"));
        }

		// standard info screen tab
		$this->addInfoTab();

		// a "properties" tab
		if ($this->access->checkAccess("write", "", $this->object->getRefId()))
		{
			$this->tabs->addTab("properties", $this->txt("properties"), $this->ctrl->getLinkTarget($this, "editProperties"));
		}

		$xavc_access = ilXAVCPermissions::hasAccess($user_id, $ref_id, AdobeConnectPermissions::PERM_EDIT_PARTICIPANTS);

		// tab for the "edit participants" command
        if ($xavc_access || $is_admin || ilObject::_lookupOwner(ilObject::_lookupObjectId($ref_id)) == $ilUser->getId() )
		{
			$this->tabs->addTab("participants", $this->txt("participants"), $this->ctrl->getLinkTarget($this, "editParticipants"));
		}
		else
		if ($this->access->checkAccess("read", "", $this->object->getRefId()))
		{
			$this->tabs->addTab("participants", $this->txt("participants"), $this->ctrl->getLinkTarget($this, "showMembersGallery"));
		}

//		// tab for the "show records" command
//		if ($this->access->checkAccess("read", "", $this->object->getRefId()) || $is_member )
//		{
//			$this->tabs->addTab("records", $this->txt("records"), $this->ctrl->getLinkTarget($this, "viewRecords"));
//		}
		// standard epermiss"ion tab
		$this->addPermissionTab();
	}

    /**
     *  Sets subtabs
     *
     * @param String $a_tab       Parent tab
     */
    protected function __setSubTabs($a_tab)
    {
		global $DIC;
		$lng = $DIC->language(); 
		$ilUser = $DIC->user();

		$lng->loadLanguageModule('crs');

		switch ($a_tab)
		{
//			case 'contents':
//			$this->tabs->addSubTab("viewContents",$this->txt("view"), $this->ctrl->getLinkTarget($this,'viewContents'));
//			// tab for the "edit content" command
//			if ($this->access->checkAccess("write", "", $this->object->getRefId()))
//				$this->tabs->addSubTab("editContents",$this->txt("edit"),$this->ctrl->getLinkTarget($this,'editContents'));
//
//			break;
			case 'participants':
				$xavc_access = ilXAVCPermissions::hasAccess($ilUser->getId(), $this->object->getRefId(), AdobeConnectPermissions::PERM_EDIT_PARTICIPANTS);
				if(	$xavc_access )
				{
					$this->tabs->addSubTab("editParticipants",$lng->txt("crs_member_administration"),$this->ctrl->getLinkTarget($this,'editParticipants'));

					if( !ilAdobeConnectServer::getSetting('allow_crs_grp_trigger') && count($this->object->getParticipantsObject()->getParticipants()) > 0)
					{
						$this->tabs->addSubTab("addCrsGrpMembers",$this->txt("add_crs_grp_members"),$this->ctrl->getLinkTarget($this,'addCrsGrpMembers'));
					}
				}
				$this->tabs->addSubTab("showMembersGallery",$this->pluginObj->txt('members_gallery'),$this->ctrl->getLinkTarget($this,'showMembersGallery'));
			break;
		}
    }

	/**
	* Edits Properties. This commands uses the form class to display an input form.
	*/
	public function editProperties()
	{
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

        $this->object->doRead();
		$this->tabs->activateTab("properties");

		$this->initPropertiesForm();
		$this->getPropertiesValues();

		if(ilAdobeConnectServer::getSetting('show_free_slots'))
		{
			$this->showCreationForm($this->form);
		}
		else
		{
			$this->tpl->setContent($this->form->getHtml());
		}
	}

    /**
     *  Inits form
     */
	public function initPropertiesForm()
	{
		$this->form = new ilPropertyFormGUI();

		// title
		$ti = new ilTextInputGUI($this->txt("title"), "title");
		$ti->setRequired(true);
		$this->form->addItem($ti);

		// description
		$ta = new ilTextAreaInputGUI($this->txt("description"), "desc");
		$this->form->addItem($ta);

		$instructions = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions');
		$instructions->setRows(self::CREATION_FORM_TA_ROWS);
		$this->form->addItem($instructions);

		// contact_info
		$contact_info = new ilTextAreaInputGUI($this->pluginObj->txt("contact_information"), "contact_info");
		$contact_info->setRows(self::CREATION_FORM_TA_ROWS);
		$this->form->addItem($contact_info);

		$radio_access_level = new ilRadioGroupInputGUI($this->pluginObj->txt('access'), 'access_level');
		$opt_private = new ilRadioOption($this->pluginObj->txt('private_room'), ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE);
		$opt_protected = new ilRadioOption($this->pluginObj->txt('protected_room'), ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED);
		$opt_public = new ilRadioOption($this->pluginObj->txt('public_room'), ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC);

		$radio_access_level->addOption($opt_private);
		$radio_access_level->addOption($opt_protected);
		$radio_access_level->addOption($opt_public);

		$this->form->addItem($radio_access_level);

		$radio_time_type = new ilRadioGroupInputGUI($this->pluginObj->txt('time_type_selection'), 'time_type_selection');

		// option: permanent room
		if(ilAdobeConnectServer::getSetting('enable_perm_room', '1'))
		{
			$permanent_room = new ilRadioOption($this->pluginObj->txt('permanent_room'), 'permanent_room');
			$permanent_room->setInfo($this->pluginObj->txt('permanent_room_info'));
			$radio_time_type->addOption($permanent_room);
		}
		// option: date selection
		$opt_date = new ilRadioOption( $this->pluginObj->txt('start_date'), 'date_selection');
		// start date
        $sd = new ilDateTimeInputGUI($this->txt("start_date"), "start_date");
		$sd->setShowTime(true);
        $sd->setInfo($this->txt("info_start_date"));
        $sd->setRequired(true);
		$opt_date->addSubItem($sd);

        $duration = new ilDurationInputGUI($this->pluginObj->txt("duration"),"duration");
        $duration->setRequired(true);

		$opt_date->addSubItem($duration);

		$radio_time_type->addOption($opt_date);
		$this->form->addItem($radio_time_type);

		$cb_uploads = new ilCheckboxInputGUI($this->pluginObj->txt('read_contents'), 'read_contents');
		$cb_records = new ilCheckboxInputGUI($this->pluginObj->txt('read_records'), 'read_records');

		$this->form->addItem($cb_uploads);
		$this->form->addItem($cb_records);
		
		$lang_selector = new ilSelectInputGUI($this->lng->txt('language'), 'ac_language');
		$adobe_langs = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'tr', 'ru', 'ja', 'zh', 'ko'];
		$this->lng->loadLanguageModule('meta');
		foreach($adobe_langs as $lang)
		{
			$lang_options[$lang] = $this->lng->txt('meta_l_'.$lang);
		}
		
		$lang_selector->setOptions($lang_options);
		$this->form->addItem($lang_selector);
		
		$this->form->addCommandButton("updateProperties", $this->txt("save"));
		$this->form->addCommandButton("editProperties", $this->txt("cancel"));

		$this->form->setTitle($this->txt("edit_properties"));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}

    /**
	* Gets values for edit properties form
	*/
	public function getPropertiesValues()
	{
		$values["title"] = $this->object->getTitle();
		$values["desc"] = $this->object->getDescription();

		$values['access_level'] = $this->object->getPermissionId();

		if($this->object->getPermanentRoom() == 1 && ilAdobeConnectServer::getSetting('enable_perm_room', '1'))
		{
			$values['time_type_selection'] = 'permanent_room';
		}
		else
		{
			$values['time_type_selection'] = 'date_selection';
		}
        $duration = $this->object->getDuration();
		if(version_compare(ILIAS_VERSION_NUMERIC, '5.2.0', '>='))
		{
			$values["start_date"] = $this->object->getStartDate();
		}
		else
		{
			$values["start_date"] = array(
				'date' => date('Y-m-d', $this->object->getStartDate()->get(IL_CAL_UNIX)),
				'time' => date('H:i:s', $this->object->getStartDate()->get(IL_CAL_UNIX))
			);
		}
		
        $values["duration"] = array("hh"=>$duration["hours"],"mm"=>$duration["minutes"]);
		$values['instructions'] = $this->object->getInstructions();

		$values['contact_info'] = $this->object->getContactInfo();

		$values['read_contents'] = $this->object->getReadContents();
		$values['read_records'] = $this->object->getReadRecords();
		
		global $DIC;
		$default_lang = $DIC->language()->getDefaultLanguage();
		$adobe_langs = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'tr', 'ru', 'ja', 'zh', 'ko'];
		
		if(in_array($this->object->getAcLanguage(), $adobe_langs))
		{
			$values['ac_language'] = $this->object->getAcLanguage();
		}
		else if(in_array($default_lang, $adobe_langs))
		{
			$values['ac_language'] = $default_lang;
		}
		else
		{
			$values['ac_language'] = 'de';
		}

		$this->form->setValuesByArray($values);
	}

	/**
	 * Updates properties
	 */
	public function updateProperties()
	{
		global $DIC; 
		$ilCtrl = $DIC->ctrl();

		$this->initPropertiesForm();

		$formValid = $this->form->checkInput();

		$duration = $this->form->getInput("duration");

		if($this->form->getInput('time_type_selection') == 'permanent_room' && ilAdobeConnectServer::getSetting('enable_perm_room', '1'))
		{
			$durationValid = true;
		}
		else if($duration['hh'] * 60 + $duration['mm'] < 10)
		{
			$this->form->getItemByPostVar('duration')->setAlert($DIC->language()->txt('min_duration_error'));
			$durationValid = false;
		}
		else
		{
			$durationValid = true;
		}

		$oldObject = new ilObjAdobeConnect();
		$oldObject->setId($this->object->getId());
		$oldObject->doRead();

		$time_mismatch = false;

		if($formValid && $durationValid)
		{
			$new_start_date_input = $this->form->getItemByPostVar('start_date');
			if(
				$new_start_date_input instanceof ilDateTimeInputGUI &&
				$new_start_date_input->getDate() instanceof ilDateTime
			)
			{
				$newStartDate = $new_start_date_input->getDate();
			}
			else
			{
				$newStartDate = new ilDateTime(time(), IL_CAL_UNIX);
			}

			$this->object->setTitle($this->form->getInput("title"));
			$this->object->setDescription($this->form->getInput("desc"));
			$this->object->setInstructions($this->form->getInput('instructions'));
			$this->object->setContactInfo($this->form->getInput('contact_info'));
			$this->object->setAcLanguage($this->form->getInput('ac_language'));
			
			$enable_perm_room = (ilAdobeConnectServer::getSetting('enable_perm_room','1') && $this->form->getInput('time_type_selection') == 'permanent_room') ? true: false;
			$this->object->setPermanentRoom( $enable_perm_room ?  1 : 0 );

			$this->object->setReadContents((int)$this->form->getInput('read_contents'));
			$this->object->setReadRecords((int)$this->form->getInput('read_records'));

			$access_level = ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED;
			if(in_array($this->form->getInput('access_level'), array(ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE , ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED, ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC)))
			{
				$access_level = $this->form->getInput('access_level');
			}
			$this->object->setPermission( $access_level);

			if(!$time_mismatch || ($this->form->getInput('time_type_selection') == 'permanent_room' && ilAdobeConnectServer::getSetting('enable_perm_room', '1') ))
			{
				$this->object->setStartDate($newStartDate);
				$duration = $this->form->getInput("duration");
				$this->object->setDuration(array("hours"=> $duration["hh"], "minutes"=> $duration["mm"]));
			}
			$concurrent_vcs = $this->object->checkConcurrentMeetingDates();
			$num_max_ac_obj = ilAdobeConnectServer::getSetting('ac_interface_objects');
			if((int)$num_max_ac_obj <= 0 || (int)count($concurrent_vcs) < (int)$num_max_ac_obj)
			{
				$this->object->update();
				ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);

				$ilCtrl->redirect($this, 'editProperties');
			}
			else
			{
				ilUtil::sendFailure($this->pluginObj->txt('maximum_concurrent_vcs_reached'), true);
				$ilCtrl->redirect($this, 'editProperties');
			}
		}

		$this->form->setValuesByPost();
	}

	/**
	 * Returns true if the current objects access link should be displayed
	 * at $now.
	 * @param ilDateTime $now
	 * @return boolean
	 */
	private function doProvideAccessLink(ilDateTime $now = null)
	{
		$instance        = ilAdobeConnectServer::_getInstance();
		$datetime_before = new ilDateTime($this->object->getStartDate()->getUnixtime() - $instance->getBufferBefore(), IL_CAL_UNIX);
		$datetime_after  = new ilDateTime($this->object->getEndDate()->getUnixtime() + $instance->getBufferAfter(), IL_CAL_UNIX);
		if(!isset($now))
		{
			$now = new ilDateTime(time(), IL_CAL_UNIX);
		}
		return ilDateTime::_before($datetime_before, $now) && ilDateTime::_before($now, $datetime_after);
	}

	/**
    * Shows access link
    */
	public function showAccessLink()
	{
        $this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
        $this->pluginObj->includeClass('class.ilAdobeConnectQuota.php');
        $this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		$presentation_url = ilAdobeConnectServer::getPresentationUrl();

		$this->tabs->activateTab("access");

		$form = new ilPropertyFormGUI();
		$form->setTitle($this->pluginObj->txt('access_meeting_title'));

        $this->object->doRead();

		if($this->object->getStartDate() != NULL)
		{
			$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
			$ilAdobeConnectUser->ensureAccountExistance();

			$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
			$quota 		= new ilAdobeConnectQuota();

			// show link
			$link 		= new ilNonEditableValueGUI($this->pluginObj->txt("access_link"));

			if($this->doProvideAccessLink() && $this->object->isParticipant($xavc_login))
			{
				if(!$quota->mayStartScheduledMeeting($this->object->getScoId()))
				{
					$link->setValue($this->txt("meeting_not_available_no_slots"));
				}
				else
				{
					$link = new ilCustomInputGUI($this->pluginObj->txt("access_link"));
					$href = '<a href="' . $this->ctrl->getLinkTarget($this, 'performSso') . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
					$link->setHtml($href);
				}
			}
			else
			{
				$link->setValue($this->txt("meeting_not_available"));
			}

			$form->addItem($link);

			$start_date = new ilNonEditableValueGUI($this->txt('start_date'));

			$start_date->setValue(ilDatePresentation::formatDate(
				new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX)));

			$form->addItem($start_date);

			$duration = new ilNonEditableValueGUI($this->txt('duration'));
			$duration->setValue(ilDatePresentation::formatPeriod(
				new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX),
				new ilDateTime($this->object->getEndDate()->getUnixTime(), IL_CAL_UNIX)
			));
			$form->addItem($duration);

			$this->tpl->setContent($form->getHTML());
		}
		else
		{
			ilUtil::sendFailure($this->txt('error_connect_ac_server'));
		}
	}

	/**
	 * Performs a single singn on an redirects to adobe connect server
	 *
	 * @access	public
	 */
	public function performSso()
	{
		global $DIC;
		
		$ilSetting = $DIC->settings();

		$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectQuota.php');

		if(null !== $this->object->getStartDate())
		{
            //SWITCH
            $settings = ilAdobeConnectServer::_getInstance();
            if($settings->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI)
            {
                //@Todo MST check this IF-Statement
                if (($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink()))
                {
					if ($ilSetting->get('short_inst_name') != "")
					{
						$title_prefix = $ilSetting->get('short_inst_name');
					}
					else
					{
						$title_prefix = 'ILIAS';
					}

					$presentation_url = ilAdobeConnectServer::getPresentationUrl();
                    $url = ilAdobeConnectServer::getSetting('cave')."?back=".$presentation_url.$this->object->getURL();
                    $sso_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.perform_sso.html", true, true);
					$sso_tpl->setVariable('SPINNER_SRC', $this->pluginObj->getDirectory().'/templates/js/spin.js');
                    $sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
                    $sso_tpl->setVariable('LOGOUT_URL', "");
                    $sso_tpl->setVariable('URL', $url);
                    $sso_tpl->setVariable('INFO_TXT',$this->pluginObj->txt('redirect_in_progress'));
                    $sso_tpl->setVariable('OBJECT_TITLE',$this->object->getTitle());
                    $sso_tpl->show();
					exit;
                }
            }
            else
            {
                $ilAdobeConnectUser = new ilAdobeConnectUserUtil( $this->user->getId() );
                $ilAdobeConnectUser->ensureAccountExistance();

                $xavc_login = $ilAdobeConnectUser->getXAVCLogin();

                if (($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
                    && $this->object->isParticipant( $xavc_login ))
                {
                    $xmlAPI = ilXMLApiFactory::getApiByAuthMode();

                    $presentation_url = ilAdobeConnectServer::getPresentationUrl();

                    //login current user session
					if( !ilAdobeConnectServer::getSetting('enhanced_security_mode') == false || $settings->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_DFN)
					{
						$session = $xmlAPI->getBreezeSession(true);
					}
					else
					{
						$session = $xmlAPI->generateUserSessionCookie($xavc_login);
					}

                    $_SESSION['xavc_last_sso_sessid'] = $session;
                    $url = $presentation_url.$this->object->getURL().'?session='.$session;
                    
                    $GLOBALS['ilLog']->write(sprintf("Generated URL %s for user '%s'", $url, $xavc_login));

                    $presentation_url = ilAdobeConnectServer::getPresentationUrl(true);
                    $logout_url = $presentation_url.'/api/xml?action=logout';

                    if ($ilSetting->get('short_inst_name') != "")
                    {
                        $title_prefix = $ilSetting->get('short_inst_name');
                    }
                    else
                    {
                        $title_prefix = 'ILIAS';
                    }
                    $sso_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.perform_sso.html", true, true);
					$sso_tpl->setVariable('SPINNER_SRC', $this->pluginObj->getDirectory().'/templates/js/spin.js');
                    $sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
	                $sso_tpl->setVariable('LOGOUT_URL', str_replace(['http://', 'https://'], '//', $logout_url));
                    $sso_tpl->setVariable('URL', $url);
                    $sso_tpl->setVariable('INFO_TXT',$this->pluginObj->txt('redirect_in_progress'));
                    $sso_tpl->setVariable('OBJECT_TITLE',$this->object->getTitle());
                    $sso_tpl->show();
                    exit;
                }
            }
		}
		// Fallback action
		$this->showContent();
	}
	
		/**
	 * Shows meeting contents
	 *
	 * @param bool   $has_access
	 * @param string content|record $by_type
	 * @return string
	 */
	public function viewContents($has_access = false, $by_type = 'content')
	{
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');
        $this->pluginObj->includeClass('class.ilAdobeConnectContentTableGUI.php');
        $this->pluginObj->includeClass('class.ilXAVCPermissions.php');

        $server = ilAdobeConnectServer::getPresentationUrl();
		$this->tabs->activateTab('contents');

		$my_tpl = new ilTemplate($this->pluginObj->getDirectory().'/templates/tpl.meeting_content.html', true, true);

		if($this->object->readContents($by_type))
		{
			// Get contents and records
			$contents = $this->object->searchContent(NULL);

			if($has_access)
			{
				$view_mode = ilAdobeConnectContentTableGUI::MODE_EDIT;
			}

			$table = new ilAdobeConnectContentTableGUI($this, 'showContent', $by_type, $view_mode);

			$table->init();

			$data = array();
			$i    = 0;
			require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';
			foreach($contents as $content)
			{
				$content_type = $content->getAttributes()->getAttribute('type');
				if($content_type != $by_type)
				{
					continue;
				}

				$icon = $this->object->getContentIconAttribute($content->getAttributes()->getAttribute('sco-id'));
				
				if($icon[0] == 'archive' && $content->getAttributes()->getAttribute('date-end') == '' && $by_type == 'content')
				{
					// in this case, the content is a 'running' recording!!
					continue;
				}
				
				$data[$i]['title'] = $content->getAttributes()->getAttribute('name');
				$data[$i]['type']  = $content->getAttributes()->getAttribute('type') == 'record' ? $this->pluginObj->txt('record') : $this->pluginObj->txt('content');

				$auth_mode = ilAdobeConnectServer::getSetting('auth_mode');
				switch($auth_mode)
				{
					case ilAdobeConnectServer::AUTH_MODE_SWITCHAAI:
						$data[$i]['link'] = ilAdobeConnectServer::getSetting('cave')."?back=".$server.$content->getAttributes()->getAttribute('url');
						break;
					default:
						$data[$i]['rec_url'] = $server . $content->getAttributes()->getAttribute('url');
						$this->ctrl->setParameter($this, 'record_url', urlencode($data[$i]['rec_url']));
						$data[$i]['link'] = $this->ctrl->getLinkTarget($this, 'requestAdobeConnectContent');
				}

				$data[$i]['date_created'] = $content->getAttributes()->getAttribute('date-created')->getUnixTime();
				$data[$i]['description']  = $content->getAttributes()->getAttribute('description');
				if($has_access && $content_type == $by_type)
				{
					$content_id = $content->getAttributes()->getAttribute('sco-id');
					$this->ctrl->setParameter($this, 'content_id', $content_id);
					if($content_type == 'content')
					{
						$action = new ilAdvancedSelectionListGUI();
						$action->setId('asl_' . $content_id . mt_rand(1, 50));
						$action->setListTitle($this->lng->txt('actions'));
						$action->addItem($this->lng->txt('edit'), '', $this->ctrl->getLinkTarget($this, 'editItem'));
						$action->addItem($this->lng->txt('delete'), '', $this->ctrl->getLinkTarget($this, 'askDeleteContents'));

						$data[$i]['actions'] = $action->getHtml();
					}
					else
					{
						$data[$i]['actions'] = '';
					}
				}
				++$i;
			}
			$table->setData($data);

			$my_tpl->setVariable('CONTENT_TABLE', $table->getHTML());
		}
		return $my_tpl->get();

	}

    /**
     * Shows meeting records
     *
     * @access public
     */
    public function viewRecords($has_access = false, $by_type = 'record')
    {
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectRecordsTableGUI.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectContentTableGUI.php');
		$this->pluginObj->includeClass('class.ilXAVCPermissions.php');

		$server = ilAdobeConnectServer::getPresentationUrl();
		$this->tabs->activateTab('contents');

		$my_tpl = new ilTemplate($this->pluginObj->getDirectory().'/templates/tpl.meeting_content.html', true, true);

		if($this->object->readContents($by_type))
		{
			// Get contents and records
			$contents = $this->object->searchContent(NULL);

			if($has_access)
			{
				$view_mode = ilAdobeConnectContentTableGUI::MODE_EDIT;
			}

			$table = new ilAdobeConnectRecordsTableGUI($this, 'showContent', $by_type, $view_mode);

			$table->init();

			$data = array();
			$i    = 0;
			require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';
			foreach($contents as $content)
			{
				$content_type = $content->getAttributes()->getAttribute('type');
				if($content_type != $by_type)
				{
					continue;
				}

				$data[$i]['title'] = $content->getAttributes()->getAttribute('name');
				$data[$i]['type']  = $content->getAttributes()->getAttribute('type') == 'record' ? $this->pluginObj->txt('record') : $this->pluginObj->txt('content');

				$auth_mode = ilAdobeConnectServer::getSetting('auth_mode');
				switch($auth_mode)
				{
					case ilAdobeConnectServer::AUTH_MODE_SWITCHAAI:
						$data[$i]['link'] = $server . $content->getAttributes()->getAttribute('url');

					default:
						$data[$i]['rec_url'] = $server . $content->getAttributes()->getAttribute('url');
						$this->ctrl->setParameter($this, 'record_url', urlencode($data[$i]['rec_url']));
						$data[$i]['link'] = $this->ctrl->getLinkTarget($this, 'requestAdobeConnectContent');
				}

				$data[$i]['date_created'] = $content->getAttributes()->getAttribute('date-created')->getUnixTime();
				$data[$i]['description']  = $content->getAttributes()->getAttribute('description');
				if($has_access && $content_type == $by_type)
				{
					$content_id = $content->getAttributes()->getAttribute('sco-id');
					$this->ctrl->setParameter($this, 'content_id', $content_id);
//					if($content_type == 'content')
//					{
						$action = new ilAdvancedSelectionListGUI();
						$action->setId('asl_' . $content_id . mt_rand(1, 50));
						$action->setListTitle($this->lng->txt('actions'));
						$action->addItem($this->lng->txt('edit'), '', $this->ctrl->getLinkTarget($this, 'editRecord'));
						$action->addItem($this->lng->txt('delete'), '', $this->ctrl->getLinkTarget($this, 'askDeleteContents'));

						$data[$i]['actions'] = $action->getHtml();
//					}
//					else
//					{
//						$data[$i]['actions'] = '';
//					}
				}
				++$i;
			}
			$table->setData($data);

			$my_tpl->setVariable('CONTENT_TABLE', $table->getHTML());
		}
		return $my_tpl->get();


    }

	// CRS-GRP-MEMBER ADMINITRATION
	public function addCrsGrpMembers()
	{
		global $DIC;  
		$ilCtrl = $DIC->ctrl(); 
		$lng = $DIC->language();

		$this->pluginObj->includeClass('class.ilXAVCTableGUI.php');
		
		$this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("addCrsGrpMembers");

		$lng->loadLanguageModule('crs');

		$my_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.meeting_participant_table.html", true, true);
		
		$oParticipants = $this->object->getParticipantsObject();
		
		/** @var $oParticipants  ilGroupParticipants */
		$admins = $oParticipants->getAdmins();
		$tutors = $oParticipants->getTutors();
		$members = $oParticipants->getMembers();

		$all_crs_members = array_unique(array_merge($admins, $tutors, $members));

		$counter = 0;
		$f_result_1 = NULL;
		foreach($all_crs_members as $user_id)
		{
			if($user_id > 0)
			{
				$tmp_user = new ilObjUser($user_id);

				$firstname =  $tmp_user->getFirstname();
				$lastname =  $tmp_user->getLastname();

				if($tmp_user->hasPublicProfile() && $tmp_user->getPref('public_email') == 'y')
				{
					$user_mail = $tmp_user->getEmail();
				}
				else
				{
					$user_mail = '';
				}
			}

			$f_result_1[$counter]['checkbox'] = ilUtil::formCheckbox('','usr_id[]', $user_id);
			$f_result_1[$counter]['user_name'] = $lastname.', '.$firstname;
			$f_result_1[$counter]['email'] = $user_mail;
			++$counter;
		}

		// show Administrator Table
		$tbl_admin = new ilXAVCTableGUI($this,'addCrsGrpMembers');
		$ilCtrl->setParameter($this,'cmd', 'editParticipants');

		$tbl_admin->setTitle($lng->txt("crs_members"));
		$tbl_admin->setId('tbl_admins');
		$tbl_admin->setRowTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.meeting_participant_row.html", false);

		$tbl_admin->addColumn('', 'checkbox', '1%', true);
		$tbl_admin->addColumn($this->pluginObj->txt('user_name'), 'user_name','30%');
		$tbl_admin->addColumn($lng->txt('email'), 'email');
		$tbl_admin->setSelectAllCheckbox('usr_id[]');
		$tbl_admin->addMultiCommand('performAddCrsGrpMembers',$this->pluginObj->txt('add_crs_grp_members'));
		$tbl_admin->addCommandButton('editParticipants',$this->pluginObj->txt('cancel'));

		$tbl_admin->setData($f_result_1);
		$my_tpl->setVariable('ADMINS',$tbl_admin->getHTML());

		$this->tpl->setContent($my_tpl->get());
	}

	public function performAddCrsGrpMembers()
	{
		global $DIC;
		$lng = $DIC->language();
		
		if(!is_array($_POST['usr_id']) || !$_POST['usr_id'])
		{
		   ilUtil::sendFailure($this->txt('participants_select_one'));

		  return $this->addCrsGrpMembers();
		}
		$this->tabs->activateTab('participants');
		$lng->loadLanguageModule('crs');

		$this->object->addCrsGrpMembers($this->object->getRefId(), $this->object->getScoId(), $_POST['usr_id']);

		return $this->editParticipants();
	}

    /**
     *  Shows meeting hosts
     *
     */
    public function showXavcRoles()
    {
	    global $DIC;
	    $lng = $DIC->language();
		$ilCtrl = $DIC->ctrl(); 
		$ilToolbar = $DIC->toolbar();

		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');
		$this->pluginObj->includeClass('class.ilXAVCTableGUI.php');

		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

        $this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("editParticipants");

		$my_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.meeting_participant_table.html", true, true);

        // add members
		include_once 'Services/Search/classes/class.ilRepositorySearchGUI.php';
		$types = array(
			'add_member' => $this->lng->txt('member'),
			'add_admin'	=> $this->lng->txt('administrator')
		);

		ilRepositorySearchGUI::fillAutoCompleteToolbar(
			$this,
			$ilToolbar,
			array(
				'auto_complete_name'	=> $lng->txt('user'),
				'user_type'				=> $types,
				'submit_name'			=> $lng->txt('add')
			)
		);
		// add separator
		$ilToolbar->addSeparator();

		// search button
		$ilToolbar->addButton($this->lng->txt("crs_search_members"),
			$ilCtrl->getLinkTargetByClass('ilRepositorySearchGUI','start'));

		// GET Admins
		$admins = $xavcRoles->getCurrentAdministrators();

		$counter = 0;
		$f_result_1 = NULL;
		foreach($admins as $user)
		{
			$f_result_1[$counter]['checkbox'] = ilUtil::formCheckbox('','usr_id[]', $user['user_id']);
			$f_result_1[$counter]['user_name'] = $user['firstname'].' '.$user['lastname'];
			++$counter;
		}

		// show Administrator Table
		$tbl_admin = new ilXAVCTableGUI($this,'editParticipants');
		$ilCtrl->setParameter($this,'cmd', 'editParticipants');
		$tbl_admin->setTitle($lng->txt("crs_administrators"));
		$tbl_admin->setId('tbl_admins');
		$tbl_admin->setRowTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.xavc_active_user_row.html", false);

		$tbl_admin->addColumn('', 'checkbox', '1%', true);
		$tbl_admin->addColumn($this->pluginObj->txt('user_name'), 'user_name', '40%');
		$tbl_admin->setSelectAllCheckbox('usr_id[]');
		$tbl_admin->addMultiCommand('detachAdmin',$this->pluginObj->txt('detach_admin'));

		$tbl_admin->setData($f_result_1);
		$my_tpl->setVariable('ADMINS',$tbl_admin->getHTML());

		// GET MEMBERS TABLE
		$members = $xavcRoles->getCurrentMembers();
		$counter = 0;
		$f_result_2 = NULL;
		foreach($members as $user)
		{
			$f_result_2[$counter]['checkbox'] = ilUtil::formCheckbox('','usr_id[]', $user['user_id']);
			$f_result_2[$counter]['user_name'] = $user['firstname'].' '.$user['lastname'];
			++$counter;
		}

		// show Member Table
		$tbl_member = new ilXAVCTableGUI($this, 'editParticipants');
		$ilCtrl->setParameter($this,'cmd', 'editParticipants');

		$tbl_member->setTitle($lng->txt("members"));
		$tbl_member->setId('tbl_member');
		$tbl_member->setRowTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.xavc_active_user_row.html", false);

		$tbl_member->addColumn('', 'checkbox', '1%', true);
		$tbl_member->addColumn($this->pluginObj->txt('user_name'), 'user_name', '40%');
		$tbl_member->setSelectAllCheckbox('usr_id[]');
		$tbl_member->addMultiCommand('detachMember',$this->pluginObj->txt('detach_member'));
		$tbl_member->addMultiCommand('assignAdmin',$this->pluginObj->txt('assign_admin'));

		$tbl_member->setData($f_result_2);
		$my_tpl->setVariable('MEMBERS',$tbl_member->getHTML());

    	$this->tpl->setContent($my_tpl->get());
    }

	public function updateParticipants()
	{
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');

		if(!is_array($_POST['xavc_status']) || !is_array($_POST['usr_id']) )
		{
			ilUtil::sendFailure($this->txt('participants_select_one'));
			return $this->editParticipants();
		}
		
		$xavc_options = array(
			$this->txt("presenter")	=> "host",
			$this->txt("moderator") => "mini-host",
			$this->txt("participant")=> "view",
			$this->txt("denied")=> "denied"
		);

		if(isset($_POST['usr_id']))
		{
			foreach ($_POST['usr_id'] as $selected_user)
			{
				if(array_key_exists($selected_user, $_POST['xavc_status']))
				{
					$selected_status = $_POST['xavc_status'][$selected_user];
					$memberObj = new ilXAVCMembers($this->object->getRefId(), $selected_user);
					$memberObj->setStatus($xavc_options[$selected_status]);
					$memberObj->updateXAVCMember();

					$this->object->updateParticipant(ilXAVCMembers::_lookupXAVCLogin($selected_user),$memberObj->getStatus());
				}
			}
		}
		else
		if(!is_array($_POST['usr_id']))
		{
			ilUtil::sendInfo($this->txt('participants_select_one'));
			return $this->editParticipants();
		}
		return $this->editParticipants();
	}

	public function showMembersGallery()
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		
		$this->pluginObj->includeClass('class.ilAdobeConnectUsersGalleryCollectionProvider.php');
		$this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("showMembersGallery");

		$provider    = new ilAdobeConnectUsersGalleryCollectionProvider(ilAdobeConnectContainerParticipants::getInstanceByObjId($this->object->getId()));
		$gallery_gui = new ilUsersGalleryGUI($provider);
		$this->ctrl->setCmd('view');
		$gallery_gui->executeCommand();
		
		$tpl->getStandardTemplate();
		
		return;
	}
	
	public function requestAdobeConnectContent()
	{
		global $DIC;
		$ilSetting = $DIC->settings();
		
		if(!isset($_GET['record_url']) || !strlen($_GET['record_url']))
		{
			$this->showContent();
			return;
		}

		$url = ilUtil::stripSlashes($_GET['record_url']);

		$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectQuota.php');

		$ilAdobeConnectUser = new ilAdobeConnectUserUtil( $this->user->getId() );
		$ilAdobeConnectUser->ensureAccountExistance();

		$xmlAPI = ilXMLApiFactory::getApiByAuthMode();
		$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
		//login current user session
		$session = $xmlAPI->generateUserSessionCookie($xavc_login);
		$_SESSION['xavc_last_sso_sessid'] = $session;

		$url = ilUtil::appendUrlParameterString($url, 'session=' . $session);

		$presentation_url = ilAdobeConnectServer::getPresentationUrl(true);
		$logout_url = $presentation_url.'/api/xml?action=logout';

		if ($ilSetting->get('short_inst_name') != "")
		{
			$title_prefix = $ilSetting->get('short_inst_name');
		}
		else
		{
			$title_prefix = 'ILIAS';
		}
		
		$sso_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.perform_sso.html", true, true);
		$sso_tpl->setVariable('SPINNER_SRC', $this->pluginObj->getDirectory().'/templates/js/spin.js');
		$sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
		$sso_tpl->setVariable('LOGOUT_URL', str_replace(['http://', 'https://'], '//', $logout_url));
		$sso_tpl->setVariable('URL', $url);
		$sso_tpl->setVariable('INFO_TXT',$this->pluginObj->txt('redirect_in_progress'));
		$sso_tpl->setVariable('OBJECT_TITLE',$this->object->getTitle());
		$sso_tpl->show();
		exit;
	}

	/**
	* 1. Assign User by Login with Ilias-Role "Administrator/Member"
	* 2. Check if User already has a xavc-account
	* 3. Register new User at Adobe-Connect-Server or get xavc-login of existing user
	* 4. Assign user as Meetingroom-Participant
	*/
	public function addAsMember($user_ids, $type)
	{
		if(!$user_ids || !is_array($user_ids))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'), true);
			return false;
		}

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');

		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());
		$xavc_cur_users = $xavcRoles->getUsers();

		$added_users = 0;
		foreach($user_ids as $usr_id)
		{
			if(!ilObjUser::_lookupLogin($usr_id))
			{
				continue;
			}

			if(in_array($usr_id, $xavc_cur_users))
			{
				continue;
			}

			switch($type)
			{
				case 'add_admin':
					$xavcRoles->addAdministratorRole($usr_id);
                    break;
                case 'add_member':
                default:
                    $xavcRoles->addMemberRole($usr_id);
                    break;
            }
            $this->addParticipant($usr_id);
            ++$added_users;
		}
		ilUtil::sendSuccess($this->plugin->txt('assigned_users' . (count($added_users) == 1 ? '_s' : '_p')), true);
		$this->ctrl->redirectByClass('ilObjAdobeConnectGUI', 'editParticipants');
	}

/*
 * User joins XAVC_object
 */
	public function join()
	{
		global $DIC;
		$ilCtrl = $DIC->ctrl();

		$user_id = $this->user->getId();

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

		$current_users = $xavcRoles->getUsers();

		if(in_array($user_id, $current_users))
		{
			ilUtil::sendInfo($this->txt('already_member'));
		}
		if(!$user_id)
		{
			ilUtil::sendFailure($this->txt('user_not_known'));
			return $this->editParticipants();
		}

		$xavcRoles->addMemberRole($user_id);

		//check if there is an adobe connect account at the ac-server
		$ilAdobeConnectUser = new ilAdobeConnectUserUtil($user_id);
		$ilAdobeConnectUser->ensureAccountExistance();

		$role_map = ilAdobeConnectServer::getRoleMap();

		$status        = false;
		$oParticipants = null;
		$type          = '';
		$owner         = 0;

		if(count($this->object->getParticipantsObject()->getParticipants()) > 0)
		{
			$user_is_admin = $this->object->getParticipantsObject()->isAdmin($user_id);
			$user_is_tutor = $this->object->getParticipantsObject()->isTutor($user_id);
			$owner = ilObject::_lookupOwner($this->object->getParticipantsObject()->getObjId());
			if($owner == $this->user->getId())
			{
				$status = $role_map[$this->object->getParticipantsObject()->getType() . '_owner'];
			}
			else if($user_is_admin)
			{
				$status = $role_map[$this->object->getParticipantsObject()->getType() . '_admin'];
			}
			else if($user_is_tutor)
			{
				$status = $role_map[$this->object->getParticipantsObject()->getType() . '_tutor'];
			}
			else
			{
				$status = $role_map[$this->object->getParticipantsObject()->getType() . '_member'];
			}
		}

		if(!$status)
		{
			if($owner == $this->user->getId())
			{
				$status = 'host';
			}
			else
			{
				$status = 'view';
			}
		}

       	$is_member = ilXAVCMembers::_isMember($user_id, $this->object->getRefId());
		// local member table

		$xavcMemberObj = new ilXAVCMembers($this->object->getRefId(), $user_id);
		$xavcMemberObj->setStatus($status);
		$xavcMemberObj->setScoId($this->object->getScoId());

		if($is_member)
		{
			$xavcMemberObj->updateXAVCMember();
		}
		else
		{
			$xavcMemberObj->insertXAVCMember();
		}

		$this->object->updateParticipant(ilXAVCMembers::_lookupXAVCLogin($user_id), $status);

		if(ilAdobeConnectServer::getSetting('add_to_desktop') == 1)
		{
			ilObjUser::_addDesktopItem($user_id, $this->object->getRefId(), 'xavc');
		}

		$ilCtrl->setParameter($this, 'cmd', 'showContent');
		$ilCtrl->redirect($this, "showContent");
	}

	public function leave()
	{
		//TODO: CHECK THIS
		$user_id = $this->user->getId();
		$ref_id = $this->object->getRefId();

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');
		$xavcRoles = new ilAdobeConnectRoles($ref_id);

		$detach_user_ids[] = $user_id;

		$xavcRoles->detachMemberRole($user_id);
		ilXAVCMembers::deleteXAVCMember($user_id, $ref_id);

		$xavc_login = ilXAVCMembers::_lookupXAVCLogin($user_id);
        $this->object->deleteParticipant($xavc_login);

		ilUtil::sendInfo($this->txt('participants_detached_successfully'));
		return $this->showContent();
	}

	// detach member role
	public function detachMember()
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		
		$this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("editParticipants");

		if(!is_array($_POST['usr_id']))
		{
			ilUtil::sendInfo($this->txt('participants_select_one'));
			return $this->editParticipants();
		}
		// CONFIRMATION
		include_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$c_gui = new ilConfirmationGUI();

		$c_gui->setFormAction($this->ctrl->getFormAction($this, 'performDetachMember'));

		if(count($_POST['usr_id']) == 1)
		{
			$c_gui->setHeaderText($this->pluginObj->txt('sure_delete_participant_s'));
		}
		else if (count($_POST['usr_id']) > 1)
		{
			$c_gui->setHeaderText($this->pluginObj->txt('sure_delete_participant_p'));
		}

		$c_gui->setCancel($this->lng->txt('cancel'), 'editParticipants');
		$c_gui->setConfirm($this->lng->txt('confirm'), 'performDetachMember');

		foreach((array)$_POST['usr_id'] as $user_id)
		{
			$user_name = ilObjUser::_lookupName($user_id);
			$c_gui->addItem('usr_id[]', $user_id, $user_name['firstname'].' '.$user_name['lastname']);
		}

		$tpl->setContent($c_gui->getHTML());
	}

	public function performDetachMember()
	{
		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');
		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

		$detach_user_ids = array();
		$detach_user_ids = $_POST['usr_id'];

		foreach($detach_user_ids as $usr_id)
		{
			$is_admin = $xavcRoles->isAdministrator($usr_id);
			$xavcRoles->detachMemberRole($usr_id);
			if(!$is_admin)
			{
				$xavc_login = ilXAVCMembers::_lookupXAVCLogin($usr_id);
				ilXAVCMembers::deleteXAVCMember($usr_id, $this->object->getRefId());
				$this->object->deleteParticipant($xavc_login);
			}

			//remove from pd
			ilObjUser::_dropDesktopItem($usr_id, $this->object->getRefId(), 'xavc');
		}
		ilUtil::sendInfo($this->txt('participants_detached_successfully'));
		return $this->editParticipants();
	}

	// detach admin role
	public function detachAdmin()
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();

		$this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("editParticipants");

		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

		$cnt_current_admins = count($xavcRoles->getCurrentAdministrators());
		$cnt_selected_admins = count($_POST['usr_id']);

       if(!is_array($_POST['usr_id']))
		{
			ilUtil::sendInfo($this->txt('participants_select_one'));
			return $this->editParticipants();
		}

		if($cnt_selected_admins > 0 && $cnt_selected_admins == $cnt_current_admins)
		{
			// all administrators has been selected for detaching
			ilUtil::sendFailure($this->txt('at_least_one_admin'));
			return $this->editParticipants();
		}
		// CONFIRMATION
		include_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$c_gui = new ilConfirmationGUI();

		$c_gui->setFormAction($this->ctrl->getFormAction($this, 'performDetachAdmin'));
		$c_gui->setHeaderText($this->pluginObj->txt('sure_detach_admin'));
		$c_gui->setCancel($this->lng->txt('cancel'), 'editParticipants');
		$c_gui->setConfirm($this->lng->txt('confirm'), 'performDetachAdmin');

		foreach((array)$_POST['usr_id'] as $user_id)
		{
			$user_name = ilObjUser::_lookupName($user_id);
			$c_gui->addItem('usr_id[]', $user_id, $user_name['firstname'].' '.$user_name['lastname']);
		}

		$tpl->setContent($c_gui->getHTML());
	}

	public function performDetachAdmin()
	{
		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');
		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

		$cnt_current_admins = count($xavcRoles->getCurrentAdministrators());
		$cnt_selected_admins = count($_POST['usr_id']);

       if(!is_array($_POST['usr_id']))
		{
			ilUtil::sendInfo($this->txt('participants_select_one'));
			return $this->editParticipants();
		}

		if($cnt_selected_admins > 0 && $cnt_selected_admins == $cnt_current_admins)
		{
			// all administrators has been selected for detaching
			ilUtil::sendFailure($this->txt('at_least_one_admin'));
			return $this->editParticipants();
		}

        foreach($_POST['usr_id'] as $usr_id)
		{
			$xavcRoles->detachAdministratorRole($usr_id);
			$xavcRoles->addMemberRole($usr_id);
		}

		ilUtil::sendInfo($this->txt('participants_detached_successfully'));
		return $this->editParticipants();
	}

	// assign admin role
	public function assignAdmin()
	{
		$this->pluginObj->includeClass('class.ilAdobeConnectRoles.php');

		$xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

       if(!is_array($_POST['usr_id']))
		{
			ilUtil::sendInfo($this->txt('participants_select_one'));
			return $this->editParticipants();
		}

        foreach($_POST['usr_id'] as $usr_id)
		{
			$xavcRoles->detachMemberRole($usr_id);
			$xavcRoles->addAdministratorRole($usr_id);
		}

		ilUtil::sendInfo($this->txt('administrators_assigned_successfully'));
		return $this->editParticipants();
	}

    /**
     *  Add user to the Adobe Connect server

	 * @param integer $a_user_id
	 */
	public function addParticipant($a_user_id)
    {
        $this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
        $this->pluginObj->includeClass('class.ilXAVCMembers.php');

        $this->tabs->activateTab("participants");

        //check if there is an adobe connect account at the ac-server
        $ilAdobeConnectUser = new ilAdobeConnectUserUtil($a_user_id);
        $ilAdobeConnectUser->ensureAccountExistance();


		// add to desktop
		if(ilAdobeConnectServer::getSetting('add_to_desktop') == 1)
		{
			ilObjUser::_addDesktopItem($a_user_id, $this->object->getRefId(), 'xavc');
		}
		$is_member = ilXAVCMembers::_isMember($a_user_id, $this->object->getRefId());

		// local member table
		if(! $is_member )
		{
			$xavcMemberObj = new ilXAVCMembers($this->object->getRefId(), $a_user_id);
            $xavcMemberObj->setParticipantStatus();
            $xavcMemberObj->setScoId($this->object->getScoId());
            $xavcMemberObj->insertXAVCMember();

            $this->object->updateParticipant(ilXAVCMembers::_lookupXAVCLogin($a_user_id),$xavcMemberObj->getStatus());
			ilUtil::sendInfo($this->txt('participant_added_successfully'));
		}
		else if($is_member)
		{
			//only update at adobe connect server
			$this->object->updateParticipant(ilXAVCMembers::_lookupXAVCLogin($a_user_id),ilXAVCMembers::_lookupStatus($a_user_id, $this->object->getRefId()));
			ilUtil::sendInfo($this->pluginObj->txt('is_already_participant'));
		}
	}

    /**
     *
     * Inits a ilPropertyFormGUI for content search
     *
     * @access private
     *
     */
    private function initContentSearchForm()
    {
    	if($this->csform instanceof ilPropertyFormGUI)
    		return;

    	include_once 'Services/Form/classes/class.ilPropertyFormGUI.php';

    	$this->csform = new ilPropertyFormGUI();
        $this->csform->setFormAction($this->ctrl->getFormAction($this, 'searchContentFile'));
		$this->csform->setTitle($this->txt('add_content_from_ilias'));

        $textField = new ilTextInputGUI($this->txt('search_term'), 'search_query');
		$textField->setRequired(true);
		$this->csform->addItem($textField);

		$this->csform->addCommandButton('searchContentFile', $this->lng->txt('search'));
		$this->csform->addCommandButton('showContent', $this->txt('cancel'));
    }

    /**
     *
     * Shows add content form
     *
     * @access public
     *
     */
    public function showAddContent()
    {
        $this->tabs->activateTab('contents');
		/**
		 * @var $my_tpl $tpl
 		 */
		$my_tpl = new ilTemplate($this->pluginObj->getDirectory().'/templates/tpl.add_content.html', true, true);

        //Add content
		$this->initFormContent(self::CONTENT_MOD_ADD);
        $my_tpl->setVariable('FORM_ADD_CONTENT', $this->cform->getHTML());

        //Add content from ILIAS
        $this->initContentSearchForm();
		$my_tpl->setVariable('FORM_ADD_CONTENT_FROM_ILIAS', $this->csform->getHTML());

		$this->tpl->setContent($my_tpl->get());
    }

	/**
	 *  Adds a content to the Adobe Connect server
	 */
	public function addContent()
	{
		$this->initFormContent(self::CONTENT_MOD_ADD);
		if($this->cform->checkInput())
		{
			$this->pluginObj->includeClass('class.ilAdobeConnectDuplicateContentException.php');
			$this->pluginObj->includeClass('class.ilAdobeConnectContentUploadException.php');

			$fdata = $this->cform->getInput('file');

			$targetDir      = dirname(ilUtil::ilTempnam());
			$targetFilePath = $targetDir . '/' . $fdata['name'];

			ilUtil::moveUploadedFile($fdata['tmp_name'], $fdata['name'], $targetFilePath);
			try
			{
				$filemame = strlen($this->cform->getInput('tit')) ? $this->cform->getInput('tit') : $fdata['name'];
				$url = $this->object->addContent($filemame, $this->cform->getInput('des'));
				if(!strlen($url))
				{
					throw new ilAdobeConnectContentUploadException('add_cnt_err');
				}

				$this->object->uploadFile($url, $targetFilePath);

				@unlink($targetFilePath);
				ilUtil::sendSuccess($this->txt('virtualClassroom_content_added'));
			}
			catch(ilAdobeConnectException $e)
			{
				@unlink($targetFilePath);
				ilUtil::sendFailure($this->txt($e->getMessage()));
				$this->cform->setValuesByPost();
				return $this->showAddContent();
			}
		}
		else
		{
			$this->cform->setValuesByPost();
			return $this->showAddContent();
		}

		$this->showContent();
	}

    /**
     *
     * Called if the user clicked the cancel button of the content search result table
     *
     * @access public
     *
     */
    public function cancelSearchContentFile()
    {
    	unset($_SESSION['contents']['search_result']);
    	return $this->showAddContent();
    }

    /**
     *  Search content that belongs to the current user and that meet the search criteria
     *
     */
    public function searchContentFile()
    {
		global $DIC; 
		$ilAccess = $DIC->access();

    	$this->initContentSearchForm();
    	if($this->csform->checkInput())
    	{
    		$allowedExt = array(
	        	'ppt', 'pptx', 'flv', 'swf', 'pdf', 'gif',
	        	'jpg', 'png', 'mp3', 'html'
	        );

	        $result = array();

    		include_once './Services/Search/classes/class.ilSearchSettings.php';
			if(ilSearchSettings::getInstance()->enabledLucene())
			{
				include_once './Services/Search/classes/Lucene/class.ilLuceneSearcher.php';
				include_once './Services/Search/classes/Lucene/class.ilLuceneQueryParser.php';
				$qp = new ilLuceneQueryParser( '+(type:file) '.$this->csform->getInput('search_query') );
				$qp->parse();
				$searcher = ilLuceneSearcher::getInstance( $qp );
				$searcher->search();

				include_once './Services/Search/classes/Lucene/class.ilLuceneSearchResultFilter.php';
				include_once './Services/Search/classes/Lucene/class.ilLucenePathFilter.php';
				$filter = ilLuceneSearchResultFilter::getInstance( $this->user->getId() );
				$filter->addFilter(new ilLucenePathFilter( ROOT_FOLDER_ID ));
				$filter->setCandidates( $searcher->getResult() );
				$filter->filter();

				foreach($filter->getResultIds() as $refId => $objId)
	            {
	            	$obj = ilObjectFactory::getInstanceByRefId($refId);

					if(!in_array(strtolower($obj->getFileExtension()), $allowedExt))
					{
						continue;
					}

					if(!$ilAccess->checkAccessOfUser($this->user->getId(), 'read', '', $refId, '', $objId))
					{
						continue;
					}

					$result[$obj->getId()] = $obj->getId();
	            }
			}
			else
			{
		    	include_once 'Services/Search/classes/class.ilQueryParser.php';

				$query_parser = new ilQueryParser( $this->csform->getInput('search_query') );
				$query_parser->setCombination( QP_COMBINATION_OR );
				$query_parser->parse();
				if( !$query_parser->validate() )
				{
					ilUtil::sendInfo( $query_parser );
					$this->csform->setValuesByPost();
					return $this->showAddContent();
				}

				include_once 'Services/Search/classes/Like/class.ilLikeObjectSearch.php';
				$object_search = new ilLikeObjectSearch( $query_parser );

				$object_search->setFilter( array('file') );

				$res = $object_search->performSearch();
				$res->setUserId( $this->user->getId() );
				$res->setMaxHits( 999999 );
				$res->filter(ROOT_FOLDER_ID, false);
				$res->setRequiredPermission('read');

				foreach($res->getUniqueResults() as $entry)
				{
					$obj = ilObjectFactory::getInstanceByRefId($entry['ref_id']);

					if(!in_array(strtolower($obj->getFileExtension()), $allowedExt))
						continue;

					$result[$obj->getId()] = $obj->getId();
				}
			}

	        if(count($result) > 0)
	        {
	            $this->showFileSearchResult($result);
	            $_SESSION['contents']['search_result'] = $result;
	        }
	        else
	        {
	            ilUtil::sendInfo($this->txt('files_matches_in_no_results'));
	            $this->csform->setValuesByPost();
				$this->showAddContent();
	        }
    	}
    	else
    	{
    		$this->csform->setValuesByPost();
        	return $this->showAddContent();
    	}
    }

    /**
     * Shows $results in a table
     *
     * @param array $results
     * @access public
     */
    public function showFileSearchResult($results = null)
    {
		global $DIC;
		$tree = $DIC->repositoryTree();
		
		if(!$results && isset($_SESSION['contents']['search_result']))
		{
			// this is for table sorting  
			$results = $_SESSION['contents']['search_result'];
		}
    	if(!$results)
    		return $this->showAddContent();

        include_once 'Services/Table/classes/class.ilTable2GUI.php';

        $this->tabs->activateTab('contents');

        $table = new ilTable2GUI($this, 'showFileSearchResult');

        $table->setLimit( 2147483647 );

        $table->setTitle($this->txt('files'));

		$table->setDefaultOrderField('path');
		
        $table->addColumn('', '', '1%', true);
        $table->addColumn($this->txt('title'), 'title', '30%');
		$table->addColumn($this->lng->txt('path'), 'path', '70%');

        $table->setFormAction($this->ctrl->getFormAction($this, 'addContentFromILIAS'));

        $table->setRowTemplate('tpl.content_file_row.html', $this->pluginObj->getDirectory());

		$table->setId('xavc_cs_'.$this->object->getId());
		$table->setPrefix('xavc_cs_'.$this->object->getId());

		$table->addCommandButton('addContentFromILIAS', $this->txt('add'));
		$table->addCommandButton('cancelSearchContentFile', $this->txt('cancel'));

        $data = array();
        $i = 0;
		
        foreach($results as $file_id)
        {
            $title = ilObject::_lookupTitle($file_id);

			$file_ref = array_shift(ilObject::_getAllReferences($file_id));
			$path_arr = $tree->getPathFull($file_ref);
			$counter = 0;
			$path = '';
			foreach($path_arr as $element)
			{
				if($counter++)
				{
					$path .= " > ";
					$path .= $element['title'];
				}
				else
				{
					$path .= $this->lng->txt('repository');
				}
			}
			
			$data[$i]['check_box'] = ilUtil::formRadioButton(0, 'file_id', $file_id);
        	$data[$i]['title'] = $title;
			$data[$i]['path'] = $path;

            ++$i;
        }
        $table->setData($data);

        $this->tpl->setContent($table->getHTML());
    }

    /**
     *  Add a content from ILIAS to the Adobe Connect server
     *
     * @return string cmd
     */
    public function addContentFromILIAS()
    {
    	require_once('Modules/File/classes/class.ilFSStorageFile.php');
		require_once('Modules/File/classes/class.ilObjFileAccess.php');

		if(!((int)$_POST['file_id']))
		{
		    ilUtil::sendInfo($this->txt('content_select_one'));
		    return $this->showFileSearchResult($_SESSION['contents']['search_result']);
		}

		/** @noinspection PhpUndefinedClassInspection */
		$fss = new ilFSStorageFile((int)$_POST['file_id']);

		/** @noinspection PhpUndefinedClassInspection */
		$version_subdir = '/'.sprintf("%03d", ilObjFileAccess::_lookupVersion($_POST['file_id']));
		include_once './Modules/File/classes/class.ilObjFile.php';
		$file_name = ilObjFile::_lookupFileName((int)$_POST['file_id']);
		$object_title = ilObject::_lookupTitle((int)$_POST['file_id']);

		$file = $fss->getAbsolutePath().$version_subdir.'/'.$file_name;

		$this->pluginObj->includeClass('class.ilAdobeConnectDuplicateContentException.php');
		try
		{
			$this->object->uploadFile($this->object->addContent($object_title, ''), $file, $object_title);
			unset($_SESSION['contents']['search_result']);
			ilUtil::sendSuccess($this->txt('virtualClassroom_content_added'));
			return $this->showContent();
		}
		catch(ilAdobeConnectDuplicateContentException $e)
		{
			ilUtil::sendFailure($this->txt($e->getMessage()));
			return $this->showFileSearchResult($_SESSION['contents']['search_result']);
		}
    }

    /**
     * Shows edit content form
     *
     * @access public
     */
    public function editItem()
    {
		global $DIC; 
		$ilCtrl = $DIC->ctrl();

        $this->tabs->activateTab('contents');

		$this->initFormContent(self::CONTENT_MOD_EDIT, (int)$_GET['content_id']);

		if($ilCtrl->getCmd() == 'editItem' || $ilCtrl->getCmd() == 'editRecord')
		{
			$this->setValuesFromContent((int)$_GET['content_id']);
		}
		$this->tpl->setContent($this->cform->getHTML());
    }

	public function editRecord()
	{
			$this->is_record = true;
			$this->editItem();
	}

	/**
	 * 
	 */
	protected function updateRecord()
	{
		$this->is_record = true;
		$this->updateContent();
	}

    /**
     * Updates a content on the Adobe Connect server
	 *
     * @access public
     */
	public function updateContent()
	{
		$this->initFormContent(self::CONTENT_MOD_EDIT, (int)$_GET['content_id']);
		if($this->cform->checkInput())
		{
			$this->pluginObj->includeClass('class.ilAdobeConnectDuplicateContentException.php');
			$this->pluginObj->includeClass('class.ilAdobeConnectContentUploadException.php');

			$fdata = $this->cform->getInput('file');
			$target= '';
			if($fdata['name'] != '')
			{
				$target = dirname(ilUtil::ilTempnam());
				ilUtil::moveUploadedFile($fdata['tmp_name'], $fdata['name'], $target.'/'.$fdata['name']);
			}

			try
			{
				$title = strlen($this->cform->getInput('tit')) ? $this->cform->getInput('tit') : $fdata['name'];
				$this->object->updateContent((int)$_GET['content_id'], $title, $this->cform->getInput('des'));
				if($fdata['name'] != '')
				{
					$this->object->uploadFile($this->object->uploadContent((int)$_GET['content_id']), $target. '/'. $fdata['name']);
					@unlink($target.'/'.$fdata['name']);
				}
				ilUtil::sendSuccess($this->txt('virtualClassroom_content_updated'), true);
				$this->showContent();
				return true;
			}
			catch(ilAdobeConnectException $e)
			{
				if($fdata['name'] != '')
				{
					@unlink($target.'/'.$fdata['name']);
				}
				ilUtil::sendFailure($this->txt($e->getMessage()));
				$this->cform->setValuesByPost();
				return $this->editItem();
			}
		}
		else
		{
			$this->cform->setValuesByPost();
			return $this->editItem();
		}
	}

    /**
     * Shows a message giving user confirmation to delete contents
	 *
     * @access public
     */
    public function askDeleteContents()
    {
		$content_ids = array();
		if(is_array($_POST['content_id']) && count($_POST['content_id']) > 0)
		{
			$content_ids = $_POST['content_id'];
		}
		else if(isset($_GET['content_id']) && (int)$_GET['content_id'] > 0 )
		{
			$content_ids[] = $_GET['content_id'];
		}

        if(count($content_ids) == 0)
		{
			ilUtil::sendFailure($this->txt('content_select_one'));
			$this->showContent();

			return true;
		}

        $this->tabs->activateTab('contents');

        include_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this, 'showContent'));
		$confirm->setHeaderText($this->txt('sure_delete_contents'));
		$confirm->setConfirm($this->txt('delete'), 'deleteContents');
		$confirm->setCancel($this->txt('cancel'), 'showContent');

		$this->object->readContents();

		$contents_found = false;
        foreach($content_ids as $content_id)
		{
            $content = $this->object->getContent($content_id);
			if(!$content)
			{
				continue;
			}
			$contents_found = true;
            $confirm->addItem('content_id[]', $content_id, $content->getAttributes()->getAttribute('name'));
		}
		
		if($contents_found)
		{
			$this->tpl->setContent($confirm->getHTML());
		}
		else
		{
			return $this->showContent();
		}
    }

    /**
     * Deletes a content from Adobe Connect server
     *
     * @access public
     */
    public function deleteContents()
    {
    	$content_ids = $_POST['content_id'];
        if(!$content_ids)
		{
			ilUtil::sendFailure($this->txt('content_select_one'));
			$this->showContent();
			return true;
		}

        foreach($content_ids as $content_id)
		{
			$this->object->deleteContent($content_id);
		}

		ilUtil::sendSuccess($this->txt('virtualClassroom_content_deleted'), true);
		$this->showContent();
		return true;
    }

    /**
     * Shows a form to add or edit content
     *
     * @param int $a_mode
     * @param int $a_content_id optional content id
     * @access protected
     */
    protected function initFormContent($a_mode, $a_content_id = 0)
    {
    	if($this->cform instanceof ilPropertyFormGUI)
    		return;

        include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$this->cform = new ilPropertyFormGUI();

        switch ($a_mode)
        {
            case self::CONTENT_MOD_EDIT:
				$positive_cmd = ($this->is_record ? 'updateRecord' : 'updateContent');

				// Header
				$this->ctrl->setParameter($this,'content_id',(int) $_REQUEST['content_id']);
				$this->cform->setTitle($this->txt('edit_content'));
				// Buttons
				$this->cform->addCommandButton($positive_cmd, $this->txt('save'));
				$this->cform->addCommandButton('showContent', $this->txt('cancel'));

				// Form action
				if($a_content_id)
					$this->ctrl->setParameter($this, 'content_id', $a_content_id);
        		$this->cform->setFormAction($this->ctrl->getFormAction($this, $positive_cmd));
				break;
            case self::CONTENT_MOD_ADD:
                // Header
				$this->cform->setTitle($this->txt('add_new_content'));
				// Buttons
				$this->cform->addCommandButton('addContent', $this->txt('save'));
				$this->cform->addCommandButton('showContent', $this->txt('cancel'));

				// Form action
        		$this->cform->setFormAction($this->ctrl->getFormAction($this, 'addContent'));
				break;
        }

        // Title
		$tit = new ilTextInputGUI($this->txt('title'), 'tit');
//		$tit->setRequired(true);
		$tit->setSize(40);
		$tit->setMaxLength(127);
		$this->cform->addItem($tit);

        // Description
		$des = new ilTextAreaInputGUI($this->txt('description'), 'des');
		$des->setRows(3);
		$this->cform->addItem($des);

		if($this->is_record == false)
		{
			// File
			$fil = new ilFileInputGUI($this->txt('file'), 'file');
			if($a_mode == self::CONTENT_MOD_ADD)
				$fil->setRequired(true);
	
			$content_file_types = strlen(ilAdobeConnectServer::getSetting('content_file_types')) > 1 ? ilAdobeConnectServer::getSetting('content_file_types'): 'ppt, pptx, flv, swf, pdf, gif, jpg, png, mp3, html';
			$fil->setSuffixes(explode(',', str_replace(' ', '', $content_file_types)));
			$this->cform->addItem($fil);
		}
    }

    /**
     *  Initializes the form
     *
     * @param String $a_content_id
     */
    protected function setValuesFromContent($a_content_id)
	{
        $this->object->readContents();
		$contents = $this->object->searchContent(array('sco-id' => $a_content_id));
		/** @var $content ilAdobeConnectContent */
        $content = $contents[0];

		$this->cform->setValuesByArray(
			array(
				'tit'		=> $content->getAttributes()->getAttribute('name'),
				'des'		=> $content->getAttributes()->getAttribute('description'),
			)
		);
	}
	/**
	 * @param string $type
	 * @return array
	 */
	protected function initCreationForms($type)
	{
		include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		$templates = ilSettingsTemplate::getAllSettingsTemplates("xavc");
		sort($templates);

		$selected_templates = unserialize(ilAdobeConnectServer::getSetting('obj_creation_settings'));

		$creation_forms = array();

		if($templates)
		{
			$key = 100;
			foreach($templates as $item)
			{
				if($selected_templates == false)
				{
					$creation_forms[$key] =  $this->initCreateForm($item);
					$key++;
				}
				else
				if(is_array($selected_templates) && in_array($item['id'], $selected_templates))
				{
					$creation_forms[$key] =  $this->initCreateForm($item);
					$key++;
				}
			}
		}

		return $creation_forms;
	}

	public function initCreateForm($item)
	{
		global $DIC;
		$ilUser = $DIC->user();
		
		
		$settings = ilAdobeConnectServer::_getInstance();
		//Login User - this creates a user if he not exists.
		if($settings->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI)
		{
			$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');

			$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
			$ilAdobeConnectUser->loginUser();
		}

		$this->pluginObj->includeClass('class.ilObjAdobeConnect.php');
		if(isset($_POST['tpl_id']) && (int)$_POST['tpl_id'] > 0)
		{
			$item['id'] = $_POST['tpl_id'];
		}
		$template_settings = array();
		if($item['id'])
		{
			include_once "Services/Administration/classes/class.ilSettingsTemplate.php";

			$template          = new ilSettingsTemplate($item['id']);
			$template_settings = $template->getSettings();
		}

		$form = new ilPropertyFormGUI();
		$form->setTitle($this->pluginObj->txt($item['title']));
//        login to ac-server
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		// title
		$title = new ilTextInputGUI($this->pluginObj->txt("title"), "title");
		$title->setRequired(true);

		// description
		$description = new ilTextAreaInputGUI($this->pluginObj->txt("description"), "desc");

		// contact_info_val
		$civ = array();
		if($ilUser->getPref('public_profile') == "y")
		{
			if($ilUser->getPref('public_title'))
			{
				$civ_title = $ilUser->getUTitle().' ';
			}

			$civ[] = $civ_title.$ilUser->getFirstname().' '.$ilUser->getLastname();
			if($ilUser->getPref('public_email'))
			{
				$civ[] = $ilUser->getEmail();
			}
			if($ilUser->getPref('public_phone_office') && strlen($ilUser->getPhoneOffice()) > 1)
			{
				$civ[] = $this->pluginObj->txt('office').': '.$ilUser->getPhoneOffice();
			}
			if($ilUser->getPref('public_phone_mobile') && strlen($ilUser->getPhoneMobile()) > 1)
			{
				$civ[] = $this->pluginObj->txt('mobile').': '.$ilUser->getPhoneMobile();
			}
		}

		$contact_info_value = implode(', ',$civ);

		// owner
		$owner = new ilTextInputGUI($this->lng->txt("owner"), "owner");
		$owner->setInfo($this->pluginObj->txt('owner_info'));

		$owner->setValue(ilObjUser::_lookupLogin($ilUser->getId()));

		$radio_time_type = new ilRadioGroupInputGUI($this->pluginObj->txt('time_type_selection'), 'time_type_selection');
		
		// option: permanent room
		if(ilAdobeConnectServer::getSetting('enable_perm_room','1'))
		{
			$permanent_room            = new ilRadioOption($this->pluginObj->txt('permanent_room'), 'permanent_room');
			$permanent_room->setInfo($this->pluginObj->txt('permanent_room_info'));
			$radio_time_type->addOption($permanent_room);
			$radio_time_type->setValue('permanent_room');
		}
		// option: date selection
		$opt_date = new ilRadioOption( $this->pluginObj->txt('start_date'), 'date_selection');
		if($template_settings['start_date']['hide'] == '0')
		{
			// start date
			$sd = new ilDateTimeInputGUI($this->pluginObj->txt("start_date"), "start_date");

			$serverConfig = ilAdobeConnectServer::_getInstance();

			$now = strtotime('+3 minutes');
			$minTime      = new ilDateTime($now + $serverConfig->getScheduleLeadTime() * 60 * 60, IL_CAL_UNIX);

			$sd->setDate($minTime);
			$sd->setShowTime(true);
			$sd->setRequired(true);
			$opt_date->addSubItem($sd);
		}

		if($template_settings['duration']['hide'] == '0')
		{
			$duration = new ilDurationInputGUI($this->pluginObj->txt("duration"), "duration");
			$duration->setRequired(true);
			$duration->setHours('2');
			$opt_date->addSubItem($duration);
		}
		$radio_time_type->addOption($opt_date);
		$radio_time_type->setRequired(true);
		if(!ilAdobeConnectServer::getSetting('enable_perm_room','1'))
		{
			$radio_time_type->setValue('date_selection');
		}

		// access-level of the meeting room
		$radio_access_level = new ilRadioGroupInputGUI($this->pluginObj->txt('access'), 'access_level');
		$opt_private = new ilRadioOption($this->pluginObj->txt('private_room'), ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE);
		$opt_protected = new ilRadioOption($this->pluginObj->txt('protected_room'), ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED);
		$opt_public = new ilRadioOption($this->pluginObj->txt('public_room'), ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC);

		$radio_access_level->addOption($opt_private);
		$radio_access_level->addOption($opt_protected);
		$radio_access_level->addOption($opt_public);
		$radio_access_level->setValue( ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED);
		
		$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
		$ilAdobeConnectUser->ensureAccountExistance();
		$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
		$folder_id  =  $ilAdobeConnectUser->ensureUserFolderExistance($xavc_login);
		
		if($template_settings['reuse_existing_rooms']['hide'] == '0')
		{
			$all_scos   = (array)ilObjAdobeConnect::getScosByFolderId($folder_id);
			$local_scos = (array)ilObjAdobeConnect::getLocalScos();
			$free_scos  = array();
			if($all_scos)
			{
				foreach($all_scos as $sco)
				{
					$sco_ids[] = $sco['sco_id'];
				}

				$free_scos = array_diff($sco_ids, $local_scos);
			}

			if(!$free_scos)
			{
				$hidden_creation_type = new ilHiddenInputGUI('creation_type');
				$hidden_creation_type->setValue('new_vc');
				$form->addItem($hidden_creation_type);

				$advanced_form_item = $form;
				$afi_add_method     = 'addItem';
			}
			else
			{
				$radio_grp = new ilRadioGroupInputGUI($this->pluginObj->txt('choose_creation_type'), 'creation_type');
				$radio_grp->setRequired(true);

				$radio_new      = new ilRadioOption($this->pluginObj->txt('create_new'), 'new_vc');
				$radio_existing = new ilRadioOption($this->pluginObj->txt('select_existing'), 'existing_vc');

				$radio_grp->setValue('new_vc');
				$radio_grp->addOption($radio_new);

				$advanced_form_item = $radio_new;
				$afi_add_method     = 'addSubItem';
			}

			$advanced_form_item->{$afi_add_method}($title);
			$advanced_form_item->{$afi_add_method}($description);

			$contact_info = new ilTextAreaInputGUI($this->pluginObj->txt("contact_information"), "contact_info");
			$contact_info->setRows(self::CREATION_FORM_TA_ROWS);
			$contact_info->setValue($contact_info_value);
			$advanced_form_item->{$afi_add_method}($contact_info);

			$instructions = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions');
			$instructions->setRows(self::CREATION_FORM_TA_ROWS);
			$advanced_form_item->{$afi_add_method}($instructions);

			if($template_settings['access_level']['hide'] == 0)
			{
				$advanced_form_item->{$afi_add_method}($radio_access_level);
			}
			$advanced_form_item->{$afi_add_method}($radio_time_type);
			$advanced_form_item->{$afi_add_method}($owner);

			if($free_scos && $radio_existing)
			{
				$radio_existing = new ilRadioOption($this->pluginObj->txt('select_existing'), 'existing_vc');
				$radio_grp->addOption($radio_existing);
				$form->addItem($radio_grp);

				foreach($free_scos as $fs)
				{
					$options[$fs] = $all_scos[$fs]['sco_name'];
				}
				$available_rooms = new ilSelectInputGUI($this->pluginObj->txt('available_rooms'), 'available_rooms');
				$available_rooms->setOptions($options);
				$available_rooms->setInfo($this->pluginObj->txt('choose_existing_room'));
				$radio_existing->addSubItem($available_rooms);

				$instructions_3 = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions_3');
				$instructions_3->setRows(self::CREATION_FORM_TA_ROWS);
				$radio_existing->addSubItem($instructions_3);

				$contact_info_3 = new ilTextAreaInputGUI($this->pluginObj->txt("contact_information"), "contact_info_3");
				$contact_info_3->setValue($contact_info_value);
				$contact_info_3->setRows(self::CREATION_FORM_TA_ROWS);
				$radio_existing->addSubItem($contact_info_3);
			}
			else
			{
				//$info = new ilNonEditableValueGUI($this->pluginObj->txt('no_available_rooms'));
				//$radio_existing->addSubItem($info);
			}
		}
		else
		{
			$form->addItem($title);
			$form->addItem($description);

			$contact_info_2 = new ilTextAreaInputGUI($this->pluginObj->txt("contact_information"), "contact_info_2");
			$contact_info_2->setRows(self::CREATION_FORM_TA_ROWS);
			$contact_info_2->setValue($contact_info_value);
			$form->addItem($contact_info_2);

			if($template_settings['access_level']['hide'] == 0)
			{
				$form->addItem($radio_access_level);
			}

			$instructions_2 = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions_2');
			$instructions_2->setRows(self::CREATION_FORM_TA_ROWS);
			$form->addItem($instructions_2);
			if(ilAdobeConnectServer::getSetting('default_perm_room') && ilAdobeConnectServer::getSetting('enable_perm_room', '1') )
			{
				$info_text =  $this->pluginObj->txt('smpl_permanent_room_enabled');
			}
			else
			{
				$time = date('H:i', strtotime('+2 hours'));
				$info_text = sprintf($this->pluginObj->txt('smpl_permanent_room_disabled'), $time );
			}
			$info = new ilNonEditableValueGUI($this->lng->txt('info'), 'info_text');
			$info->setValue($info_text);
			$form->addItem($info);
		}

		$tpl_id = new ilHiddenInputGUI('tpl_id');
		$tpl_id->setValue($item['id']);
		$form->addItem($tpl_id);

		if(ilAdobeConnectServer::getSetting('use_meeting_template'))
		{
			$use_meeting_template = new ilCheckboxInputGUI($this->pluginObj->txt('use_meeting_template'), 'use_meeting_template');
			$use_meeting_template->setChecked(true);
			$form->addItem($use_meeting_template);
		}
		
		// language selector
		$lang_selector = new ilSelectInputGUI($this->lng->txt('language'), 'ac_language');
		$adobe_langs = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'tr', 'ru', 'ja', 'zh', 'ko'];
		$this->lng->loadLanguageModule('meta');
		foreach($adobe_langs as $lang)
		{
			$lang_options[$lang] = $this->lng->txt('meta_l_'.$lang);
		}
		$lang_selector->setOptions($lang_options);
		$form->addItem($lang_selector);
		
		$form->addCommandButton("save", $this->pluginObj->txt($this->getType()."_add"));
		$form->addCommandButton("cancelCreation", $this->lng->txt("cancel"));

		$form->setFormAction($this->ctrl->getFormAction($this));

		return $form;
	}

	public function getFreeMeetingSlotTable($meetings)
	{
		$meetingsByDay = array();

		$srv           = ilAdobeConnectServer::_getInstance();
		$buffer_before = $srv->getBufferBefore();
		$buffer_after  = $srv->getBufferAfter();

		foreach($meetings as $m)
		{
			if($this->object && $this->object->getId() && $m->id == $this->object->getId())
			{
				continue;
			}

			$day0s = date('Y-m-d', $m->start_date - $buffer_before);
			$day1s = date('Y-m-d', $m->end_date + $buffer_after);
			$day1  = strtotime($day1s);

			if($day0s == $day1s)
			{
				// why?	....condition results false everytime.....
				$h0 = date('H', $m->start_date - $buffer_before) * 60 + floor(date('i', $m->start_date - $buffer_before) / 15) * 15;
				$h1 = date('H', $m->end_date + $buffer_after) * 60 + floor(date('i', $m->end_date + $buffer_after) / 15) * 15;

				for($i = $h0; $i <= $h1; $i += 15)
				{
					$meetingsByDay[$day0s][(string)$i][] = $m;
				}
			}
			else
			{
				$h0 = date('H', $m->start_date - $buffer_before) * 60 + floor(date('i', $m->start_date - $buffer_before) / 15) * 15;
				$h1 = 23 * 60 + 45;

				for($i = $h0; $i <= $h1; $i += 15)
				{
					$meetingsByDay[$day0s][(string)$i][] = $m;
				}

//				$t = strtotime($day0s);

				$h0 = '0';
				$h1 = date('H', $m->end_date + $buffer_after) * 60 + floor(date('i', $m->end_date + $buffer_after) / 15) * 15;

				for($i = $h0; $i <= $h1; $i += 15)
				{
					$meetingsByDay[$day1s][(string)$i][] = $m;
				}
			}
		}

		// aggregate
		foreach($meetingsByDay as $date_day => $day_hours)
		{
			foreach($day_hours as $day_hour => $hour_meetings)
			{
				$meetingsByDay[$date_day][(string)$day_hour] = count($hour_meetings);
			}
		}

		return $meetingsByDay;
	}

	/**
	 * Save object
	 * @access    public
	 */
	public function save()
	{
		global $DIC;
		$rbacsystem = $DIC->rbac()->system(); 
		$objDefinition = $DIC['objDefinition']; 
		$lng = $DIC->language();

		$new_type = $_POST["new_type"] ? $_POST["new_type"] : $_GET["new_type"];

		// create permission is already checked in createObject. This check here is done to prevent hacking attempts
		if(!$rbacsystem->checkAccess("create", (int)$_GET["ref_id"], $new_type))
		{
			$this->ilias->raiseError($this->lng->txt("no_create_permission"), $this->ilias->error_obj->MESSAGE);
		}
		$this->ctrl->setParameter($this, "new_type", $new_type);
		if(isset($_POST['tpl_id']) && (int)$_POST['tpl_id'] > 0)
		{
			$tpl_id = (int)$_POST['tpl_id'];
		}
		else
		{
			$this->ilias->raiseError($this->lng->txt("no_template_id_given"), $this->ilias->error_obj->MESSAGE);
		}

		include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
		$templates = ilSettingsTemplate::getAllSettingsTemplates("xavc");

		foreach($templates as $template)
		{
			if($template['id'] == $tpl_id)
			{
				$form = $this->initCreateForm($template);
				$selected_template = $template;

				$template_settings = array();
				if($template['id'])
				{
					$objTemplate          = new ilSettingsTemplate($template['id']);
					$template_settings = $objTemplate->getSettings();
				}
			}
		}

		if($form->checkInput())
		{
			if($form->getInput('creation_type') == 'existing_vc' && $template_settings['reuse_existing_rooms']['hide'] == '0')
			{
				try
				{
					$location = $objDefinition->getLocation($new_type);

					$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
					$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());

					$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
					$folder_id =  $ilAdobeConnectUser->ensureUserFolderExistance($xavc_login);
                    $sco_ids  =  ilObjAdobeConnect::getScosByFolderId($folder_id);

					$title       = $sco_ids[$form->getInput('available_rooms')]['sco_name'];
					$description = $sco_ids[$form->getInput('available_rooms')]['description'];

					// create and insert object in objecttree
					$class_name = "ilObj" . $objDefinition->getClassName($new_type);
					include_once($location . "/class." . $class_name . ".php");
					/** @var $newObj ilObjAdobeConnect */
					$newObj = new $class_name();
					$newObj->setType($new_type);
					$newObj->setTitle(ilUtil::stripSlashes($title));
					$newObj->setDescription(ilUtil::stripSlashes($description));
					$newObj->setUseMeetingTemplate($form->getInput('use_meeting_template'));
					$newObj->create();
					$newObj->createReference();
					$newObj->putInTree($_GET["ref_id"]);
					$newObj->setPermissions($_GET["ref_id"]);
					ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
					$this->afterSave($newObj);
					return;
				}
				catch(Exception $e)
				{
					ilUtil::sendFailure($e->getMessage(), true);
				}
			}
			else // create new object
			{
				global $DIC; 
				$ilUser = $DIC->user();
				
				$owner = $ilUser->getId();

				if(strlen($form->getInput('owner')) > 1)
				{
					if(ilObjUser::_lookupId($form->getInput('owner')) > 0)
					{
						$owner = ilObjUser::_lookupId($form->getInput('owner'));
					}
					else
					{
						ilUtil::sendFailure($this->lng->txt('user_not_found'));
						$owner = 0;
					}
				}

				if($template_settings['duration']['hide'] == '1')
				{
					$durationValid = true;
				}
				else
				{
					if($form->getInput('time_type_selection') == 'permanent_room' && ilAdobeConnectServer::getSetting('enable_perm_room', '1') )
					{
						$duration['hh'] = 2;
						$duration['mm'] = 0;
					}
					else
					{
						$duration = $form->getInput("duration");
					}

					if($duration['hh'] * 60 + $duration['mm'] < 10)
					{
						$form->getItemByPostVar('duration')->setAlert($this->pluginObj->txt('min_duration_error'));
						$durationValid = false;
					}
					else
					{
						$durationValid = true;
					}
				}

				if($template_settings['start_date']['hide'] == '1')
				{
					$time_mismatch = false;
				}
				else
				{
					if($durationValid)
					{
						require_once dirname(__FILE__) . '/class.ilAdobeConnectServer.php';
						$serverConfig = ilAdobeConnectServer::_getInstance();
						$minTime       = new ilDateTime(time() + $serverConfig->getScheduleLeadTime() * 60 * 60, IL_CAL_UNIX);

						if ($form->getInput('time_type_selection') == 'permanent_room') {
							$form->getItemByPostVar("start_date")->checkInput();
						}

						$newStartDate  = $form->getItemByPostVar("start_date")->getDate();

						$time_mismatch = false;
						if(ilDateTime::_before($newStartDate, $minTime) && $form->getInput('time_type_selection') != 'permanent_room')
						{
							ilUtil::sendFailure(sprintf($this->pluginObj->txt('xavc_lead_time_mismatch_create'), ilDatePresentation::formatDate($minTime)), true);
							$time_mismatch = true;
						}
					}
				}
				if(!$time_mismatch && $owner > 0)
				{
					try
					{
						if($durationValid)
						{
                            $location = $objDefinition->getLocation($new_type);

							// create and insert object in objecttree
							$class_name = "ilObj" . $objDefinition->getClassName($new_type);
							include_once($location . "/class." . $class_name . ".php");
							/** @var $newObj ilObjAdobeConnect */
							$newObj = new $class_name();
							$newObj->setType($new_type);
							$newObj->setTitle(ilUtil::stripSlashes($_POST["title"]));
							$newObj->setDescription(ilUtil::stripSlashes($_POST["desc"]));
							$newObj->setOwner($owner);
							$newObj->create();
							$newObj->createReference();
							$newObj->putInTree($_GET["ref_id"]);
							$newObj->setPermissions($_GET["ref_id"]);
							ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
							$this->afterSave($newObj);
							return;
						}
					}
					catch(Exception $e)
					{
						ilUtil::sendFailure($e->getMessage(), true);
					}
				}
			}

			$form->setValuesByPost();
			if(ilAdobeConnectServer::getSetting('show_free_slots'))
			{
				$this->showCreationForm($form);
			}
			else
			{
				$this->tpl->setContent($form->getHtml());
			}
		}
		else
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHTML());
			return;
		}
	}

	private function showCreationForm(ilPropertyFormGUI $form)
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();

		require_once dirname(__FILE__) . '/class.ilObjAdobeConnect.php';
		require_once dirname(__FILE__) . '/class.ilAdobeConnectServer.php';

		$num_max_ac_obj = ilAdobeConnectServer::getSetting('ac_interface_objects');
		if((int)$num_max_ac_obj <= 0)
		{
			$tpl->setContent($form->getHtml());
			return 0;
		}

		$fromtime = strtotime(date('Y-m-d H:00', time()));
		$totime   = strtotime(date('Y-m-d', time() + 60 * 60 * 24 * 15)) - 1;

		$meetings       = ilObjAdobeConnect::getMeetingsInRange($fromtime, $totime);

		$meetingsByDay = $this->getFreeMeetingSlotTable($meetings);
		$ranges = array();

		$t0             = $fromtime;
		$t1             = $fromtime;
		/*
						 * 2 * 30 minutes for starting and ending buffer
						 * 10 minutes as minimum meeting length
						 */
		$bufferingTime = 30 * 60 * 2 + 10 * 60;

		$prev_dayinmonth = date('d', $t1);

		for(; $t1 < $totime; $t1 += 60 * 15)
		{
			$day        = date('Y-m-d', $t1);
			$hour       = date('H', $t1) * 60 + floor(date('i', $t1) / 15) * 15;
			$dayinmonth = date('d', $t1);

			if($meetingsByDay[$day] && $meetingsByDay[$day][$hour] && $meetingsByDay[$day][$hour] >= $num_max_ac_obj || ($dayinmonth != $prev_dayinmonth))
			{
				if($t0 != $t1 && ($t1 - $t0) > $bufferingTime)
					$ranges[] = array($t0, $t1 - 1, $t1 - $t0);

				if($dayinmonth != $prev_dayinmonth)
				{
					$prev_dayinmonth = $dayinmonth;
					$t0              = $t1;
				}
				else
				{
					$t0 = $t1 + 60 * 15;
				}
			}
		}

		if($t0 != $t1)
		{
			$ranges[] = array($t0, $t1 - 1, $t1 - $t0);
		}

		$data = array();

		foreach($ranges as $r)
		{
			$size_hours   = floor($r[2] / 60 / 60);
			$size_minutes = ($r[2] - $size_hours * 60 * 60) / 60;

			$data[] = array(
				'sched_from' => ilDatePresentation::formatDate(new ilDateTime($r[0], IL_CAL_UNIX)),
				'sched_to'   => ilDatePresentation::formatDate(new ilDateTime($r[1], IL_CAL_UNIX)),
				'sched_size' => str_pad($size_hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($size_minutes, 2, '0', STR_PAD_LEFT),
			);
		}

		require_once 'Services/Table/classes/class.ilTable2GUI.php';
		$tgui = new ilTable2GUI($this);

		$tgui->addColumn($this->txt('sched_from'));
		$tgui->addColumn($this->txt('sched_to'));
		$tgui->addColumn($this->txt('sched_size'));

		$tgui->setExternalSegmentation(true);

		$tgui->enabled['footer'] = false;
//		$tgui->setRowTemplate('tpl.schedule_row.html', 'Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect');
		$tgui->setRowTemplate('tpl.schedule_row.html',$this->pluginObj->getDirectory());
		$tgui->setData($data);
		$tgui->setTitle(sprintf($this->txt('schedule_free_slots'), date('z', $totime - $fromtime)));

		$tpl->setContent($form->getHtml() . '<br />' . $tgui->getHTML());
	}

	public function editParticipants()
	{
		global $DIC; 
		$ilCtrl = $DIC->ctrl(); 
		$lng = $DIC->language(); 
		$ilUser = $DIC->user();
		$ilToolbar = $DIC->toolbar();
		
		$this->pluginObj->includeClass('class.ilXAVCMembers.php');
		$this->pluginObj->includeClass('class.ilXAVCTableGUI.php');

		$this->tabs->activateTab('participants');
		$this->__setSubTabs('participants');
		$this->tabs->activateSubTab("editParticipants");

		$my_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.meeting_participant_table.html", true, true);

		$has_access = ilXAVCPermissions::hasAccess($ilUser->getId(), $this->object->getRefId(), AdobeConnectPermissions::PERM_ADD_PARTICIPANTS);
		if(count($this->object->getParticipantsObject()->getParticipants()) == 0 && $has_access)
		{
			// add members
			include_once 'Services/Search/classes/class.ilRepositorySearchGUI.php';
			$types = array(
				'add_member' => $this->lng->txt('member'),
				'add_admin'  => $this->lng->txt('administrator')
			);

			ilRepositorySearchGUI::fillAutoCompleteToolbar(
				$this,
				$ilToolbar,
				array(
					'auto_complete_name' => $lng->txt('user'),
					'user_type'          => $types,
					'submit_name'        => $lng->txt('add')
				)
			);
			// add separator
			$ilToolbar->addSeparator();

			// search button
			$ilToolbar->addButton($this->lng->txt("crs_search_members"),
				$ilCtrl->getLinkTargetByClass('ilRepositorySearchGUI', 'start'));
		}

		$this->pluginObj->includeClass('class.ilXAVCParticipantsTableGUI.php');
		$this->pluginObj->includeClass('class.ilXAVCParticipantsDataProvider.php');
		$table = new ilXAVCParticipantsTableGUI($this, "editParticipants");

		$table->setProvider(new ilXAVCParticipantsDataProvider($DIC->database(), $this));
		$table->populate();

		$my_tpl->setVariable('FORM',$table->getHTML().$this->getPerformTriggerHtml());

		$this->tpl->setContent($my_tpl->get());
	}
	
	public function performCrsGrpTrigger()
	{
		ignore_user_abort(true);
		@set_time_limit(0);

		$response = new stdClass();
		$response->succcess = false;

		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');
		if((int)ilAdobeConnectServer::getSetting('allow_crs_grp_trigger') == 0)
		{
			echo json_encode($response);
			exit();
		}

		$this->pluginObj->includeClass('class.ilXAVCMembers.php');

		if (count($this->object->getParticipantsObject()->getParticipants()) > 0 )
		{
			$sco_id = ilObjAdobeConnect::_lookupScoId(ilObject::_lookupObjectId($this->object->getRefId()));
			$current_member_ids = ilXAVCMembers::getMemberIds($this->object->getRefId());
			$crs_grp_member_ids = $this->object->getParticipantsObject()->getParticipants();

			if(count($current_member_ids) == 0 && count($crs_grp_member_ids) > 0 )
			{
				$this->object->addCrsGrpMembers($this->object->getRefId(), $sco_id);
			}
			else
			{
				$new_member_ids = array_diff($crs_grp_member_ids, $current_member_ids);
				$delete_member_ids = array_diff($current_member_ids, $crs_grp_member_ids);

				if(is_array($new_member_ids) && count($new_member_ids) > 0)
				{
					$this->object->addCrsGrpMembers($this->object->getRefId(), $sco_id, $new_member_ids);
				}

				if(is_array($delete_member_ids) && count($delete_member_ids) > 0)
				{
					$this->object->deleteCrsGrpMembers($sco_id, $delete_member_ids);
				}
			}
		}
		$response->succcess = true;
		echo json_encode($response);
		exit();
	}

	public function getPerformTriggerHtml()
	{
		global $DIC;
		$ilCtrl = $DIC->ctrl();

		include_once "Services/jQuery/classes/class.iljQueryUtil.php";
		iljQueryUtil::initjQuery();

		$trigger_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.perform_trigger.html", true, true);

		$target = $ilCtrl->getLinkTarget($this, 'performCrsGrpTrigger', '', true, false);
		$trigger_tpl->setVariable('TRIGGER_TARGET', $target );

		$trigger_tpl->parseCurrentBlock();
		return $trigger_tpl->get();
	}

	public function infoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen();
	}

	public function infoScreen()
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();

		$this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		$this->pluginObj->includeClass('class.ilAdobeConnectQuota.php');
		$this->pluginObj->includeClass("class.ilObjAdobeConnectAccess.php");

		$settings = ilAdobeConnectServer::_getInstance();
		$this->tabs->setTabActive('info_short');
		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);
		$info->removeFormAction();
		$info->addSection($this->pluginObj->txt('general'));
		if($this->object->getPermanentRoom() == 1 && ilAdobeConnectServer::getSetting('enable_perm_room', '1') )
		{
			$duration_text = $this->pluginObj->txt('permanent_room');
		}
		else
		{
			$duration_text = ilDatePresentation::formatPeriod(
				new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX),
				new ilDateTime($this->object->getEndDate()->getUnixTime(), IL_CAL_UNIX));
		}
			$presentation_url = $settings->getPresentationUrl();

			$form = new ilPropertyFormGUI();
			$form->setTitle($this->pluginObj->txt('access_meeting_title'));

			$this->object->doRead();

			if($this->object->getStartDate() != NULL)
			{
				$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
				$ilAdobeConnectUser->ensureAccountExistance();

				$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
				$quota 		= new ilAdobeConnectQuota();
			}
// show link
		if(($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
			&& $this->object->isParticipant($xavc_login))
		{
			if(!$quota->mayStartScheduledMeeting($this->object->getScoId()))
			{
				$href = $this->txt("meeting_not_available_no_slots");
			}
			else
			{
				$href = '<a href="' . $presentation_url . $this->object->getURL() . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
				$button_txt = $this->pluginObj->txt('enter_vc');
				$button_target = ILIAS_HTTP_PATH."/". $this->ctrl->getLinkTarget($this, 'performSso', '', false, false);
				$button_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.bigbutton.html", true, true);
				$button_tpl->setVariable('BUTTON_TARGET', $button_target);
				$button_tpl->setVariable('BUTTON_TEXT', $button_txt);
				
				$big_button = $button_tpl->get();
				$info->addProperty('',$big_button."<br />");	
			}
		}
		else
		{
			$href = $this->txt("meeting_not_available");
		}

		$info->addProperty($this->pluginObj->txt('duration'), $duration_text);
		$info->addProperty($this->pluginObj->txt('meeting_url'), $href);
		
		$tpl->setContent($info->getHTML().$this->getPerformTriggerHtml());
	}

	public function showContent()
	{
		global $DIC; 
		$ilUser = $DIC->user();
		$tpl = $DIC->ui()->mainTemplate(); 
		$ilAccess = $DIC->access();

        $this->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$this->pluginObj->includeClass('class.ilAdobeConnectServer.php');

		$has_write_permission =  $ilAccess->checkAccess("write", "", $this->object->getRefId());

		$settings = ilAdobeConnectServer::_getInstance();

		if($settings->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI AND ilAdobeConnectServer::useSwitchaaiAuthMode($ilUser->getAuthMode(true)))
		{
            //Login User - this creates a user if he not exists.
            $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
            $ilAdobeConnectUser->loginUser();

            //Add the user as Participant @adobe switch
            $status = ilXAVCMembers::_lookupStatus($ilUser->getId(), $this->object->getRefId());
            $this->object->addSwitchParticipant($ilUser->getEmail(),$status);
		}

		$this->tabs->setTabActive('contents');

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);
		$info->removeFormAction();

		$this->pluginObj->includeClass('class.ilAdobeConnectQuota.php');
		$this->pluginObj->includeClass("class.ilObjAdobeConnectAccess.php");

		$is_member = ilObjAdobeConnectAccess::_hasMemberRole($ilUser->getId(), $this->object->getRefId());
		$is_admin = ilObjAdobeConnectAccess::_hasAdminRole($ilUser->getId(), $this->object->getRefId());

		//SWITCHAAI: If the user has no SWITCHaai-Account, we show the room link without connecting to the adobe-connect server. This is used for guest logins.
		$show_only_roomlink = false;
		if($settings->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI AND !ilAdobeConnectServer::useSwitchaaiAuthMode($ilUser->getAuthMode(true)))
		{
			$show_only_roomlink = true;
			$presentation_url = $settings->getPresentationUrl();
			$button_txt = $this->pluginObj->txt('enter_vc');
			$button_target = $presentation_url . $this->object->getURL();
			$button_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.bigbutton.html", true, true);
			$button_tpl->setVariable('BUTTON_TARGET', $button_target);
			$button_tpl->setVariable('BUTTON_TEXT', $button_txt);
			$big_button = $button_tpl->get();
			$info->addSection('');
			$info->addProperty('',$big_button."<br />");
		}

		if (($this->access->checkAccess("write", "", $this->object->getRefId()) || $is_member || $is_admin) && !$show_only_roomlink)
		{
			$presentation_url = $settings->getPresentationUrl();

			$form = new ilPropertyFormGUI();
			$form->setTitle($this->pluginObj->txt('access_meeting_title'));

			$this->object->doRead();

			if($this->object->getStartDate() != NULL)
			{
				$ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
				$ilAdobeConnectUser->ensureAccountExistance();

				$xavc_login = $ilAdobeConnectUser->getXAVCLogin();
				$quota 		= new ilAdobeConnectQuota();

				// show button
				if(($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
				&& $this->object->isParticipant($xavc_login))
				{
					if(!$quota->mayStartScheduledMeeting($this->object->getScoId()))
					{
						$href = $this->txt("meeting_not_available_no_slots");
						$button_disabled = true;
					}
					else
					{
						$href = '<a href="' . $this->ctrl->getLinkTarget($this, 'performSso') . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
						$button_disabled = false;
					}
				}
				else
				{
					$href = $this->txt("meeting_not_available");
					$button_disabled = true;
				}

				if($button_disabled == true)
				{
					$button_txt = $href;
				}
				else
				{
					$button_txt = $this->pluginObj->txt('enter_vc');
				}

				$button_target = ILIAS_HTTP_PATH."/". $this->ctrl->getLinkTarget($this, 'performSso', '', false, false);
				$button_tpl = new ilTemplate($this->pluginObj->getDirectory()."/templates/default/tpl.bigbutton.html", true, true);
				$button_tpl->setVariable('BUTTON_TARGET', $button_target);
				$button_tpl->setVariable('BUTTON_TEXT', $button_txt);

				$big_button = $button_tpl->get();

				$info->addSection('');
				if($button_disabled == true)
				{
					$info->addProperty('', $href);
				}
				else
				{
					$info->addProperty('',$big_button."<br />");
				}

				// show instructions
				if(strlen($this->object->getInstructions()) > 1)
				{
					$info->addSection($this->lng->txt('exc_instruction'));
					$info->addProperty('', nl2br($this->object->getInstructions()));
				}

				// show contact info
				if(strlen($this->object->getContactInfo()) > 1)
				{
					$info->addSection($this->pluginObj->txt('contact_information'));
					$info->addProperty('', nl2br($this->object->getContactInfo()));
				}

				//show contents
				if(
					ilXAVCPermissions::hasAccess($ilUser->getId(), $this->object->getRefId(), AdobeConnectPermissions::PERM_READ_CONTENTS) 
					&& $this->object->getReadContents('content')
				)
				{
					$info->addSection($this->pluginObj->txt('file_uploads'));
					$info->setFormAction($this->ctrl->getFormAction($this, 'showContent'));
					$has_access = false;
					if(
						ilXAVCPermissions::hasAccess($ilUser->getId(), $this->ref_id, AdobeConnectPermissions::PERM_UPLOAD_CONTENT) 
						|| $has_write_permission
					)
					{
						require_once 'Services/UIComponent/Button/classes/class.ilSubmitButton.php';
						$submitBtn = ilSubmitButton::getInstance();
						$submitBtn->setCaption($this->txt("add_new_content"), false);
						$submitBtn->setCommand('showAddContent');

						$has_access = true;

						$info->addProperty('', $submitBtn->render());
					}
					$info->addProperty('', $this->viewContents($has_access));
				}

				// show records
				if(
					ilXAVCPermissions::hasAccess($ilUser->getId(), $this->object->getRefId(), AdobeConnectPermissions::PERM_READ_RECORDS) &&
					$this->object->getReadRecords()
				)
				{
					$has_access = false;
					if(
						ilXAVCPermissions::hasAccess($ilUser->getId(), $this->ref_id, AdobeConnectPermissions::PERM_EDIT_RECORDS)
						|| $has_write_permission
					)
					{
						$has_access = true;
					}
					
					$info->addSection($this->pluginObj->txt('records'));
					$info->addProperty('', $this->viewRecords($has_access, 'record'));
				}
			}
			else
			{
				ilUtil::sendFailure($this->txt('error_connect_ac_server'));
			}
		}

		$info->hideFurtherSections();
		$tpl->setContent($info->getHTML().$this->getPerformTriggerHtml());

		$tpl->setPermanentLink('xavc', $this->object->getRefId());
		$tpl->addILIASFooter();
	}
}