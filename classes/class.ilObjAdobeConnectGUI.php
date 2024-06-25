<?php

/**
 * @ilCtrl_isCalledBy ilObjAdobeConnectGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjAdobeConnectGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
 */
class ilObjAdobeConnectGUI extends ilObjectPluginGUI implements AdobeConnectPermissions
{
    use ilAdobeConnectRequestTrait;

    public const CONTENT_MOD_EDIT = 1;
    public const CONTENT_MOD_ADD = 2;

    public const CREATION_FORM_TA_COLS = 60;
    public const CREATION_FORM_TA_ROWS = 5;

    public ilTabsGUI $tabs;
    public ilCtrl $ctrl;
    public ilAccessHandler $access;
    public ilGlobalTemplateInterface $tpl;
    public ilLanguage $lng;
    public ilObjUser $user;
    public $pluginObj;
    public ?ilPropertyFormGUI $form = null;
    public ?ilPropertyFormGUI $cform = null;
    public ?ilPropertyFormGUI $csform = null;

    /**
     * @var ilXAVCTemplates[]
     */
    public array $xavc_templates = [];

    /** @var bool is_record  for initEditForm hide/show file upload form */
    public bool $is_record = false;

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->pluginObj = ilAdobeConnectPlugin::getInstance();
        $this->form = new ilPropertyFormGUI();

        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->error = $DIC['ilErr'];

        if (is_object($this->object)) {
            $this->tpl->setDescription($this->object->getLongDescription());
            $this->ref_id = $this->object->getRefId();
        }
    }

    final public function getType(): string
    {
        return 'xavc';
    }

    public function performCommand($cmd): void
    {
        $this->pluginObj = ilAdobeConnectPlugin::getInstance();
        $next_class = $this->ctrl->getNextClass($this);

        switch ($next_class) {
            case 'ilpublicuserprofilegui':
                $user_id = $this->retrieveIntFrom(self::$REQUEST_GET, 'user');
                $profile_gui = new ilPublicUserProfileGUI($user_id);
                $profile_gui->setBackUrl($this->ctrl->getLinkTarget($this, 'showMembersGallery'));
                $this->tabs->activateTab('participants');
                $this->setSubTabs('participants');
                $this->tabs->activateSubTab('editParticipants');
                $html = $this->ctrl->forwardCommand($profile_gui);

                $this->tpl->setVariable('ADM_CONTENT', $html);
                break;
            case 'ilcommonactiondispatchergui':
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;
            case 'ilrepositorysearchgui':
                $this->tabs->activateTab('participants');
                $rep_search = new ilRepositorySearchGUI();
                $rep_search->setCallback(
                    $this,
                    'addAsMember',
                    [
                        'add_member' => $this->lng->txt('member'),
                        'add_admin' => $this->lng->txt('administrator')
                    ]
                );
                $this->ctrl->setReturn($this, 'editParticipants');
                $this->ctrl->forwardCommand($rep_search);
                break;

            default:
                switch ($cmd) {
                    //		            case 'editContents':
                    case 'editProperties':        // list all commands that need write permission here
                    case 'updateProperties':
                    case 'assignRolesAfterCreate':

                        $this->checkPermission('write');
                        $this->$cmd();
                        break;

                    // list all commands that need read permission here
                    case 'addParticipant':
                    case 'searchContentFile':
                    case 'cancelSearchContentFile':
                    case 'showFileSearchResult':
                    case 'addContentFromILIAS':
                    case 'askDeleteContents':
                    case 'deleteContents':
                    case 'uploadFile':
                    case 'showUploadFile':
                    case 'editItem':
                    case 'editRecord':
                    case 'updateContent':
                    case 'updateRecord':
                    case 'showAddContent':
                    case 'addContent':
                    case 'performDetachMember':
                    case 'performAddCrsGrpMembers':
                    case 'addAsMember':
                    case 'detachMember':
                    case 'addCrsGrpMembers':
                    case 'showContent':
                    case 'editParticipants':
                    case 'updateParticipants':
                    case 'performSso':
                    case 'requestAdobeConnectContent':
                    case 'viewContents':
                    case 'viewRecords':
                    case 'showMembersGallery':
                    case 'performCrsGrpTrigger':
                        $this->checkPermission('read');
                        $this->$cmd();
                        break;
                    case 'join':
                    case 'leave':
                        $this->checkPermission('visible');
                        $this->$cmd();
                        break;

                    default:
                        $this->showContent();
                        break;
                }
                break;
        }
    }

    public function getObject(): ilObject
    {
        return $this->object;
    }

    public function assignRolesAfterCreate(): void
    {
        $xavcMemberObj = new ilXAVCMembers($this->object->getRefId(), $this->user->getId());
        $xavcMemberObj->setPresenterStatus();
        $xavcMemberObj->setScoId($this->object->getScoId());
        $xavcMemberObj->insertXAVCMember();

        $xavc_role = new ilAdobeConnectRoles($this->object->getRefId());
        $xavc_role->addAdministratorRole($this->user->getId());

        if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
            ilObjAdobeConnect::addToFavourites($this->user->getId(), $this->object->getRefId());
        }

        if (ilAdobeConnectServer::getSetting('allow_crs_grp_trigger')) {
            $this->object->addCrsGrpMembers($this->object->getRefId(), $this->object->getScoId());
        }

        $this->editProperties();
    }

    public function getAfterCreationCmd(): string
    {
        return 'assignRolesAfterCreate';
    }

    public function getStandardCmd(): string
    {
        return 'editProperties';
    }

    public function setTabs(): void
    {
        $user_id = $this->user->getId();
        $ref_id = $this->object->getRefId();

        $is_member = ilObjAdobeConnectAccess::_hasMemberRole($user_id, $ref_id);
        $is_admin = ilObjAdobeConnectAccess::_hasAdminRole($user_id, $ref_id);

        // tab for the 'show contents' command
        if ($this->access->checkAccess('read', '', $this->object->getRefId()) || $is_member) {
            $this->tabs->addTab(
                'contents',
                $this->txt('adobe_meeting_room'),
                $this->ctrl->getLinkTarget($this, 'showContent')
            );
        }

        // standard info screen tab
        $this->addInfoTab();

        // a 'properties' tab
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $this->tabs->addTab(
                'properties',
                $this->txt('properties'),
                $this->ctrl->getLinkTarget($this, 'editProperties')
            );
        }

        $xavc_access = ilXAVCPermissions::hasAccess($user_id, $ref_id, AdobeConnectPermissions::PERM_EDIT_PARTICIPANTS);

        // tab for the 'edit participants' command
        if ($xavc_access || $is_admin || ilObject::_lookupOwner(
                ilObject::_lookupObjectId($ref_id)
            ) == $this->user->getId()) {
            $this->tabs->addTab(
                'participants',
                $this->txt('participants'),
                $this->ctrl->getLinkTarget($this, 'editParticipants')
            );
        } else {
            if ($this->access->checkAccess('read', '', $this->object->getRefId())) {
                $this->tabs->addTab(
                    'participants',
                    $this->txt('participants'),
                    $this->ctrl->getLinkTarget($this, 'showMembersGallery')
                );
            }
        }

        $this->addPermissionTab();
    }

    protected function setSubTabs(string $a_tab): void
    {
        $this->lng->loadLanguageModule('crs');

        switch ($a_tab) {
            case 'participants':
                $xavc_access = ilXAVCPermissions::hasAccess(
                    $this->user->getId(),
                    $this->object->getRefId(),
                    AdobeConnectPermissions::PERM_EDIT_PARTICIPANTS
                );
                $is_owner = $this->user->getId() == $this->object->getOwner();

                if ($is_owner || $xavc_access) {
                    $this->tabs->addSubTab(
                        'editParticipants',
                        $this->lng->txt('crs_member_administration'),
                        $this->ctrl->getLinkTarget($this, 'editParticipants')
                    );

                    if (!ilAdobeConnectServer::getSetting('allow_crs_grp_trigger') && count(
                            $this->object->getParticipantsObject()->getParticipants()
                        ) > 0) {
                        $this->tabs->addSubTab(
                            'addCrsGrpMembers',
                            $this->txt('add_crs_grp_members'),
                            $this->ctrl->getLinkTarget($this, 'addCrsGrpMembers')
                        );
                    }
                }
                $this->tabs->addSubTab(
                    'showMembersGallery',
                    $this->pluginObj->txt('members_gallery'),
                    $this->ctrl->getLinkTarget($this, 'showMembersGallery')
                );
                break;
        }
    }

    /**
     * Edits Properties. This commands uses the form class to display an input form.
     */
    public function editProperties(): void
    {
        $this->object->doRead();
        $this->tabs->activateTab('properties');

        $this->initPropertiesForm();
        $this->getPropertiesValues();

        if (ilAdobeConnectServer::getSetting('show_free_slots')) {
            $this->showCreationForm($this->form);
        } else {
            $this->tpl->setContent($this->form->getHTML());
        }
    }

    public function initPropertiesForm(): void
    {
        $this->form = new ilPropertyFormGUI();

        // title
        $ti = new ilTextInputGUI($this->txt('title'), 'title');
        $ti->setRequired(true);
        $this->form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->txt('description'), 'desc');
        $this->form->addItem($ta);

        $instructions = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions');
        $instructions->setRows(self::CREATION_FORM_TA_ROWS);
        $this->form->addItem($instructions);

        // contact_info
        $contact_info = new ilTextAreaInputGUI($this->pluginObj->txt('contact_information'), 'contact_info');
        $contact_info->setRows(self::CREATION_FORM_TA_ROWS);
        $this->form->addItem($contact_info);

        $radio_access_level = new ilRadioGroupInputGUI($this->pluginObj->txt('access'), 'access_level');
        $opt_private = new ilRadioOption(
            $this->pluginObj->txt('private_room'),
            ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE
        );
        $opt_protected = new ilRadioOption(
            $this->pluginObj->txt('protected_room'),
            ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED
        );
        $opt_public = new ilRadioOption($this->pluginObj->txt('public_room'), ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC);

        $radio_access_level->addOption($opt_private);
        $radio_access_level->addOption($opt_protected);
        $radio_access_level->addOption($opt_public);

        $this->form->addItem($radio_access_level);

        $radio_time_type = new ilRadioGroupInputGUI(
            $this->pluginObj->txt('time_type_selection'),
            'time_type_selection'
        );

        // option: permanent room
        if (ilAdobeConnectServer::getSetting('enable_perm_room', '1')) {
            $permanent_room = new ilRadioOption($this->pluginObj->txt('permanent_room'), 'permanent_room');
            $permanent_room->setInfo($this->pluginObj->txt('permanent_room_info'));
            $radio_time_type->addOption($permanent_room);
        }
        // option: date selection
        $opt_date = new ilRadioOption($this->pluginObj->txt('start_date'), 'date_selection');
        // start date
        $sd = new ilDateTimeInputGUI($this->txt('start_date'), 'start_date');
        $sd->setShowTime(true);
        $sd->setInfo($this->txt('info_start_date'));
        $sd->setRequired(true);
        $opt_date->addSubItem($sd);

        $duration = new ilDurationInputGUI($this->pluginObj->txt('duration'), 'duration');
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
        foreach ($adobe_langs as $lang) {
            $lang_options[$lang] = $this->lng->txt('meta_l_' . $lang);
        }

        $lang_selector->setOptions($lang_options);
        $this->form->addItem($lang_selector);

        $html_client = new ilCheckboxInputGUI($this->pluginObj->txt('html_client'), 'html_client');
        $html_client->setInfo($this->pluginObj->txt('html_client_info'));
        $this->form->addItem($html_client);

        $this->form->addCommandButton('updateProperties', $this->txt('save'));
        $this->form->addCommandButton('editProperties', $this->txt('cancel'));

        $this->form->setTitle($this->txt('edit_properties'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
    }

    public function getPropertiesValues(): void
    {
        $values['title'] = $this->object->getTitle();
        $values['desc'] = $this->object->getDescription();

        $values['access_level'] = $this->object->getPermissionId();

        if ($this->object->getPermanentRoom() == 1 && ilAdobeConnectServer::getSetting('enable_perm_room', '1')) {
            $values['time_type_selection'] = 'permanent_room';
        } else {
            $values['time_type_selection'] = 'date_selection';
        }

        $duration = $this->object->getDuration();
        $values['start_date'] = $this->object->getStartDate();
        $values['duration'] = ['hh' => $duration['hours'], 'mm' => $duration['minutes']];
        $values['instructions'] = $this->object->getInstructions();

        $values['contact_info'] = $this->object->getContactInfo();

        $values['read_contents'] = $this->object->getReadContents();
        $values['read_records'] = $this->object->getReadRecords();

        global $DIC;
        $default_lang = $DIC->language()->getDefaultLanguage();
        $adobe_langs = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'tr', 'ru', 'ja', 'zh', 'ko'];

        if (in_array($this->object->getAcLanguage(), $adobe_langs)) {
            $values['ac_language'] = $this->object->getAcLanguage();
        } else {
            if (in_array($default_lang, $adobe_langs)) {
                $values['ac_language'] = $default_lang;
            } else {
                $values['ac_language'] = 'de';
            }
        }

        $values['html_client'] = $this->object->isHtmlClientEnabled();

        $this->form->setValuesByArray($values);
    }

    public function updateProperties(): void
    {
        $this->initPropertiesForm();

        $formValid = $this->form->checkInput();

        $duration = $this->form->getInput('duration');

        if ($this->form->getInput('time_type_selection') == 'permanent_room' && ilAdobeConnectServer::getSetting(
                'enable_perm_room',
                '1'
            )) {
            $durationValid = true;
        } else {
            if ($duration['hh'] * 60 + $duration['mm'] < 10) {
                $this->form->getItemByPostVar('duration')->setAlert($this->lng->txt('min_duration_error'));
                $durationValid = false;
            } else {
                $durationValid = true;
            }
        }

        $oldObject = new ilObjAdobeConnect();
        $oldObject->setId($this->object->getId());
        $oldObject->doRead();

        $time_mismatch = false;

        if ($formValid && $durationValid) {
            $new_start_date_input = $this->form->getItemByPostVar('start_date');
            if (
                $new_start_date_input instanceof ilDateTimeInputGUI &&
                $new_start_date_input->getDate() instanceof ilDateTime
            ) {
                $newStartDate = $new_start_date_input->getDate();
            } else {
                $newStartDate = new ilDateTime(time(), IL_CAL_UNIX);
            }

            $this->object->setTitle($this->form->getInput('title'));
            $this->object->setDescription($this->form->getInput('desc'));
            $this->object->setInstructions($this->form->getInput('instructions'));
            $this->object->setContactInfo($this->form->getInput('contact_info'));
            $this->object->setAcLanguage($this->form->getInput('ac_language'));
            $this->object->setUseHtmlClient((int) $this->form->getInput('html_client'));

            $enable_perm_room = (ilAdobeConnectServer::getSetting(
                    'enable_perm_room',
                    '1'
                ) && $this->form->getInput('time_type_selection') == 'permanent_room') ? true : false;
            $this->object->setPermanentRoom($enable_perm_room ? 1 : 0);

            $this->object->setReadContents((int) $this->form->getInput('read_contents'));
            $this->object->setReadRecords((int) $this->form->getInput('read_records'));

            $access_level = ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED;
            if (in_array($this->form->getInput('access_level'), [
                ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE,
                ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED,
                ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC
            ])) {
                $access_level = $this->form->getInput('access_level');
            }
            $this->object->setPermission($access_level);

            if (!$time_mismatch || ($this->form->getInput(
                        'time_type_selection'
                    ) == 'permanent_room' && ilAdobeConnectServer::getSetting(
                        'enable_perm_room',
                        '1'
                    ))) {
                $this->object->setStartDate($newStartDate);
                $duration = $this->form->getInput('duration');
                $this->object->setDuration(['hours' => $duration['hh'], 'minutes' => $duration['mm']]);
            }
            $concurrent_vcs = $this->object->checkConcurrentMeetingDates();
            $num_max_ac_obj = ilAdobeConnectServer::getSetting('ac_interface_objects');
            if ((int) $num_max_ac_obj <= 0 || (int) count($concurrent_vcs) < (int) $num_max_ac_obj) {
                $this->object->update();

                $this->tpl->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);

                $this->ctrl->redirect($this, 'editProperties');
            } else {
                $this->tpl->setOnScreenMessage(
                    'failure',
                    $this->pluginObj->txt('maximum_concurrent_vcs_reached'),
                    true
                );
                $this->ctrl->redirect($this, 'editProperties');
            }
        }

        $this->form->setValuesByPost();
    }

    private function doProvideAccessLink(ilDateTime $now = null): bool
    {
        $instance = ilAdobeConnectServer::_getInstance();
        $datetime_before = new ilDateTime(
            $this->object->getStartDate()->getUnixtime() - $instance->getBufferBefore(),
            IL_CAL_UNIX
        );
        $datetime_after = new ilDateTime(
            $this->object->getEndDate()->getUnixtime() + $instance->getBufferAfter(),
            IL_CAL_UNIX
        );
        if (!isset($now)) {
            $now = new ilDateTime(time(), IL_CAL_UNIX);
        }
        return ilDateTime::_before($datetime_before, $now) && ilDateTime::_before($now, $datetime_after);
    }

    public function showAccessLink(): void
    {
        $presentation_url = ilAdobeConnectServer::getPresentationUrl();

        $this->tabs->activateTab('access');

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObj->txt('access_meeting_title'));

        $this->object->doRead();

        if ($this->object->getStartDate() != null) {
            $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
            $ilAdobeConnectUser->ensureAccountExistence();

            $xavc_login = $ilAdobeConnectUser->getXAVCLogin();
            $quota = new ilAdobeConnectQuota();

            // show link
            $link = new ilNonEditableValueGUI($this->pluginObj->txt('access_link'));

            if ($this->doProvideAccessLink() && $this->object->isParticipant($xavc_login)) {
                if (!$quota->mayStartScheduledMeeting($this->object->getScoId())) {
                    $link->setValue($this->txt('meeting_not_available_no_slots'));
                } else {
                    $link = new ilCustomInputGUI($this->pluginObj->txt("access_link"));
                    $href = '<a href="' . $this->ctrl->getLinkTarget(
                            $this,
                            'performSso'
                        ) . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
                    $link->setHtml($href);
                }
            } else {
                $link->setValue($this->txt('meeting_not_available'));
            }

            $form->addItem($link);

            $start_date = new ilNonEditableValueGUI($this->txt('start_date'));

            $start_date->setValue(
                ilDatePresentation::formatDate(
                    new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX)
                )
            );

            $form->addItem($start_date);

            $duration = new ilNonEditableValueGUI($this->txt('duration'));
            $duration->setValue(
                ilDatePresentation::formatPeriod(
                    new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX),
                    new ilDateTime($this->object->getEndDate()->getUnixTime(), IL_CAL_UNIX)
                )
            );
            $form->addItem($duration);

            $this->tpl->setContent($form->getHTML());
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->txt('error_connect_ac_server'));
        }
    }

    public function performSso(): void
    {
        global $DIC;

        $ilSetting = $DIC->settings();

        $settings = ilAdobeConnectServer::_getInstance();

        if (null !== $this->object->getStartDate()) {
            $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
            $ilAdobeConnectUser->ensureAccountExistence();

            $xavc_login = $ilAdobeConnectUser->getXAVCLogin();

            if (($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
                && $this->object->isParticipant($xavc_login)) {
                $xmlAPI = ilXMLApiFactory::getApiByAuthMode();

                $presentation_url = ilAdobeConnectServer::getPresentationUrl();

                if(array_key_exists('xavc_last_sso_sessid', $_SESSION)) {
                    $xmlAPI->logout($_SESSION['xavc_last_sso_sessid']);
                }

                //login current user session
                $session = $ilAdobeConnectUser->loginUser();
                $_SESSION['xavc_last_sso_sessid'] = $session;
                if ($settings->isHtmlClientEnabled() == 1 && $this->object->isHtmlClientEnabled() == 1) {
                    $html_client = '&html-view=true';
                }
                $url = $presentation_url . $this->object->getURL() . '?session=' . $session . $html_client;

                $GLOBALS['ilLog']->write(sprintf("Generated URL %s for user '%s'", $url, $xavc_login));

                $presentation_url = ilAdobeConnectServer::getPresentationUrl(true);
                $logout_url = $presentation_url . '/api/xml?action=logout';

                if ($ilSetting->get('short_inst_name') != "") {
                    $title_prefix = $ilSetting->get('short_inst_name');
                } else {
                    $title_prefix = 'ILIAS';
                }
                $sso_tpl = new ilTemplate(
                    $this->pluginObj->getDirectory() . "/templates/default/tpl.perform_sso.html",
                    true,
                    true
                );
                $sso_tpl->setVariable('SPINNER_SRC', $this->pluginObj->getDirectory() . '/templates/js/spin.js');
                $sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
                $sso_tpl->setVariable('LOGOUT_URL', str_replace(['http://', 'https://'], '//', $logout_url));
                $sso_tpl->setVariable('URL', $url);
                $sso_tpl->setVariable('INFO_TXT', $this->pluginObj->txt('redirect_in_progress'));
                $sso_tpl->setVariable('OBJECT_TITLE', $this->object->getTitle());
                $sso_tpl->show();
                exit;
            }
        }
        // Fallback action
        $this->showContent();
    }

    public function viewContents(bool $has_access = false, string $by_type = 'content'): string
    {
        $server = ilAdobeConnectServer::getPresentationUrl();
        $this->tabs->activateTab('contents');

        $my_tpl = new ilTemplate($this->pluginObj->getDirectory() . '/templates/tpl.meeting_content.html', true, true);

        if ($this->object->readContents($by_type)) {
            // Get contents and records
            $contents = $this->object->searchContent([]);
            $view_mode = ilAdobeConnectContentTableGUI::MODE_VIEW;
            if ($has_access) {
                $view_mode = ilAdobeConnectContentTableGUI::MODE_EDIT;
            }

            $table = new ilAdobeConnectContentTableGUI($this, 'showContent', $by_type, $view_mode);
            $table->init();

            $data = [];
            $i = 0;
            foreach ($contents as $content) {
                $content_type = $content->getAttributes()->getAttribute('type');
                if ($content_type != $by_type) {
                    continue;
                }

                $icon = $this->object->getContentIconAttribute($content->getAttributes()->getAttribute('sco-id'));

                if ($icon[0] == 'archive' && $content->getAttributes()->getAttribute(
                        'date-end'
                    ) == '' && $by_type == 'content') {
                    // in this case, the content is a 'running' recording!!
                    continue;
                }

                $data[$i]['title'] = $content->getAttributes()->getAttribute('name');
                $data[$i]['type'] = $content->getAttributes()->getAttribute('type') == 'record' ? $this->pluginObj->txt(
                    'record'
                ) : $this->pluginObj->txt('content');

                $auth_mode = ilAdobeConnectServer::getSetting('auth_mode');
                switch ($auth_mode) {
                    default:
                        $data[$i]['rec_url'] = $server . $content->getAttributes()->getAttribute('url');
                        $this->ctrl->setParameter($this, 'record_url', urlencode($data[$i]['rec_url']));
                        $data[$i]['link'] = $this->ctrl->getLinkTarget($this, 'requestAdobeConnectContent');
                }

                $data[$i]['date_created'] =strtotime(substr($content->getAttributes()->getAttribute('date-created'), 0, 19));
                $data[$i]['description'] = $content->getAttributes()->getAttribute('description');
                if ($has_access && $content_type == $by_type) {
                    $content_id = $content->getAttributes()->getAttribute('sco-id');
                    $this->ctrl->setParameter($this, 'content_id', $content_id);
                    if ($content_type == 'content') {
                        $action = new ilAdvancedSelectionListGUI();
                        $action->setId('asl_' . $content_id . mt_rand(1, 50));
                        $action->setListTitle($this->lng->txt('actions'));
                        $action->addItem($this->lng->txt('edit'), '', $this->ctrl->getLinkTarget($this, 'editItem'));
                        $action->addItem(
                            $this->lng->txt('delete'),
                            '',
                            $this->ctrl->getLinkTarget($this, 'askDeleteContents')
                        );

                        $data[$i]['actions'] = $action->getHTML();
                    } else {
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

    public function viewRecords(bool $has_access = false, string $by_type = 'record'): string
    {
        $server = ilAdobeConnectServer::getPresentationUrl();
        $this->tabs->activateTab('contents');

        $my_tpl = new ilTemplate($this->pluginObj->getDirectory() . '/templates/tpl.meeting_content.html', true, true);

        if ($this->object->readContents($by_type)) {
            // Get contents and records
            $contents = $this->object->searchContent([]);

            if ($has_access) {
                $view_mode = ilAdobeConnectContentTableGUI::MODE_EDIT;
            }

            $table = new ilAdobeConnectRecordsTableGUI($this, 'showContent', $by_type, $view_mode);

            $table->init();

            $data = [];
            $i = 0;
            foreach ($contents as $content) {
                $content_type = $content->getAttributes()->getAttribute('type');
                if ($content_type != $by_type) {
                    continue;
                }

                $data[$i]['title'] = $content->getAttributes()->getAttribute('name');
                $data[$i]['type'] = $content->getAttributes()->getAttribute('type') == 'record' ? $this->pluginObj->txt(
                    'record'
                ) : $this->pluginObj->txt('content');

                $data[$i]['rec_url'] = $server . $content->getAttributes()->getAttribute('url');
                $this->ctrl->setParameter($this, 'record_url', urlencode($data[$i]['rec_url']));
                $data[$i]['link'] = $this->ctrl->getLinkTarget($this, 'requestAdobeConnectContent');

                $data[$i]['date_created'] = $content->getAttributes()->getAttribute('date-created')->getUnixTime();
                $data[$i]['description'] = $content->getAttributes()->getAttribute('description');
                if ($has_access && $content_type == $by_type) {
                    $content_id = $content->getAttributes()->getAttribute('sco-id');
                    $this->ctrl->setParameter($this, 'content_id', $content_id);
                    // @todo V10
                    $action = new ilAdvancedSelectionListGUI();
                    $action->setId('asl_' . $content_id . mt_rand(1, 50));
                    $action->setListTitle($this->lng->txt('actions'));
                    $action->addItem($this->lng->txt('edit'), '', $this->ctrl->getLinkTarget($this, 'editRecord'));
                    $action->addItem(
                        $this->lng->txt('delete'),
                        '',
                        $this->ctrl->getLinkTarget($this, 'askDeleteContents')
                    );

                    $data[$i]['actions'] = $action->getHTML();
                }
                ++$i;
            }
            $table->setData($data);

            $my_tpl->setVariable('CONTENT_TABLE', $table->getHTML());
        }
        return $my_tpl->get();
    }

    // CRS-GRP-MEMBER ADMINITRATION
    public function addCrsGrpMembers(): void
    {
        $this->tabs->activateTab('participants');
        $this->setSubTabs('participants');
        $this->tabs->activateSubTab("addCrsGrpMembers");

        $this->lng->loadLanguageModule('crs');

        $my_tpl = new ilTemplate(
            $this->pluginObj->getDirectory() . "/templates/default/tpl.meeting_participant_table.html",
            true,
            true
        );

        $oParticipants = $this->object->getParticipantsObject();

        /** @var $oParticipants  ilGroupParticipants */
        $admins = $oParticipants->getAdmins();
        $tutors = $oParticipants->getTutors();
        $members = $oParticipants->getMembers();

        $all_crs_members = array_unique(array_merge($admins, $tutors, $members));

        $counter = 0;
        $f_result_1 = null;
        foreach ($all_crs_members as $user_id) {
            if ($user_id > 0) {
                $tmp_user = new ilObjUser($user_id);

                $firstname = $tmp_user->getFirstname();
                $lastname = $tmp_user->getLastname();

                if ($tmp_user->hasPublicProfile() && $tmp_user->getPref('public_email') == 'y') {
                    $user_mail = $tmp_user->getEmail();
                } else {
                    $user_mail = '';
                }
            }

            $f_result_1[$counter]['checkbox'] = ilLegacyFormElementsUtil::formCheckbox('', 'usr_id[]', $user_id);
            $f_result_1[$counter]['user_name'] = $lastname . ', ' . $firstname;
            $f_result_1[$counter]['email'] = $user_mail;
            ++$counter;
        }

        // show Administrator Table
        $tbl_admin = new ilXAVCTableGUI($this, 'addCrsGrpMembers');
        $this->ctrl->setParameter($this, 'cmd', 'editParticipants');

        $tbl_admin->setTitle($this->lng->txt('crs_members'));
        $tbl_admin->setId('tbl_admins');
        $tbl_admin->setRowTemplate(
            $this->pluginObj->getDirectory() . "/templates/default/tpl.meeting_participant_row.html",
            false
        );

        $tbl_admin->addColumn('', 'checkbox', '1%', true);
        $tbl_admin->addColumn($this->pluginObj->txt('user_name'), 'user_name', '30%');
        $tbl_admin->addColumn($this->lng->txt('email'), 'email');
        $tbl_admin->setSelectAllCheckbox('usr_id[]');
        $tbl_admin->addMultiCommand('performAddCrsGrpMembers', $this->pluginObj->txt('add_crs_grp_members'));
        $tbl_admin->addCommandButton('editParticipants', $this->pluginObj->txt('cancel'));

        $tbl_admin->setData($f_result_1);
        $my_tpl->setVariable('ADMINS', $tbl_admin->getHTML());

        $this->tpl->setContent($my_tpl->get());
    }

    public function performAddCrsGrpMembers()
    {
        $user_ids = $this->retrieveListOfIntFrom(self::$REQUEST_POST, 'usr_id');
        if (count($user_ids) === 0) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('participants_select_one'));
            return $this->addCrsGrpMembers();
        }
        $this->tabs->activateTab('participants');
        $this->lng->loadLanguageModule('crs');

        $this->object->addCrsGrpMembers($this->object->getRefId(), $this->object->getScoId(), $user_ids);

        return $this->editParticipants();
    }

    public function updateParticipants(): void
    {
        global $DIC;

        //@todo V9 not sure about that ...
        $roles = $this->retrieveListOfStringFrom(self::$REQUEST_POST, 'roles');
        $xavc_status = $this->retrieveListOfStringFrom(self::$REQUEST_POST, 'xavc_status');

        $user_ids = $this->retrieveListOfIntFrom(self::$REQUEST_POST, 'usr_id');

        if (count($xavc_status) === 0 || count($user_ids) === 0) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('participants_select_one'));
            $this->editParticipants();
            return;
        }

        $xavc_options = [
            $this->txt('presenter') => 'host',
            $this->txt('moderator') => 'mini-host',
            $this->txt('participant') => 'view',
            $this->txt('denied') => 'denied'
        ];

        $local_roles = $DIC->rbac()->review()->getLocalRoles($this->object->getRefId());

        foreach ($user_ids as $selected_user) {
            if (array_key_exists($selected_user, $xavc_status)) {
                $selected_status = $xavc_status[$selected_user];
                $memberObj = new ilXAVCMembers($this->object->getRefId(), $selected_user);
                $memberObj->setStatus($xavc_options[$selected_status]);
                $memberObj->updateXAVCMember();

                $this->object->updateParticipant(
                    ilXAVCMembers::_lookupXAVCLogin((int) $selected_user),
                    $memberObj->getStatus()
                );
            }

            if (isset($roles[$selected_user])) {
                $deassign_roles = array_diff($local_roles, $roles[$selected_user]);
                if ($deassign_roles) {
                    foreach ($deassign_roles as $deassign_role_id) {
                        $DIC->rbac()->admin()->deassignUser($deassign_role_id, $selected_user);
                    }
                }

                foreach ($roles[$selected_user] as $role_id) {
                    $DIC->rbac()->admin()->assignUser($role_id, $selected_user);
                }
            }
        }
        $this->editParticipants();
        return;
    }

    public function showMembersGallery(): void
    {
        $this->tabs->activateTab('participants');
        $this->setSubTabs('participants');
        $this->tabs->activateSubTab('showMembersGallery');

        $provider = new ilAdobeConnectUsersGalleryCollectionProvider(
            ilAdobeConnectContainerParticipants::getInstanceByObjId($this->object->getId())
        );
        $gallery_gui = new ilUsersGalleryGUI($provider);
        $this->ctrl->setCmd('view');
        $gallery_gui->executeCommand();
    }

    public function requestAdobeConnectContent(): void
    {
        global $DIC;
        $ilSetting = $DIC->settings();

        $record_url = $this->retrieveStringFrom(self::$REQUEST_GET, 'record_url');
        if ($record_url == '') {
            $this->showContent();
            return;
        }

        $url = ilUtil::stripSlashes($record_url);

        $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
        $ilAdobeConnectUser->ensureAccountExistence();

        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        if(array_key_exists('xavc_last_sso_sessid', $_SESSION)) {
            $xmlAPI->logout($_SESSION['xavc_last_sso_sessid']);
        }

        $session = $ilAdobeConnectUser->loginUser();
        $_SESSION['xavc_last_sso_sessid'] = $session;

        $url = ilUtil::appendUrlParameterString($url, 'session=' . $session);

        $presentation_url = ilAdobeConnectServer::getPresentationUrl(true);
        $logout_url = $presentation_url . '/api/xml?action=logout';

        if ($ilSetting->get('short_inst_name') != "") {
            $title_prefix = $ilSetting->get('short_inst_name');
        } else {
            $title_prefix = 'ILIAS';
        }

        $sso_tpl = new ilTemplate(
            $this->pluginObj->getDirectory() . "/templates/default/tpl.perform_sso.html",
            true,
            true
        );
        $sso_tpl->setVariable('SPINNER_SRC', $this->pluginObj->getDirectory() . '/templates/js/spin.js');
        $sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
        $sso_tpl->setVariable('LOGOUT_URL', str_replace(['http://', 'https://'], '//', $logout_url));
        $sso_tpl->setVariable('URL', $url);
        $sso_tpl->setVariable('INFO_TXT', $this->pluginObj->txt('redirect_in_progress'));
        $sso_tpl->setVariable('OBJECT_TITLE', $this->object->getTitle());
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
        if (!$user_ids || !is_array($user_ids)) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('select_one'), true);
            return false;
        }

        $xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

        $added_users = 0;
        foreach ($user_ids as $usr_id) {
            if (!ilObjUser::_lookupLogin((int) $usr_id)) {
                continue;
            }

            switch ($type) {
                case 'add_admin':
                    $xavcRoles->addAdministratorRole((int) $usr_id);
                    break;
                case 'add_member':
                default:
                    $xavcRoles->addMemberRole((int) $usr_id);
                    break;
            }
            $this->addParticipant((int) $usr_id);
            ++$added_users;
        }
        $this->tpl->setOnScreenMessage(
            'success',
            $this->plugin->txt('assigned_users' . ($added_users == 1 ? '_s' : '_p')),
            true
        );
        $this->ctrl->redirectByClass(ilObjAdobeConnectGUI::class, 'editParticipants');
    }

    /*
 * User joins XAVC_object
 */
    public function join(): void
    {
        $user_id = $this->user->getId();

        $xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());

        $current_users = $xavcRoles->getUsers();

        if (in_array($user_id, $current_users)) {
            $this->tpl->setOnScreenMessage('info', $this->txt('already_member'));
        }
        if (!$user_id) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('user_not_known'));
            $this->editParticipants();
            return;
        }

        $xavcRoles->addMemberRole($user_id);

        //check if there is an adobe connect account at the ac-server
        $ilAdobeConnectUser = new ilAdobeConnectUserUtil($user_id);
        $ilAdobeConnectUser->ensureAccountExistence();

        $role_map = ilAdobeConnectServer::getRoleMap();

        $status = false;
        $oParticipants = null;
        $owner = 0;

        if (count($this->object->getParticipantsObject()->getParticipants()) > 0) {
            $user_is_admin = $this->object->getParticipantsObject()->isAdmin($user_id);
            $user_is_tutor = $this->object->getParticipantsObject()->isTutor($user_id);
            $owner = ilObject::_lookupOwner($this->object->getParticipantsObject()->getObjId());
            if ($owner == $this->user->getId()) {
                $status = $role_map[$this->object->getParticipantsObject()->getType() . '_owner'];
            } else {
                if ($user_is_admin) {
                    $status = $role_map[$this->object->getParticipantsObject()->getType() . '_admin'];
                } else {
                    if ($user_is_tutor) {
                        $status = $role_map[$this->object->getParticipantsObject()->getType() . '_tutor'];
                    } else {
                        $status = $role_map[$this->object->getParticipantsObject()->getType() . '_member'];
                    }
                }
            }
        }

        if (!$status) {
            if ($owner == $this->user->getId()) {
                $status = 'host';
            } else {
                $status = 'view';
            }
        }

        $is_member = ilXAVCMembers::_isMember($user_id, $this->object->getRefId());
        // local member table

        $xavcMemberObj = new ilXAVCMembers($this->object->getRefId(), $user_id);
        $xavcMemberObj->setStatus($status);
        $xavcMemberObj->setScoId($this->object->getScoId());

        if ($is_member) {
            $xavcMemberObj->updateXAVCMember();
        } else {
            $xavcMemberObj->insertXAVCMember();
        }

        $this->object->updateParticipant(ilXAVCMembers::_lookupXAVCLogin($user_id), $status);

        if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
            ilObjAdobeConnect::addToFavourites($user_id, $this->object->getRefId());
        }

        $this->ctrl->setParameter($this, 'cmd', 'showContent');
        $this->ctrl->redirect($this, 'showContent');
    }

    public function leave(): void
    {
        $user_id = $this->user->getId();
        $ref_id = $this->object->getRefId();

        $xavcRoles = new ilAdobeConnectRoles($ref_id);

        $xavcRoles->detachMemberRole($user_id);
        ilXAVCMembers::deleteXAVCMember($user_id, $ref_id);

        $xavc_login = ilXAVCMembers::_lookupXAVCLogin($user_id);
        $this->object->deleteParticipant($xavc_login);

        $this->tpl->setOnScreenMessage('info', $this->txt('participants_detached_successfully'));
        $this->showContent();
    }

    // detach member role
    public function detachMember(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $this->tabs->activateTab('participants');
        $this->setSubTabs('participants');
        $this->tabs->activateSubTab("editParticipants");

        $user_ids = $this->retrieveListOfIntFrom(self::$REQUEST_POST, 'usr_id');

        switch (count($user_ids)) {
            case 0:
                $this->tpl->setOnScreenMessage('info', $this->txt('participants_select_one'));
                $this->editParticipants();
                return;
            case 1:
                $header_text = $this->pluginObj->txt('sure_delete_participant_s');
                break;
            default:
                $header_text = $this->pluginObj->txt('sure_delete_participant_p');
                break;
        }

        // CONFIRMATION
        $c_gui = new ilConfirmationGUI();
        $c_gui->setFormAction($this->ctrl->getFormAction($this, 'performDetachMember'));
        $c_gui->setHeaderText($header_text);

        $c_gui->setCancel($this->lng->txt('cancel'), 'editParticipants');
        $c_gui->setConfirm($this->lng->txt('confirm'), 'performDetachMember');

        foreach ($user_ids as $user_id) {
            $user_name = ilObjUser::_lookupName((int) $user_id);
            $c_gui->addItem('usr_id[]', $user_id, $user_name['firstname'] . ' ' . $user_name['lastname']);
        }

        $tpl->setContent($c_gui->getHTML());
    }

    public function performDetachMember(): void
    {
        $xavcRoles = new ilAdobeConnectRoles($this->object->getRefId());
        $detach_user_ids = $this->retrieveListOfIntFrom(self::$REQUEST_POST, 'usr_id');

        foreach ($detach_user_ids as $usr_id) {
            $xavcRoles->detachMemberRole((int) $usr_id);
            if ($xavcRoles->isAdministrator((int) $usr_id)) {
                $xavcRoles->detachAdministratorRole((int) $usr_id);
            }

            $xavc_login = ilXAVCMembers::_lookupXAVCLogin((int) $usr_id);
            ilXAVCMembers::deleteXAVCMember((int) $usr_id, $this->object->getRefId());
            $this->object->deleteParticipant($xavc_login);

            //remove from pd
            ilObjAdobeConnect::removeFromFavourites((int) $usr_id, $this->object->getRefId());
        }
        $this->tpl->setOnScreenMessage('info', $this->txt('participants_detached_successfully'));
        $this->editParticipants();
    }

    /**
     *  Add user to the Adobe Connect server
     */
    public function addParticipant(int $a_user_id): void
    {
        $this->tabs->activateTab('participants');

        //check if there is an adobe connect account at the ac-server
        $ilAdobeConnectUser = new ilAdobeConnectUserUtil((int) $a_user_id);
        $ilAdobeConnectUser->ensureAccountExistence();

        // add to desktop
        if (ilAdobeConnectServer::getSetting('add_to_desktop') == 1) {
            ilObjAdobeConnect::addToFavourites((int) $a_user_id, $this->object->getRefId());
        }
        $is_member = ilXAVCMembers::_isMember((int) $a_user_id, $this->object->getRefId());

        // local member table
        if (!$is_member) {
            $xavcMemberObj = new ilXAVCMembers((int) $this->object->getRefId(), (int) $a_user_id);
            $xavcMemberObj->setParticipantStatus();
            $xavcMemberObj->setScoId($this->object->getScoId());
            $xavcMemberObj->insertXAVCMember();

            $this->object->updateParticipant(
                ilXAVCMembers::_lookupXAVCLogin((int) $a_user_id),
                $xavcMemberObj->getStatus()
            );
            $this->tpl->setOnScreenMessage('info', $this->txt('participant_added_successfully'));
        } else {
            if ($is_member) {
                //only update at adobe connect server
                $this->object->updateParticipant(
                    ilXAVCMembers::_lookupXAVCLogin((int) $a_user_id),
                    ilXAVCMembers::_lookupStatus((int) $a_user_id, $this->object->getRefId())
                );
                $this->tpl->setOnScreenMessage('info', $this->pluginObj->txt('is_already_participant'));
            }
        }
    }

    private function initContentSearchForm(): void
    {
        if ($this->csform instanceof ilPropertyFormGUI) {
            return;
        }

        $this->csform = new ilPropertyFormGUI();
        $this->csform->setFormAction($this->ctrl->getFormAction($this, 'searchContentFile'));
        $this->csform->setTitle($this->txt('add_content_from_ilias'));

        $textField = new ilTextInputGUI($this->txt('search_term'), 'search_query');
        $textField->setRequired(true);
        $this->csform->addItem($textField);

        $this->csform->addCommandButton('searchContentFile', $this->lng->txt('search'));
        $this->csform->addCommandButton('showContent', $this->txt('cancel'));
    }

    public function showAddContent(): void
    {
        $this->tabs->activateTab('contents');
        $my_tpl = new ilTemplate($this->pluginObj->getDirectory() . '/templates/tpl.add_content.html', true, true);

        //Add content
        $this->initFormContent(self::CONTENT_MOD_ADD);
        $my_tpl->setVariable('FORM_ADD_CONTENT', $this->cform->getHTML());

        //Add content from ILIAS
        $this->initContentSearchForm();
        $my_tpl->setVariable('FORM_ADD_CONTENT_FROM_ILIAS', $this->csform->getHTML());

        $this->tpl->setContent($my_tpl->get());
    }

    public function addContent(): void
    {
        $this->initFormContent(self::CONTENT_MOD_ADD);
        if ($this->cform->checkInput()) {
            $fdata = $this->cform->getInput('file');

            $targetDir = dirname(ilFileUtils::ilTempnam());
            $targetFilePath = $targetDir . '/' . $fdata['name'];

            ilFileUtils::moveUploadedFile($fdata['tmp_name'], $fdata['name'], $targetFilePath);
            try {
                $filemame = strlen($this->cform->getInput('tit')) ? $this->cform->getInput('tit') : $fdata['name'];
                $url = $this->object->addContent($filemame, $this->cform->getInput('des'));
                if (!strlen($url)) {
                    throw new ilAdobeConnectContentUploadException('add_cnt_err');
                }

                $this->object->uploadFile($url, $targetFilePath);

                @unlink($targetFilePath);
                $this->tpl->setOnScreenMessage('success', $this->txt('virtualClassroom_content_added'));
            } catch (ilAdobeConnectException $e) {
                @unlink($targetFilePath);
                $this->tpl->setOnScreenMessage('failure', $this->txt($e->getMessage()));
                $this->cform->setValuesByPost();
                $this->showAddContent();
                return;
            }
        } else {
            $this->cform->setValuesByPost();
            $this->showAddContent();
            return;
        }

        $this->showContent();
    }

    public function cancelSearchContentFile(): void
    {
        unset($_SESSION['contents']['search_result']);
        $this->showAddContent();
    }

    public function searchContentFile(): void
    {
        global $DIC;
        $ilAccess = $DIC->access();

        $this->initContentSearchForm();
        if ($this->csform->checkInput()) {
            $allowedExt = array(
                'ppt',
                'pptx',
                'flv',
                'swf',
                'pdf',
                'gif',
                'jpg',
                'png',
                'mp3',
                'html'
            );

            $result = [];

            if (ilSearchSettings::getInstance()->enabledLucene()) {
                $qp = new ilLuceneQueryParser('+(type:file) ' . $this->csform->getInput('search_query'));
                $qp->parse();
                $searcher = ilLuceneSearcher::getInstance($qp);
                $searcher->search();

                $filter = ilLuceneSearchResultFilter::getInstance($this->user->getId());
                $filter->addFilter(new ilLucenePathFilter(ROOT_FOLDER_ID));
                $filter->setCandidates($searcher->getResult());
                $filter->filter();

                foreach ($filter->getResultIds() as $refId => $objId) {
                    $obj = ilObjectFactory::getInstanceByRefId($refId);

                    if (!in_array(strtolower($obj->getFileExtension()), $allowedExt)) {
                        continue;
                    }

                    if (!$ilAccess->checkAccessOfUser($this->user->getId(), 'read', '', $refId, '', $objId)) {
                        continue;
                    }

                    $result[$obj->getId()] = $obj->getId();
                }
            } else {
                $query_parser = new ilQueryParser($this->csform->getInput('search_query'));
                $query_parser->setCombination(ilQueryParser::QP_COMBINATION_OR);
                $query_parser->parse();
                if (!$query_parser->validate()) {
                    $this->tpl->setOnScreenMessage('info', $query_parser);
                    $this->csform->setValuesByPost();
                    $this->showAddContent();
                    return;
                }

                $object_search = new ilLikeObjectSearch($query_parser);

                $object_search->setFilter(['file']);

                $res = $object_search->performSearch();
                $res->setUserId($this->user->getId());
                $res->setMaxHits(999999);
                $res->filter(ROOT_FOLDER_ID, false);
                $res->setRequiredPermission('read');

                foreach ($res->getUniqueResults() as $entry) {
                    $obj = ilObjectFactory::getInstanceByRefId($entry['ref_id']);

                    if (!in_array(strtolower($obj->getFileExtension()), $allowedExt)) {
                        continue;
                    }

                    $result[$obj->getId()] = $obj->getId();
                }
            }

            if (count($result) > 0) {
                $this->showFileSearchResult($result);
                $_SESSION['contents']['search_result'] = $result;
            } else {
                $this->tpl->setOnScreenMessage('info', $this->txt('files_matches_in_no_results'));
                $this->csform->setValuesByPost();
                $this->showAddContent();
            }
        } else {
            $this->csform->setValuesByPost();
            $this->showAddContent();
        }
    }

    /**
     * Shows $results in a table
     * @param array|null $results
     */
    public function showFileSearchResult(?array $results = null): void
    {
        global $DIC;
        $tree = $DIC->repositoryTree();

        if (!$results && isset($_SESSION['contents']['search_result'])) {
            // this is for table sorting
            $results = $_SESSION['contents']['search_result'];
        }
        if (!$results) {
            $this->showAddContent();
            return;
        }

        $this->tabs->activateTab('contents');

        $table = new ilTable2GUI($this, 'showFileSearchResult');

        $table->setLimit(2147483647);

        $table->setTitle($this->txt('files'));

        $table->setDefaultOrderField('path');

        $table->addColumn('', '', '1%', true);
        $table->addColumn($this->txt('title'), 'title', '30%');
        $table->addColumn($this->lng->txt('path'), 'path', '70%');

        $table->setFormAction($this->ctrl->getFormAction($this, 'addContentFromILIAS'));

        $table->setRowTemplate('tpl.content_file_row.html', $this->pluginObj->getDirectory());

        $table->setId('xavc_cs_' . $this->object->getId());
        $table->setPrefix('xavc_cs_' . $this->object->getId());

        $table->addCommandButton('addContentFromILIAS', $this->txt('add'));
        $table->addCommandButton('cancelSearchContentFile', $this->txt('cancel'));

        $data = [];
        $i = 0;

        foreach ($results as $file_id) {
            $title = ilObject::_lookupTitle((int) $file_id);
            $reference_ids = ilObject::_getAllReferences((int) $file_id);
            $file_ref = array_shift($reference_ids);
            $path_arr = $tree->getPathFull((int) $file_ref);
            $counter = 0;
            $path = '';
            foreach ($path_arr as $element) {
                if ($counter++) {
                    $path .= " > ";
                    $path .= $element['title'];
                } else {
                    $path .= $this->lng->txt('repository');
                }
            }

            $data[$i]['check_box'] = ilLegacyFormElementsUtil::formRadioButton(0, 'file_id', $file_id);
            $data[$i]['title'] = $title;
            $data[$i]['path'] = $path;

            ++$i;
        }
        $table->setData($data);

        $this->tpl->setContent($table->getHTML());
    }

    /**
     *  Add a content from ILIAS to the Adobe Connect server
     * @return string cmd
     * @throws \ILIAS\Filesystem\Exception\IOException
     */
    public function addContentFromILIAS(): string
    {
        $file_id = $this->retrieveIntFrom(self::$REQUEST_POST, 'file_id');
        if ($file_id === 0) {
            $this->tpl->setOnScreenMessage('info', $this->txt('content_select_one'));
            return $this->showFileSearchResult($_SESSION['contents']['search_result']);
        }

        /** @noinspection PhpUndefinedClassInspection */
        //@todo V10  Maybe here is a refactoring needed for possible rcid usage for the file storage
        $fss = new ilFSStorageFile($file_id);

        /** @noinspection PhpUndefinedClassInspection */
        $version_subdir = '/' . sprintf("%03d", ilObjFileAccess::_lookupVersion($file_id));
        $file_name = ilObjFile::_lookupFileName($file_id);
        $object_title = ilObject::_lookupTitle($file_id);

        $file = $fss->getAbsolutePath() . $version_subdir . '/' . $file_name;

        try {
            $this->object->uploadFile($this->object->addContent($object_title, ''), $file, $object_title);
            unset($_SESSION['contents']['search_result']);
            $this->tpl->setOnScreenMessage('success', $this->txt('virtualClassroom_content_added'));
            return $this->showContent();
        } catch (ilAdobeConnectDuplicateContentException $e) {
            $this->tpl->setOnScreenMessage('failure', $this->txt($e->getMessage()));
            return $this->showFileSearchResult($_SESSION['contents']['search_result']);
        }
    }

    public function editItem(): void
    {
        $this->tabs->activateTab('contents');

        $content_id = $this->retrieveIntFrom(self::$REQUEST_GET, 'content_id');
        $this->initFormContent(self::CONTENT_MOD_EDIT, $content_id);

        if ($this->ctrl->getCmd() == 'editItem' || $this->ctrl->getCmd() == 'editRecord') {
            $this->setValuesFromContent((string) $content_id);
        }
        $this->tpl->setContent($this->cform->getHTML());
    }

    public function editRecord(): void
    {
        $this->is_record = true;
        $this->editItem();
    }

    protected function updateRecord(): void
    {
        $this->is_record = true;
        $this->updateContent();
    }

    public function updateContent(): ?bool
    {
        $content_id = $this->retrieveIntFrom(self::$REQUEST_GET, 'content_id');
        $this->initFormContent(self::CONTENT_MOD_EDIT, $content_id);
        if ($this->cform->checkInput()) {
            $fdata = $this->cform->getInput('file');
            $target = '';
            if ($fdata['name'] != '') {
                $target = dirname(ilFileUtils::ilTempnam());
                ilFileUtils::moveUploadedFile($fdata['tmp_name'], $fdata['name'], $target . '/' . $fdata['name']);
            }

            try {
                $title = strlen($this->cform->getInput('tit')) ? $this->cform->getInput('tit') : $fdata['name'];
                $this->object->updateContent($content_id, $title, $this->cform->getInput('des'));
                if ($fdata['name'] != '') {
                    $this->object->uploadFile(
                        $this->object->uploadContent($content_id),
                        $target . '/' . $fdata['name']
                    );
                    @unlink($target . '/' . $fdata['name']);
                }
                $this->tpl->setOnScreenMessage('success', $this->txt('virtualClassroom_content_updated'), true);
                $this->showContent();
                return true;
            } catch (ilAdobeConnectException $e) {
                if ($fdata['name'] != '') {
                    @unlink($target . '/' . $fdata['name']);
                }
                $this->tpl->setOnScreenMessage('failure', $this->txt($e->getMessage()));
                $this->cform->setValuesByPost();
                return $this->editItem();
            }
        } else {
            $this->cform->setValuesByPost();
            return $this->editItem();
        }
    }

    public function askDeleteContents()
    {
        $content_ids = $this->retrieveListOfStringFrom(self::$REQUEST_GET, 'content_id');

        if (count($content_ids) == 0) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('content_select_one'));
            $this->showContent();

            return true;
        }

        $this->tabs->activateTab('contents');

        $confirm = new ilConfirmationGUI();
        $confirm->setFormAction($this->ctrl->getFormAction($this, 'showContent'));
        $confirm->setHeaderText($this->txt('sure_delete_contents'));
        $confirm->setConfirm($this->txt('delete'), 'deleteContents');
        $confirm->setCancel($this->txt('cancel'), 'showContent');

        $this->object->readContents();

        $contents_found = false;
        foreach ($content_ids as $content_id) {
            $content = $this->object->getContent($content_id);
            if (!$content) {
                continue;
            }
            $contents_found = true;
            $confirm->addItem('content_id[]', $content_id, $content->getAttributes()->getAttribute('name'));
        }

        if ($contents_found) {
            $this->tpl->setContent($confirm->getHTML());
        } else {
            return $this->showContent();
        }
    }

    public function deleteContents(): bool
    {
        $content_ids = $this->retrieveListOfIntFrom(self::$REQUEST_POST, 'content_id');
        if (count($content_ids) === 0) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('content_select_one'));
            $this->showContent();
            return false;
        }

        foreach ($content_ids as $content_id) {
            $this->object->deleteContent($content_id);
        }

        $this->tpl->setOnScreenMessage('success', $this->txt('virtualClassroom_content_deleted'), true);
        $this->showContent();
        return true;
    }

    /**
     * Shows a form to add or edit content
     * @param int $a_mode
     * @param int $a_content_id optional content id
     */
    protected function initFormContent($a_mode, $a_content_id = 0): void
    {
        if ($this->cform === null) {
            $this->cform = new ilPropertyFormGUI();

            switch ($a_mode) {
                case self::CONTENT_MOD_EDIT:
                    $positive_cmd = ($this->is_record ? 'updateRecord' : 'updateContent');
                    $request_content_id = $this->retrieveFromRequest('content_id', self::$TYPE_INT);

                    // Header
                    $this->ctrl->setParameter($this, 'content_id', $request_content_id);
                    $this->cform->setTitle($this->txt('edit_content'));
                    // Buttons
                    $this->cform->addCommandButton($positive_cmd, $this->txt('save'));
                    $this->cform->addCommandButton('showContent', $this->txt('cancel'));

                    // Form action
                    if ($a_content_id) {
                        $this->ctrl->setParameter($this, 'content_id', $a_content_id);
                    }
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

            if ($this->is_record == false) {
                // File
                $fil = new ilFileInputGUI($this->txt('file'), 'file');
                if ($a_mode == self::CONTENT_MOD_ADD) {
                    $fil->setRequired(true);
                }

                $content_file_types = strlen(
                    ilAdobeConnectServer::getSetting('content_file_types')
                ) > 1 ? ilAdobeConnectServer::getSetting(
                    'content_file_types'
                ) : 'ppt, pptx, flv, swf, pdf, gif, jpg, png, mp3, html';
                $fil->setSuffixes(explode(',', str_replace(' ', '', $content_file_types)));
                $this->cform->addItem($fil);
            }
        }
    }

    /**
     *  Initializes the form
     */
    protected function setValuesFromContent(string $content_id): void
    {
        $this->object->readContents();
        $contents = $this->object->searchContent(['sco-id' => $content_id]);
        /** @var $content ilAdobeConnectContent */
        $content = $contents[0];

        $this->cform->setValuesByArray(
            [
                'tit' => $content->getAttributes()->getAttribute('name'),
                'des' => $content->getAttributes()->getAttribute('description'),
            ]
        );
    }

    public function initXAVCTemplates(): void
    {
        foreach (ilXAVCTemplates::XAVC_TEMPLATES as $type) {
            $this->xavc_templates[$type] = ilXAVCTemplates::_getInstanceByType($type);
        }
    }

    protected function initCreationForms($type): array
    {
        $this->initXAVCTemplates();

        $selected_templates = unserialize(
            ilAdobeConnectServer::getSetting('obj_creation_settings'),
            ['allowed_classes' => false]
        );

        $creation_forms = [];
        $key = 100;
        foreach ($this->xavc_templates as $type => $item) {
            if ($selected_templates == false) {
                $creation_forms[$key] = $this->initCreateForm($item->getType());
                $key++;
            } else {
                if (is_array($selected_templates) && in_array($item->getType(), $selected_templates)) {
                    $creation_forms[$key] = $this->initCreateForm($type);
                    $key++;
                }
            }
        }

        return $creation_forms;
    }

    public function initCreateForm($item): ilPropertyFormGUI
    {
        global $DIC;
        $ilUser = $DIC->user();

        $xavc_template_type = $item ?: ilXAVCTemplates::TPL_DEFAULT;
        $tpl_id = $this->retrieveStringFrom(self::$REQUEST_POST, 'tpl_id');

        if (in_array($tpl_id, $this->xavc_templates)) {
            $xavc_template_type = $tpl_id;
        }

        $this->initXAVCTemplates();
        $template_settings = $this->xavc_templates[$item];

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObj->txt($template_settings->getLangVar()));
        // title
        $title = new ilTextInputGUI($this->pluginObj->txt('title'), 'title');
        $title->setRequired(true);

        // description
        $description = new ilTextAreaInputGUI($this->pluginObj->txt('description'), 'desc');

        // contact_info_val
        $civ = [];
        if ($ilUser->getPref('public_profile') == "y") {
            if ($ilUser->getPref('public_title')) {
                $civ_title = $ilUser->getUTitle() . ' ';
            }

            $civ[] = $civ_title . $ilUser->getFirstname() . ' ' . $ilUser->getLastname();
            if ($ilUser->getPref('public_email')) {
                $civ[] = $ilUser->getEmail();
            }
            if ($ilUser->getPref('public_phone_office') && strlen($ilUser->getPhoneOffice()) > 1) {
                $civ[] = $this->pluginObj->txt('office') . ': ' . $ilUser->getPhoneOffice();
            }
            if ($ilUser->getPref('public_phone_mobile') && strlen($ilUser->getPhoneMobile()) > 1) {
                $civ[] = $this->pluginObj->txt('mobile') . ': ' . $ilUser->getPhoneMobile();
            }
        }

        $contact_info_value = implode(', ', $civ);

        // owner
        $owner = new ilTextInputGUI($this->lng->txt('owner'), 'owner');
        $owner->setInfo($this->pluginObj->txt('owner_info'));

        $owner->setValue(ilObjUser::_lookupLogin($ilUser->getId()));

        $radio_time_type = new ilRadioGroupInputGUI(
            $this->pluginObj->txt('time_type_selection'),
            'time_type_selection'
        );

        // option: permanent room
        if (ilAdobeConnectServer::getSetting('enable_perm_room', '1')) {
            $permanent_room = new ilRadioOption($this->pluginObj->txt('permanent_room'), 'permanent_room');
            $permanent_room->setInfo($this->pluginObj->txt('permanent_room_info'));
            $radio_time_type->addOption($permanent_room);
            $radio_time_type->setValue('permanent_room');
        }
        // option: date selection
        $opt_date = new ilRadioOption($this->pluginObj->txt('start_date'), 'date_selection');
        if ($template_settings->getStartdateHide() == '0') {
            // start date
            $sd = new ilDateTimeInputGUI($this->pluginObj->txt('start_date'), 'start_date');

            $serverConfig = ilAdobeConnectServer::_getInstance();

            $now = strtotime('+3 minutes');
            $minTime = new ilDateTime($now + $serverConfig->getScheduleLeadTime() * 60 * 60, IL_CAL_UNIX);

            $sd->setDate($minTime);
            $sd->setShowTime(true);
            $sd->setRequired(true);
            $opt_date->addSubItem($sd);
        }

        if ($template_settings->getDurationHide() == '0') {
            $duration = new ilDurationInputGUI($this->pluginObj->txt("duration"), "duration");
            $duration->setRequired(true);
            $duration->setHours('2');
            $opt_date->addSubItem($duration);
        }
        $radio_time_type->addOption($opt_date);
        $radio_time_type->setRequired(true);
        if (!ilAdobeConnectServer::getSetting('enable_perm_room', '1')) {
            $radio_time_type->setValue('date_selection');
        }

        // access-level of the meeting room
        $radio_access_level = new ilRadioGroupInputGUI($this->pluginObj->txt('access'), 'access_level');
        $opt_private = new ilRadioOption(
            $this->pluginObj->txt('private_room'),
            ilObjAdobeConnect::ACCESS_LEVEL_PRIVATE
        );
        $opt_protected = new ilRadioOption(
            $this->pluginObj->txt('protected_room'),
            ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED
        );
        $opt_public = new ilRadioOption($this->pluginObj->txt('public_room'), ilObjAdobeConnect::ACCESS_LEVEL_PUBLIC);

        $radio_access_level->addOption($opt_private);
        $radio_access_level->addOption($opt_protected);
        $radio_access_level->addOption($opt_public);
        $radio_access_level->setValue(ilObjAdobeConnect::ACCESS_LEVEL_PROTECTED);

        $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
        $ilAdobeConnectUser->ensureAccountExistence();
        $xavc_login = $ilAdobeConnectUser->getXAVCLogin();
        $folder_id = $ilAdobeConnectUser->ensureUserFolderExistence($xavc_login);

        if ($template_settings->getReuseExistingRoomsHide() == '0') {
            $all_scos = (array) ilObjAdobeConnect::getScosByFolderId($folder_id);
            $local_scos = (array) ilObjAdobeConnect::getLocalScos();
            $free_scos = array();
            if ($all_scos) {
                foreach ($all_scos as $sco) {
                    $sco_ids[] = $sco['sco_id'];
                }

                $free_scos = array_diff($sco_ids, $local_scos);
            }

            if (!$free_scos) {
                $hidden_creation_type = new ilHiddenInputGUI('creation_type');
                $hidden_creation_type->setValue('new_vc');
                $form->addItem($hidden_creation_type);

                $advanced_form_item = $form;
                $afi_add_method = 'addItem';
            } else {
                $radio_grp = new ilRadioGroupInputGUI($this->pluginObj->txt('choose_creation_type'), 'creation_type');
                $radio_grp->setRequired(true);

                $radio_new = new ilRadioOption($this->pluginObj->txt('create_new'), 'new_vc');
                $radio_existing = new ilRadioOption($this->pluginObj->txt('select_existing'), 'existing_vc');

                $radio_grp->setValue('new_vc');
                $radio_grp->addOption($radio_new);

                $advanced_form_item = $radio_new;
                $afi_add_method = 'addSubItem';
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

            if ($template_settings->getAccessLevelHide() == 0) {
                $advanced_form_item->{$afi_add_method}($radio_access_level);
            }
            $advanced_form_item->{$afi_add_method}($radio_time_type);
            $advanced_form_item->{$afi_add_method}($owner);

            if ($free_scos && $radio_existing) {
                $radio_existing = new ilRadioOption($this->pluginObj->txt('select_existing'), 'existing_vc');
                $radio_grp->addOption($radio_existing);
                $form->addItem($radio_grp);

                foreach ($free_scos as $fs) {
                    $options[$fs] = $all_scos[$fs]['sco_name'];
                }
                $available_rooms = new ilSelectInputGUI($this->pluginObj->txt('available_rooms'), 'available_rooms');
                $available_rooms->setOptions($options);
                $available_rooms->setInfo($this->pluginObj->txt('choose_existing_room'));
                $radio_existing->addSubItem($available_rooms);

                $instructions_3 = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions_3');
                $instructions_3->setRows(self::CREATION_FORM_TA_ROWS);
                $radio_existing->addSubItem($instructions_3);

                $contact_info_3 = new ilTextAreaInputGUI(
                    $this->pluginObj->txt("contact_information"),
                    "contact_info_3"
                );
                $contact_info_3->setValue($contact_info_value);
                $contact_info_3->setRows(self::CREATION_FORM_TA_ROWS);
                $radio_existing->addSubItem($contact_info_3);
            }
        } else {
            $form->addItem($title);
            $form->addItem($description);

            $contact_info_2 = new ilTextAreaInputGUI($this->pluginObj->txt("contact_information"), "contact_info_2");
            $contact_info_2->setRows(self::CREATION_FORM_TA_ROWS);
            $contact_info_2->setValue($contact_info_value);
            $form->addItem($contact_info_2);

            if ($template_settings->getAccessLevelHide() == 0) {
                $form->addItem($radio_access_level);
            }

            $instructions_2 = new ilTextAreaInputGUI($this->lng->txt('exc_instruction'), 'instructions_2');
            $instructions_2->setRows(self::CREATION_FORM_TA_ROWS);
            $form->addItem($instructions_2);
            if (ilAdobeConnectServer::getSetting('default_perm_room') && ilAdobeConnectServer::getSetting(
                    'enable_perm_room',
                    '1'
                )) {
                $info_text = $this->pluginObj->txt('smpl_permanent_room_enabled');
            } else {
                $time = date('H:i', strtotime('+2 hours'));
                $info_text = sprintf($this->pluginObj->txt('smpl_permanent_room_disabled'), $time);
            }
            $info = new ilNonEditableValueGUI($this->lng->txt('info'), 'info_text');
            $info->setValue($info_text);
            $form->addItem($info);
        }

        $tpl_id = new ilHiddenInputGUI('tpl_id');
        $tpl_id->setValue($xavc_template_type);
        $form->addItem($tpl_id);

        if (ilAdobeConnectServer::getSetting('use_meeting_template')) {
            $use_meeting_template = new ilCheckboxInputGUI(
                $this->pluginObj->txt('use_meeting_template'),
                'use_meeting_template'
            );
            $use_meeting_template->setChecked(true);
            $form->addItem($use_meeting_template);
        }

        // language selector
        $lang_selector = new ilSelectInputGUI($this->lng->txt('language'), 'ac_language');
        $adobe_langs = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'tr', 'ru', 'ja', 'zh', 'ko'];
        $this->lng->loadLanguageModule('meta');
        foreach ($adobe_langs as $lang) {
            $lang_options[$lang] = $this->lng->txt('meta_l_' . $lang);
        }
        $lang_selector->setOptions($lang_options);
        $form->addItem($lang_selector);

        $html_client = new ilCheckboxInputGUI($this->pluginObj->txt('html_client'), 'html_client');
        $html_client->setInfo($this->pluginObj->txt('html_client_info'));
        $form->addItem($html_client);

        $form->addCommandButton('save', $this->pluginObj->txt($this->getType() . '_add'));
        $form->addCommandButton('cancelCreation', $this->lng->txt('cancel'));

        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    public function getFreeMeetingSlotTable($meetings): array
    {
        $meetingsByDay = [];

        $srv = ilAdobeConnectServer::_getInstance();
        $buffer_before = $srv->getBufferBefore();
        $buffer_after = $srv->getBufferAfter();

        foreach ($meetings as $m) {
            if ($this->object && $this->object->getId() && $m->id == $this->object->getId()) {
                continue;
            }

            $day0s = date('Y-m-d', $m->start_date - $buffer_before);
            $day1s = date('Y-m-d', $m->end_date + $buffer_after);
            $day1 = strtotime($day1s);

            if ($day0s == $day1s) {
                // why?	....condition results false everytime.....
                $h0 = date('H', $m->start_date - $buffer_before) * 60 + floor(
                        date(
                            'i',
                            $m->start_date - $buffer_before
                        ) / 15
                    ) * 15;
                $h1 = date('H', $m->end_date + $buffer_after) * 60 + floor(
                        date(
                            'i',
                            $m->end_date + $buffer_after
                        ) / 15
                    ) * 15;

                for ($i = $h0; $i <= $h1; $i += 15) {
                    $meetingsByDay[$day0s][(string) $i][] = $m;
                }
            } else {
                $h0 = date('H', $m->start_date - $buffer_before) * 60 + floor(
                        date(
                            'i',
                            $m->start_date - $buffer_before
                        ) / 15
                    ) * 15;
                $h1 = 23 * 60 + 45;

                for ($i = $h0; $i <= $h1; $i += 15) {
                    $meetingsByDay[$day0s][(string) $i][] = $m;
                }

                //				$t = strtotime($day0s);

                $h0 = '0';
                $h1 = date('H', $m->end_date + $buffer_after) * 60 + floor(
                        date(
                            'i',
                            $m->end_date + $buffer_after
                        ) / 15
                    ) * 15;

                for ($i = $h0; $i <= $h1; $i += 15) {
                    $meetingsByDay[$day1s][(string) $i][] = $m;
                }
            }
        }

        // aggregate
        foreach ($meetingsByDay as $date_day => $day_hours) {
            foreach ($day_hours as $day_hour => $hour_meetings) {
                $meetingsByDay[$date_day][(string) $day_hour] = count($hour_meetings);
            }
        }

        return $meetingsByDay;
    }

    public function save(): void
    {
        global $DIC;

        $rbacsystem = $DIC->rbac()->system();
        $objDefinition = $DIC['objDefinition'];
        $lng = $DIC->language();

        $ref_id = $this->retrieveIntFrom(self::$REQUEST_GET, 'ref_id');
        $new_type = $this->retrieveFromRequest('new_type', self::$TYPE_STRING);

        // create permission is already checked in createObject. This check here is done to prevent hacking attempts
        if (!$rbacsystem->checkAccess('create', $ref_id, $new_type)) {
            $this->error->raiseError($this->lng->txt('no_create_permission'), $this->error->MESSAGE);
        }

        $this->ctrl->setParameter($this, 'new_type', $new_type);

        $tpl_id = $this->retrieveStringFrom(self::$REQUEST_POST, 'tpl_id');
        if (!in_array($tpl_id, ilXAVCTemplates::XAVC_TEMPLATES)) {
            $this->error->raiseError($this->lng->txt('no_template_id_given'), $this->error->MESSAGE);
        }
        $this->initXAVCTemplates();
        $template_settings = $this->xavc_templates[$tpl_id];
        $form = $this->initCreateForm($template_settings->getType());

        if ($form->checkInput()) {
            if ($form->getInput(
                    'creation_type'
                ) == 'existing_vc' && $template_settings->getReuseExistingRoomsHide() == '0') {
                try {
                    $location = $objDefinition->getLocation($new_type);

                    $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());

                    $xavc_login = $ilAdobeConnectUser->getXAVCLogin();
                    $folder_id = $ilAdobeConnectUser->ensureUserFolderExistence($xavc_login);
                    $sco_ids = ilObjAdobeConnect::getScosByFolderId($folder_id);

                    $title = $sco_ids[$form->getInput('available_rooms')]['sco_name'];
                    $description = $sco_ids[$form->getInput('available_rooms')]['description'];

                    // create and insert object in objecttree
                    $class_name = "ilObj" . $objDefinition->getClassName($new_type);
                    /** @var $newObj ilObjAdobeConnect */
                    $newObj = new $class_name();
                    $newObj->setType($new_type);
                    $newObj->setTitle(ilUtil::stripSlashes($title));
                    $newObj->setDescription(ilUtil::stripSlashes($description));
                    $newObj->setUseMeetingTemplate($form->getInput('use_meeting_template'));
                    $newObj->create();
                    $newObj->createReference();
                    $newObj->putInTree($ref_id);
                    $newObj->setPermissions($ref_id);
                    $this->tpl->setOnScreenMessage('success', $lng->txt('msg_obj_modified'), true);
                    $this->afterSave($newObj);
                    return;
                } catch (Exception $e) {
                    $this->tpl->setOnScreenMessage('failure', $e->getMessage(), true);
                }
            } else { // create new object
                global $DIC;
                $ilUser = $DIC->user();

                $owner = $ilUser->getId();

                if (strlen($form->getInput('owner')) > 1) {
                    if (ilObjUser::_lookupId($form->getInput('owner')) > 0) {
                        $owner = ilObjUser::_lookupId($form->getInput('owner'));
                    } else {
                        $this->tpl->setOnScreenMessage('failure', $this->lng->txt('user_not_found'));
                        $owner = 0;
                    }
                }

                if ($template_settings->getDurationHide() == '1') {
                    $durationValid = true;
                } else {
                    if ($form->getInput('time_type_selection') == 'permanent_room' && ilAdobeConnectServer::getSetting(
                            'enable_perm_room',
                            '1'
                        )) {
                        $duration['hh'] = 2;
                        $duration['mm'] = 0;
                    } else {
                        $duration = $form->getInput("duration");
                    }

                    if ($duration['hh'] * 60 + $duration['mm'] < 10) {
                        $form->getItemByPostVar('duration')->setAlert($this->pluginObj->txt('min_duration_error'));
                        $durationValid = false;
                    } else {
                        $durationValid = true;
                    }
                }

                if ($template_settings->getStartDateHide() == '1') {
                    $time_mismatch = false;
                } else {
                    if ($durationValid) {
                        $serverConfig = ilAdobeConnectServer::_getInstance();
                        $minTime = new ilDateTime(time() + $serverConfig->getScheduleLeadTime() * 60 * 60, IL_CAL_UNIX);

                        if ($form->getInput('time_type_selection') == 'permanent_room') {
                            $form->getItemByPostVar("start_date")->checkInput();
                        }

                        $newStartDate = $form->getItemByPostVar('start_date')->getDate();

                        $time_mismatch = false;
                        if (ilDateTime::_before(
                                $newStartDate,
                                $minTime
                            ) && $form->getInput('time_type_selection') != 'permanent_room') {
                            $this->tpl->setOnScreenMessage(
                                'failure',
                                sprintf(
                                    $this->pluginObj->txt('xavc_lead_time_mismatch_create'),
                                    ilDatePresentation::formatDate($minTime)
                                ),
                                true
                            );
                            $time_mismatch = true;
                        }
                    }
                }
                if (!$time_mismatch && $owner > 0) {
                    try {
                        if ($durationValid) {
                            $location = $objDefinition->getLocation($new_type);

                            // create and insert object in objecttree
                            // @todo V9 Fix this!!
                            $class_name = "ilObj" . $objDefinition->getClassName($new_type);
                            include_once($location . "/class." . $class_name . ".php");
                            /** @var $newObj ilObjAdobeConnect */
                            $newObj = new $class_name();
                            $newObj->setType($new_type);
                            $obj_title = $this->retrieveStringFrom(self::$REQUEST_POST, 'title');
                            $obj_desc = $this->retrieveStringFrom(self::$REQUEST_POST, 'desc');
                            $newObj->setTitle(ilUtil::stripSlashes($obj_title));
                            $newObj->setDescription(ilUtil::stripSlashes($obj_desc));
                            $newObj->setOwner($owner);
                            $newObj->create();
                            $newObj->createReference();
                            $newObj->putInTree($ref_id);
                            $newObj->setPermissions($ref_id);
                            $this->tpl->setOnScreenMessage('success', $lng->txt('msg_obj_modified'), true);
                            $this->afterSave($newObj);
                            return;
                        }
                    } catch (Exception $e) {
                        $this->tpl->setOnScreenMessage('failure', $e->getMessage(), true);
                    }
                }
            }

            $form->setValuesByPost();
            if (ilAdobeConnectServer::getSetting('show_free_slots')) {
                $this->showCreationForm($form);
            } else {
                $this->tpl->setContent($form->getHTML());
            }
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }
    }

    private function showCreationForm(ilPropertyFormGUI $form): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $num_max_ac_obj = ilAdobeConnectServer::getSetting('ac_interface_objects');
        if ((int) $num_max_ac_obj <= 0) {
            $tpl->setContent($form->getHTML());
            return;
        }

        $fromtime = strtotime(date('Y-m-d H:00', time()));
        $totime = strtotime(date('Y-m-d', time() + 60 * 60 * 24 * 15)) - 1;

        $meetings = ilObjAdobeConnect::getMeetingsInRange($fromtime, $totime);

        $meetingsByDay = $this->getFreeMeetingSlotTable($meetings);
        $ranges = [];

        $t0 = $fromtime;
        $t1 = $fromtime;
        /*
                         * 2 * 30 minutes for starting and ending buffer
                         * 10 minutes as minimum meeting length
                         */
        $bufferingTime = 30 * 60 * 2 + 10 * 60;

        $prev_dayinmonth = date('d', $t1);

        for (; $t1 < $totime; $t1 += 60 * 15) {
            $day = date('Y-m-d', $t1);
            $hour = date('H', $t1) * 60 + floor(date('i', $t1) / 15) * 15;
            $dayinmonth = date('d', $t1);

            if (($meetingsByDay[$day] && $meetingsByDay[$day][$hour] && $meetingsByDay[$day][$hour] >= $num_max_ac_obj) || ($dayinmonth != $prev_dayinmonth)) {
                if ($t0 != $t1 && ($t1 - $t0) > $bufferingTime) {
                    $ranges[] = [$t0, $t1 - 1, $t1 - $t0];
                }

                if ($dayinmonth != $prev_dayinmonth) {
                    $prev_dayinmonth = $dayinmonth;
                    $t0 = $t1;
                } else {
                    $t0 = $t1 + 60 * 15;
                }
            }
        }

        if ($t0 != $t1) {
            $ranges[] = [$t0, $t1 - 1, $t1 - $t0];
        }

        $data = [];

        foreach ($ranges as $r) {
            $size_hours = floor($r[2] / 60 / 60);
            $size_minutes = ($r[2] - $size_hours * 60 * 60) / 60;

            $data[] = [
                'sched_from' => ilDatePresentation::formatDate(new ilDateTime($r[0], IL_CAL_UNIX)),
                'sched_to' => ilDatePresentation::formatDate(new ilDateTime($r[1], IL_CAL_UNIX)),
                'sched_size' => str_pad($size_hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad(
                        $size_minutes,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ),
            ];
        }

        $tgui = new ilTable2GUI($this);

        $tgui->addColumn($this->txt('sched_from'));
        $tgui->addColumn($this->txt('sched_to'));
        $tgui->addColumn($this->txt('sched_size'));

        $tgui->setExternalSegmentation(true);

        $tgui->enabled['footer'] = false;
        $tgui->setRowTemplate('tpl.schedule_row.html', $this->pluginObj->getDirectory());
        $tgui->setData($data);
        $tgui->setTitle(sprintf($this->txt('schedule_free_slots'), date('z', $totime - $fromtime)));

        $tpl->setContent($form->getHTML() . '<br />' . $tgui->getHTML());
    }

    public function editParticipants(): void
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();
        $ilUser = $DIC->user();
        $ilToolbar = $DIC->toolbar();

        $this->tabs->activateTab('participants');
        $this->setSubTabs('participants');
        $this->tabs->activateSubTab("editParticipants");

        $my_tpl = new ilTemplate(
            $this->pluginObj->getDirectory() . "/templates/default/tpl.meeting_participant_table.html",
            true,
            true
        );

        $has_access = ilXAVCPermissions::hasAccess(
            $ilUser->getId(),
            $this->object->getRefId(),
            AdobeConnectPermissions::PERM_ADD_PARTICIPANTS
        );
        $is_owner = $this->object->getOwner() == $ilUser->getId();
        if ((count($this->object->getParticipantsObject()->getParticipants()) == 0 && $has_access) || $is_owner) {
            // add members
            $types = [
                'add_member' => $this->lng->txt('member'),
                'add_admin' => $this->lng->txt('administrator')
            ];

            ilRepositorySearchGUI::fillAutoCompleteToolbar(
                $this,
                $ilToolbar,
                [
                    'auto_complete_name' => $lng->txt('user'),
                    'user_type' => $types,
                    'submit_name' => $lng->txt('add')
                ]
            );
            // add separator
            $ilToolbar->addSeparator();

            // search button
            $ilToolbar->addButton(
                $this->lng->txt("search_user"),
                $ilCtrl->getLinkTargetByClass('ilRepositorySearchGUI', 'start')
            );
        }

        $table = new ilXAVCParticipantsTableGUI($this, "editParticipants");

        $table->setProvider(new ilXAVCParticipantsDataProvider($DIC->database(), $this));
        $table->populate();

        $my_tpl->setVariable('FORM', $table->getHTML());

        $this->tpl->setContent($my_tpl->get());
    }

    public function performCrsGrpTrigger(): void
    {
        ignore_user_abort(true);
        @set_time_limit(0);

        $response = new stdClass();
        $response->succcess = false;

        if ((int) ilAdobeConnectServer::getSetting('allow_crs_grp_trigger') == 0) {
            // @todo V9 async call doesn't work. must be fixed soon.
            return;

            echo json_encode($response);
            exit();
        }

        if (count($this->object->getParticipantsObject()->getParticipants()) > 0) {
            $sco_id = ilObjAdobeConnect::_lookupScoId(ilObject::_lookupObjectId($this->object->getRefId()));
            $current_member_ids = ilXAVCMembers::getMemberIds($this->object->getRefId());
            $crs_grp_member_ids = $this->object->getParticipantsObject()->getParticipants();

            if (count($current_member_ids) == 0 && count($crs_grp_member_ids) > 0) {
                $this->object->addCrsGrpMembers($this->object->getRefId(), $sco_id);
            } else {
                $new_member_ids = array_diff($crs_grp_member_ids, $current_member_ids);
                $delete_member_ids = array_diff($current_member_ids, $crs_grp_member_ids);

                if (is_array($new_member_ids) && count($new_member_ids) > 0) {
                    $this->object->addCrsGrpMembers($this->object->getRefId(), $sco_id, $new_member_ids);
                }

                if (is_array($delete_member_ids) && count($delete_member_ids) > 0) {
                    $this->object->deleteCrsGrpMembers($sco_id, $delete_member_ids);
                }
            }
        }
        // @todo V9 async call doesn't work. must be fixed soon.
        return;

        $response->succcess = true;
        echo json_encode($response);
        exit();
    }

    public function getPerformTriggerHtml(): void
    {
        // @todo V9 async call doesn't work. must be fixed soon.
        $this->performCrsGrpTrigger();
        return;

        iljQueryUtil::initjQuery();

        $jsTpl = new ilTemplate($this->pluginObj->getDirectory() . '/templates/js/performTrigger.js', true, true);
        $jsTpl->setVariable(
            'TRIGGER_TARGET',
            $this->ctrl->getLinkTarget($this, 'performCrsGrpTrigger', '', true, false)
        );

        $this->tpl->addOnLoadCode($jsTpl->get());
    }

    public function infoScreenObject(): void
    {
        $this->ctrl->setCmd('showSummary');
        $this->ctrl->setCmdClass('ilinfoscreengui');
        $this->infoScreen();
    }

    public function infoScreen(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $settings = ilAdobeConnectServer::_getInstance();
        $this->tabs->setTabActive('info_short');
        $info = new ilInfoScreenGUI($this);
        $info->removeFormAction();
        $info->addSection($this->pluginObj->txt('general'));
        if ($this->object->getPermanentRoom() == 1 && ilAdobeConnectServer::getSetting('enable_perm_room', '1')) {
            $duration_text = $this->pluginObj->txt('permanent_room');
        } else {
            $duration_text = ilDatePresentation::formatPeriod(
                new ilDateTime($this->object->getStartDate()->getUnixTime(), IL_CAL_UNIX),
                new ilDateTime($this->object->getEndDate()->getUnixTime(), IL_CAL_UNIX)
            );
        }
        $presentation_url = $settings->getPresentationUrl();

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObj->txt('access_meeting_title'));

        $this->object->doRead();

        if ($this->object->getStartDate() != null) {
            $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
            $ilAdobeConnectUser->ensureAccountExistence();

            $xavc_login = $ilAdobeConnectUser->getXAVCLogin();
            $quota = new ilAdobeConnectQuota();
        }
        // show link
        if (($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
            && $this->object->isParticipant($xavc_login)) {
            if (!$quota->mayStartScheduledMeeting($this->object->getScoId())) {
                $href = $this->txt("meeting_not_available_no_slots");
            } else {
                $href = '<a href="' . $presentation_url . $this->object->getURL(
                    ) . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
                $button_txt = $this->pluginObj->txt('enter_vc');
                $button_target = ILIAS_HTTP_PATH . "/" . $this->ctrl->getLinkTarget(
                        $this,
                        'performSso',
                        '',
                        false,
                        false
                    );
                $button_tpl = new ilTemplate(
                    $this->pluginObj->getDirectory() . "/templates/default/tpl.bigbutton.html",
                    true,
                    true
                );
                $button_tpl->setVariable('BUTTON_TARGET', $button_target);
                $button_tpl->setVariable('BUTTON_TEXT', $button_txt);

                $big_button = $button_tpl->get();
                $info->addProperty('', $big_button . "<br />");
            }
        } else {
            $href = $this->txt("meeting_not_available");
        }

        $info->addProperty($this->pluginObj->txt('duration'), $duration_text);
        $info->addProperty($this->pluginObj->txt('meeting_url'), $href);

        $tpl->setContent($info->getHTML());
    }

    public function showContent(): void
    {
        global $DIC;

        $ilUser = $DIC->user();
        $tpl = $DIC->ui()->mainTemplate();
        $ilAccess = $DIC->access();

        $has_write_permission = $ilAccess->checkAccess("write", "", $this->object->getRefId());

        $settings = ilAdobeConnectServer::_getInstance();

        $this->tabs->setTabActive('contents');

        $info = new ilInfoScreenGUI($this);
        $info->removeFormAction();

        $is_member = ilObjAdobeConnectAccess::_hasMemberRole($ilUser->getId(), $this->object->getRefId());
        $is_admin = ilObjAdobeConnectAccess::_hasAdminRole($ilUser->getId(), $this->object->getRefId());

        if (($this->access->checkAccess(
                "write",
                "",
                $this->object->getRefId()
            ) || $is_member || $is_admin)) {
            $presentation_url = $settings->getPresentationUrl();

            $form = new ilPropertyFormGUI();
            $form->setTitle($this->pluginObj->txt('access_meeting_title'));

            $this->object->doRead();

            if ($this->object->getStartDate() != null) {
                $ilAdobeConnectUser = new ilAdobeConnectUserUtil($this->user->getId());
                $ilAdobeConnectUser->ensureAccountExistence();

                $xavc_login = $ilAdobeConnectUser->getXAVCLogin();
                $quota = new ilAdobeConnectQuota();

                // show button
                if (($this->object->getPermanentRoom() == 1 || $this->doProvideAccessLink())
                    && $this->object->isParticipant($xavc_login)) {
                    if (!$quota->mayStartScheduledMeeting($this->object->getScoId())) {
                        $href = $this->txt("meeting_not_available_no_slots");
                        $button_disabled = true;
                    } else {
                        $href = '<a href="' . $this->ctrl->getLinkTarget(
                                $this,
                                'performSso'
                            ) . '" target="_blank" >' . $presentation_url . $this->object->getURL() . '</a>';
                        $button_disabled = false;
                    }
                } else {
                    $href = $this->txt("meeting_not_available");
                    $button_disabled = true;
                }

                if ($button_disabled == true) {
                    $button_txt = $href;
                } else {
                    $button_txt = $this->pluginObj->txt('enter_vc');
                }

                $button_target = ILIAS_HTTP_PATH . "/" . $this->ctrl->getLinkTarget(
                        $this,
                        'performSso',
                        '',
                        false,
                        false
                    );
                $button_tpl = new ilTemplate(
                    $this->pluginObj->getDirectory() . "/templates/default/tpl.bigbutton.html",
                    true,
                    true
                );
                $button_tpl->setVariable('BUTTON_TARGET', $button_target);
                $button_tpl->setVariable('BUTTON_TEXT', $button_txt);

                $big_button = $button_tpl->get();

                $info->addSection('');
                if ($button_disabled == true) {
                    $info->addProperty('', $href);
                } else {
                    $info->addProperty('', $big_button . "<br />");
                }

                // show instructions
                if ($this->object->getInstructions() != '') {
                    $info->addSection($this->lng->txt('exc_instruction'));
                    $info->addProperty('', nl2br($this->object->getInstructions()));
                }

                // show contact info
                if ($this->object->getContactInfo() != '') {
                    $info->addSection($this->pluginObj->txt('contact_information'));
                    $info->addProperty('', nl2br($this->object->getContactInfo()));
                }

                //show contents
                if (
                    ilXAVCPermissions::hasAccess(
                        $ilUser->getId(),
                        $this->object->getRefId(),
                        AdobeConnectPermissions::PERM_READ_CONTENTS
                    )
                    && $this->object->getReadContents('content')
                ) {
                    $info->addSection($this->pluginObj->txt('file_uploads'));
                    $info->setFormAction($this->ctrl->getFormAction($this, 'showContent'));
                    $has_access = false;
                    if (
                        ilXAVCPermissions::hasAccess(
                            $ilUser->getId(),
                            $this->ref_id,
                            AdobeConnectPermissions::PERM_UPLOAD_CONTENT
                        )
                        || $has_write_permission
                    ) {
                        $submitBtn = ilSubmitButton::getInstance();
                        $submitBtn->setCaption($this->txt('add_new_content'), false);
                        $submitBtn->setCommand('showAddContent');

                        $has_access = true;

                        $info->addProperty('', $submitBtn->render());
                    }
                    $info->addProperty('', $this->viewContents($has_access));
                }

                // show records
                if (
                    ilXAVCPermissions::hasAccess(
                        $ilUser->getId(),
                        $this->object->getRefId(),
                        AdobeConnectPermissions::PERM_READ_RECORDS
                    ) &&
                    $this->object->getReadRecords()
                ) {
                    $has_access = false;
                    if (
                        ilXAVCPermissions::hasAccess(
                            $ilUser->getId(),
                            $this->ref_id,
                            AdobeConnectPermissions::PERM_EDIT_RECORDS
                        )
                        || $has_write_permission
                    ) {
                        $has_access = true;
                    }

                    $info->addSection($this->pluginObj->txt('records'));
                    $info->addProperty('', $this->viewRecords($has_access, 'record'));
                }
            } else {
                $this->tpl->setOnScreenMessage('failure', $this->txt('error_connect_ac_server'));
            }
        }

        $info->hideFurtherSections();
        $tpl->setContent($info->getHTML() . $this->getPerformTriggerHtml());

        $tpl->setPermanentLink('xavc', $this->object->getRefId());
    }
}
