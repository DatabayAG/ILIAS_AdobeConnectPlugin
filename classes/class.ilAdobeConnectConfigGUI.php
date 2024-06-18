<?php

use ILIAS\Container;

/**
 * Class ilAdobeConnectConfigGUI
 * @author            Nadia Matuschek <nmatuschek@databay.de>
 * @ilCtrl_isCalledBy ilAdobeConnectConfigGUI: ilObjComponentSettingsGUI
 */
class ilAdobeConnectConfigGUI extends ilPluginConfigGUI implements AdobeConnectPermissions
{
    public static array $template_cache = [];
    private ilTabsGUI $tabs;
    private ilGlobalTemplateInterface $tpl;

    public ?ilPropertyFormGUI $form = null;

    public function performCommand(string $cmd): void
    {
        global $DIC;

        $this->tabs = $DIC->tabs();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->getTabs();
        switch ($cmd) {
            default:
                $this->$cmd();
                break;
        }
    }

    public function configure(): void
    {
        $this->editAdobeSettings();
    }

    // ADOBE-SETTINGS
    public function cancelAdobeSettings(): void
    {
        $this->tpl->setOnScreenMessage('info', $this->getPluginObject()->txt('canceled_update_settings'));
        $this->editAdobeSettings();
    }

    private function initAdobeSettingsForm(): void
    {
        global $DIC;
        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();

        $this->tabs->activateTab('editAdobeSettings');
        $this->form = new ilPropertyFormGUI();

        $this->form->setFormAction($ilCtrl->getFormAction($this, 'saveAdobeSettings'));
        $this->form->setTitle($this->getPluginObject()->txt('adobe_settings'));

        $this->form->addCommandButton('saveAdobeSettings', $lng->txt('save'));
        $this->form->addCommandButton('cancelAdobeSettings', $lng->txt('cancel'));

        $form_server = new ilTextInputGUI($lng->txt('server'), 'server');
        $form_server->setRequired(true);
        $form_server->setInfo($this->getPluginObject()->txt('xavc_host_info'));
        $this->form->addItem($form_server);

        $form_port = new ilNumberInputGUI($lng->txt('port'), 'port');
        $form_port->setSize(5);
        $form_port->setMaxLength(5);
        $form_port->setInfo($this->getPluginObject()->txt('xavc_port_info'));
        $this->form->addItem($form_port);

        $form_login = new ilTextInputGUI($lng->txt('login'), 'login');
        $form_login->setRequired(true);
        $this->form->addItem($form_login);

        $form_passwd = new ilPasswordInputGUI($lng->txt('password'), 'password');
        $form_passwd->setSkipSyntaxCheck(true);
        $form_passwd->setRequired(true);
        $form_passwd->setRetype(false);
        $this->form->addItem($form_passwd);

        // you can choose the mode for the creation of user-accounts at AdobeServer: the AC-Loginname could be the users-email-address or the ilias-loginname
        $radio_group = new ilRadioGroupInputGUI(
            $this->getPluginObject()->txt('user_assignment_mode'),
            'user_assignment_mode'
        );
        $radio_option_1 = new ilRadioOption(
            $this->getPluginObject()->txt('assign_users_with_email'),
            'assign_user_email'
        );
        $radio_group->addOption($radio_option_1);
        $radio_option_2 = new ilRadioOption(
            $this->getPluginObject()->txt('assign_users_with_ilias_login'),
            'assign_ilias_login'
        );
        $radio_group->addOption($radio_option_2);

        $radio_group->setInfo($this->getPluginObject()->txt('assignment_info'));

        $radio_option_4 = new ilRadioOption(
            $this->getPluginObject()->txt('assign_users_with_email_dfn'),
            'assign_dfn_email'
        );
        $radio_group->addOption($radio_option_4);

        if (ilAdobeConnectServer::getSetting('user_assignment_mode') != null) {
            $radio_group->setDisabled(true);
        }
        $this->form->addItem($radio_group);

        $auth_radio_grp = new ilRadioGroupInputGUI($this->getPluginObject()->txt('auth_mode'), 'auth_mode');

        $auth_radio_opt_1 = new ilRadioOption(
            $this->getPluginObject()->txt('auth_mode_password'),
            'auth_mode_password'
        );
        $auth_radio_grp->addOption($auth_radio_opt_1);

        $auth_radio_opt_2 = new ilRadioOption($this->getPluginObject()->txt('auth_mode_header'), 'auth_mode_header');

        $form_x_user_id = new ilTextInputGUI($this->getPluginObject()->txt('x_user_id_header_var'), 'x_user_id');
        $form_x_user_id->setInfo($this->getPluginObject()->txt('xavc_x_user_id_info'));

        $auth_radio_opt_2->addSubItem($form_x_user_id);
        $auth_radio_grp->addOption($auth_radio_opt_2);

        $form_auth_mode_dfn = new ilRadioOption($this->getPluginObject()->txt('auth_mode_dfn'), 'auth_mode_dfn');
        $auth_radio_grp->addOption($form_auth_mode_dfn);

        $auth_radio_grp->setInfo($this->getPluginObject()->txt('authentification_mode_info'));

        $this->form->addItem($auth_radio_grp);

        $form_lead_time = new ilNumberInputGUI(
            $this->getPluginObject()->txt('schedule_lead_time'),
            'schedule_lead_time'
        );
        $form_lead_time->setDecimals(0);
        $form_lead_time->setMinValue(0);
        $form_lead_time->setRequired(true);
        $form_lead_time->setSize(5);
        $form_lead_time->setInfo($this->getPluginObject()->txt('schedule_lead_time_info'));
        $this->form->addItem($form_lead_time);

        $head_line = new ilFormSectionHeaderGUI();
        $head_line->setTitle($this->getPluginObject()->txt('presentation_server_settings'));
        $this->form->addItem($head_line);

        $form_fe_server = new ilTextInputGUI(
            $this->getPluginObject()->txt('presentation_server'),
            'presentation_server'
        );
        $form_fe_server->setRequired(true);
        $form_fe_server->setInfo($this->getPluginObject()->txt('xavc_presentation_host_info'));
        $this->form->addItem($form_fe_server);

        $form_fe_port = new ilNumberInputGUI($this->getPluginObject()->txt('presentation_port'), 'presentation_port');
        $form_fe_port->setSize(5);
        $form_fe_port->setMaxLength(5);
        $form_fe_port->setInfo($this->getPluginObject()->txt('xavc_presentation_port_info'));
        $this->form->addItem($form_fe_port);
    }

    private function getAdobeSettingsValues(): void
    {
        $values = [];

        $values['server'] = ilAdobeConnectServer::getSetting('server') ?: '';
        $values['port'] = ilAdobeConnectServer::getSetting('port') ?: '';
        $values['login'] = ilAdobeConnectServer::getSetting('login') ?: '';
        $values['password'] = ilAdobeConnectServer::getSetting('password') ?: '';
        $values['cave'] = ilAdobeConnectServer::getSetting('cave') ?: '';
        $values['schedule_lead_time'] = ilAdobeConnectServer::getSetting('schedule_lead_time') ?: 0;

        ilAdobeConnectServer::getSetting('user_assignment_mode')
            ? $values['user_assignment_mode'] = ilAdobeConnectServer::getSetting('user_assignment_mode')
            : $values['user_assignment_mode'] = 'assign_user_email';

        $values['presentation_server'] = ilAdobeConnectServer::getSetting('presentation_server') ?: '';
        $values['presentation_port'] = ilAdobeConnectServer::getSetting('presentation_port') ?: '';
        $values['auth_mode'] = ilAdobeConnectServer::getSetting('auth_mode') ?: 'auth_mode_password';

        $values['x_user_id'] = ilAdobeConnectServer::getSetting('x_user_id') ?: 'x_user_id';

        $this->form->setValuesByArray($values);
    }

    public function editAdobeSettings(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $this->tabs->activateTab('editAdobeSettings');

        $this->initAdobeSettingsForm();
        $this->getAdobeSettingsValues();

        $tpl->setContent($this->form->getHTML());
    }

    public function saveAdobeSettings()
    {
        global $DIC;

        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();
        $tpl = $DIC->ui()->mainTemplate();

        $this->initAdobeSettingsForm();
        if ($this->form->checkInput()) {
            $url = parse_url(trim($this->form->getInput('server')));
            $url_2 = parse_url(trim($this->form->getInput('presentation_server')));

            if ((self::isIPv4($url['host']) || self::isDN($url['host']))
                && (self::isIPv4($url_2['host']) || self::isDN($url_2['host']))) {
                $params = array(
                    'server' => null,
                    'port' => null,
                    'login' => null,
                    'password' => null,
                    'cave' => null,
                    'user_assignment_mode' => null,
                    'schedule_lead_time' => null,
                    'presentation_server' => null,
                    'presentation_port' => null
                );
                $params['auth_mode'] = $this->form->getInput('auth_mode');

                if ($this->form->getInput('auth_mode') == 'auth_mode_header') {
                    $params['x_user_id'] = $this->form->getInput('x_user_id');
                }

                // Get current values from database
                foreach ($params as $key => $val) {
                    $params[$key] = ilAdobeConnectServer::getSetting($key);
                }

                // Set values from form into database
                foreach ($params as $key => $v) {
                    $value = trim((string) $this->form->getInput($key));
                    if (in_array($key, array('server', 'presentation_server')) && '/' == substr($value, -1)) {
                        $value = substr($value, 0, -1);
                    }
                    ilAdobeConnectServer::setSetting($key, trim($value));
                }

                ilAdobeConnectServer::_getInstance()->commitSettings();

                try {
                    //check connection;
                    if (ilAdobeConnectServer::getSetting(
                            'user_assignment_mode'
                        ) == ilAdobeConnectServer::ASSIGN_USER_DFN_EMAIL) {
                        ilAdobeConnectServer::setSetting('use_user_folders', '0');
                    }

                    $this->readApiVersion();

                    $this->tpl->setOnScreenMessage('success', $lng->txt('settings_saved'), true);
                    $ilCtrl->redirect($this, 'editAdobeSettings');
                } catch (Exception $e) {
                    // rollback
                    foreach ($params as $key => $val) {
                        ilAdobeConnectServer::setSetting($key, trim($val));
                    }

                    ilAdobeConnectServer::_getInstance()->commitSettings();

                    $untranslatedError = '-' . $this->getPluginObject()->getPrefix() . '_' . $e->getMessage() . '-';
                    if ($this->getPluginObject()->txt($e->getMessage()) != $untranslatedError) {
                        $this->form->getItemByPostVar('server')
                                   ->setAlert($this->getPluginObject()->txt($e->getMessage()));
                    } else {
                        $this->form->getItemByPostVar('server')
                                   ->setAlert($e->getMessage());
                    }
                }
            } else {
                if (!self::isIPv4($url['host']) && !self::isDN($url['host'])) {
                    $this->form->getItemByPostVar('server')
                               ->setAlert($this->getPluginObject()->txt('err_invalid_server'));
                }

                if (!self::isIPv4($url_2['host']) && !self::isDN($url_2['host'])) {
                    $this->form->getItemByPostVar('presentation_server')
                               ->setAlert($this->getPluginObject()->txt('err_invalid_server'));
                }
            }
        }

        $this->tpl->setOnScreenMessage('failure', $this->getPluginObject()->txt('check_input'));
        $this->form->setValuesByPost();
        return $tpl->setContent($this->form->getHTML());
    }

    // ROOM-ALLOCATION
    public function cancelRoomAllocation(): void
    {
        $this->tpl->setOnScreenMessage('info', $this->getPluginObject()->txt('canceled_update_settings'));
        $this->editRoomAllocation();
    }

    private function initRoomAllocationForm(): void
    {
        global $DIC;
        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();

        $this->form = new ilPropertyFormGUI();
        $this->form->setFormAction($ilCtrl->getFormAction($this, 'saveRoomAllocation'));
        $this->form->setTitle($this->getPluginObject()->txt('room_allocation'));

        $this->form->addCommandButton('saveRoomAllocation', $lng->txt('save'));
        $this->form->addCommandButton('cancelRoomAllocation', $lng->txt('cancel'));

        $form_ac_obj = new ilNumberInputGUI(
            $this->getPluginObject()->txt('ac_interface_objects'),
            'ac_interface_objects'
        );
        $form_ac_obj->setInfo($this->getPluginObject()->txt('enter_number_of_scos'));
        $form_ac_obj->setSize(5);
        $this->form->addItem($form_ac_obj);

        $form_ac_buf = new ilNumberInputGUI($this->getPluginObject()->txt('ac_buffer'), 'ac_interface_objects_buffer');
        $form_ac_buf->setInfo($this->getPluginObject()->txt('enter_number_of_sco_buffer'));
        $form_ac_buf->setSize(5);
        $this->form->addItem($form_ac_buf);
    }

    public function getRoomAllocationValues(): void
    {
        $values = array();

        $values['num_max_vc'] = ilAdobeConnectServer::getSetting('num_max_vc') ?: 1;
        $values['ac_interface_objects'] = ilAdobeConnectServer::getSetting('ac_interface_objects') ?: 0;
        $values['ac_interface_objects_buffer'] = ilAdobeConnectServer::getSetting('ac_interface_objects_buffer') ?: 0;
        $this->form->setValuesByArray($values);
    }

    public function editRoomAllocation(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $this->tabs->activateTab('editRoomAllocation');
        $this->initRoomAllocationForm();
        $this->getRoomAllocationValues();

        $tpl->setContent($this->form->getHTML());
    }

    public function saveRoomAllocation()
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();
        $tpl = $DIC->ui()->mainTemplate();

        $this->initRoomAllocationForm();
        if ($this->form->checkInput()) {
            $max_num_vc = (int) $this->form->getInput('num_max_vc');
            $num_ac_obj = (int) $this->form->getInput('ac_interface_objects');
            $num_ac_obj_buffer = (int) $this->form->getInput('ac_interface_objects_buffer');

            $sum = $num_ac_obj + $num_ac_obj_buffer;
            /*if((int)$num_ac_obj > 0 && $sum > $max_num_vc)
            {
                $this->form->getItemByPostVar('num_max_vc')->setAlert($this->getPluginObject()->txt('err_num_of_required_rooms_gt_max_vc'));
                ilUtil::sendFailure($this->getPluginObject()->txt('check_input'));
                $this->form->setValuesByPost();
                return $tpl->setContent($this->form->getHTML());
            }*/

            ilAdobeConnectServer::setSetting('num_max_vc', (string) $this->form->getInput('num_max_vc'));
            ilAdobeConnectServer::setSetting('ac_interface_objects', (string) $num_ac_obj);
            ilAdobeConnectServer::setSetting('ac_interface_objects_buffer', (string) $num_ac_obj_buffer);

            $this->tpl->setOnScreenMessage(
                'success',
                $this->getPluginObject()->txt('extt_adobe_room_allocation_saved'),
                true
            );
            $ilCtrl->redirect($this, 'editRoomAllocation');
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->getPluginObject()->txt('check_input'));
            $this->form->setValuesByPost();
            return $tpl->setContent($this->form->getHTML());
        }
    }

    public function getTabs(): void
    {
        global $DIC;
        $ilCtrl = $DIC->ctrl();

        $this->tabs->addTab(
            'editAdobeSettings',
            $this->getPluginObject()->txt('editAdobeSettings'),
            $ilCtrl->getLinkTarget($this, 'editAdobeSettings')
        );
        $this->tabs->addTab(
            'editRoomAllocation',
            $this->getPluginObject()->txt('editRoomAllocation'),
            $ilCtrl->getLinkTarget($this, 'editRoomAllocation')
        );
        $this->tabs->addTab(
            'editIliasSettings',
            $this->getPluginObject()->txt('editIliasSettings'),
            $ilCtrl->getLinkTarget($this, 'editIliasSettings')
        );
    }

    public function editIliasSettings(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        $this->tabs->activateTab('editIliasSettings');
        $this->initIliasSettingsForm();
        $this->getIliasSettingsValues();

        $tpl->setContent($this->form->getHTML());
    }

    public function initIliasSettingsForm(): void
    {
        global $DIC;

        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();

        $this->form = new ilPropertyFormGUI();
        $this->form->setFormAction($ilCtrl->getFormAction($this, 'saveIliasSettings'));
        $this->form->setTitle($this->getPluginObject()->txt('general_settings'));

        $this->form->addCommandButton('saveIliasSettings', $lng->txt('save'));
        $this->form->addCommandButton('cancelIliasSettings', $lng->txt('cancel'));

        $cb_group = new ilCheckboxGroupInputGUI(
            $this->getPluginObject()->txt('object_creation_settings'),
            'obj_creation_settings'
        );

        foreach (ilXAVCTemplates::XAVC_TEMPLATES as $template_type) {
            $xavc_tpl = ilXAVCTemplates::_getInstanceByType($template_type);
            $cb_option = new ilCheckboxOption(
                $this->getPluginObject()->txt($xavc_tpl->getLangVar()),
                $xavc_tpl->getType());

            $cb_group->addOption($cb_option);
        }

        $cb_group->setInfo($this->getPluginObject()->txt('template_info'));
        $this->form->addItem($cb_group);

        $obj_title_suffix = new ilCheckboxInputGUI($this->getPluginObject()->txt('obj_title_suffix'), 'obj_title_suffix');
        $obj_title_suffix->setInfo($this->getPluginObject()->txt('obj_title_suffix_info'));
        $this->form->addItem($obj_title_suffix);

        try {
            $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
            $templateOptions = $xmlAPI->getTemplates($this->getPluginObject());

            $use_meeting_template = new ilCheckboxInputGUI(
                $this->getPluginObject()->txt('use_meeting_template'),
                'use_meeting_template'
            );
            $use_meeting_template->setInfo($this->getPluginObject()->txt('use_meeting_template_info'));
            $template_source = new ilSelectInputGUI('', 'template_sco_id');
            $template_source->setOptions($templateOptions);

            $use_meeting_template->addSubItem($template_source);
            $this->form->addItem($use_meeting_template);
        } catch (\Exception $e) {
        }

        $crs_grp_trigger = new ilCheckboxInputGUI(
            $this->getPluginObject()->txt('allow_crs_grp_trigger'),
            'allow_crs_grp_trigger'
        );
        $crs_grp_trigger->setInfo($this->getPluginObject()->txt('allow_crs_grp_trigger_info'));
        $this->form->addItem($crs_grp_trigger);

        $show_free_slots = new ilCheckboxInputGUI($this->getPluginObject()->txt('show_free_slots'), 'show_free_slots');
        $show_free_slots->setInfo($this->getPluginObject()->txt('show_free_slots_info'));
        $this->form->addItem($show_free_slots);

        $enable_perm_room = new ilCheckboxInputGUI($this->getPluginObject()->txt('enable_perm_room'), 'enable_perm_room');
        $enable_perm_room->setInfo($this->getPluginObject()->txt('enable_perm_room_info'));

        $default_perm_room = new ilCheckboxInputGUI($this->getPluginObject()->txt('default_perm_room'), 'default_perm_room');
        $default_perm_room->setInfo($this->getPluginObject()->txt('default_perm_room_info'));
        $enable_perm_room->addSubItem($default_perm_room);
        $this->form->addItem($enable_perm_room);

        $add_to_desktop = new ilCheckboxInputGUI($this->getPluginObject()->txt('add_to_desktop'), 'add_to_desktop');
        $add_to_desktop->setInfo($this->getPluginObject()->txt('add_to_desktop_info'));
        $this->form->addItem($add_to_desktop);

        $content_file_types = new ilTextInputGUI($this->getPluginObject()->txt('content_file_types'), 'content_file_types');
        $content_file_types->setRequired(true);
        $content_file_types->setInfo($this->getPluginObject()->txt('content_file_types_info'));
        $this->form->addItem($content_file_types);

        $user_folders = new ilCheckboxInputGUI($this->getPluginObject()->txt('use_user_folders'), 'use_user_folders');
        $user_folders->setInfo($this->getPluginObject()->txt('use_user_folders_info'));
        if (ilAdobeConnectServer::getSetting('user_assignment_mode') == ilAdobeConnectServer::ASSIGN_USER_DFN_EMAIL) {
            $user_folders->setDisabled(true);
        }
        $this->form->addItem($user_folders);

        $xavc_options = [
            'host' => $this->getPluginObject()->txt('presenter'),
            'mini-host' => $this->getPluginObject()->txt('moderator'),
            'view' => $this->getPluginObject()->txt('participant'),
            'denied' => $this->getPluginObject()->txt('denied')
        ];

        $mapping_crs = new ilNonEditableValueGUI($this->getPluginObject()->txt('default_crs_mapping'), 'default_crs_mapping');

        $crs_admin = new ilSelectInputGUI($lng->txt('il_crs_admin'), 'crs_admin');
        $crs_admin->setOptions($xavc_options);
        $mapping_crs->addSubItem($crs_admin);

        $crs_tutor = new ilSelectInputGUI($lng->txt('il_crs_tutor'), 'crs_tutor');
        $crs_tutor->setOptions($xavc_options);
        $mapping_crs->addSubItem($crs_tutor);

        $crs_member = new ilSelectInputGUI($lng->txt('il_crs_member'), 'crs_member');
        $crs_member->setOptions($xavc_options);
        $mapping_crs->addSubItem($crs_member);

        $this->form->addItem($mapping_crs);

        $mapping_grp = new ilNonEditableValueGUI($this->getPluginObject()->txt('default_grp_mapping'), 'default_grp_mapping');

        $grp_admin = new ilSelectInputGUI($lng->txt('il_grp_admin'), 'grp_admin');
        $grp_admin->setOptions($xavc_options);
        $mapping_grp->addSubItem($grp_admin);

        $grp_member = new ilSelectInputGUI($lng->txt('il_grp_member'), 'grp_member');
        $grp_member->setOptions($xavc_options);
        $mapping_grp->addSubItem($grp_member);
        $this->form->addItem($mapping_grp);

        $ac_permissions = ilXAVCPermissions::getPermissionsArray();
        //@todo V9: needs refactoring
        $tbl = "<table width='100%' >
		<tr>
		<td> </td> 
		<td>" . $this->getPluginObject()->txt('presenter') . "</td>
		<td>" . $this->getPluginObject()->txt('moderator') . "</td>
		<td>" . $this->getPluginObject()->txt('participant') . "</td>
		<td>" . $this->getPluginObject()->txt('denied') . "</td>
		
		</tr>";
        foreach ($ac_permissions as $ac_permission => $ac_roles) {
            $tbl .= "<tr> <td>" . $this->getPluginObject()->txt($ac_permission) . "</td>";

            foreach ($ac_roles as $ac_role => $ac_access) {
                $tbl .= "<td>";
                $tbl .= ilLegacyFormElementsUtil::formCheckbox(
                    (bool) $ac_access,
                    'permissions[' . $ac_permission . '][' . $ac_role . ']',
                    $ac_role,
                    false
                );
                $tbl .= "</td>";
            }

            $tbl .= "</tr>";
        }
        $tbl .= "</table>";
        $matrix = new ilCustomInputGUI($this->getPluginObject()->txt('ac_permissions'), '');
        $matrix->setHtml($tbl);

        $this->form->addItem($matrix);

        $api_version = new ilNonEditableValueGUI($this->getPluginObject()->txt('api_version'), 'api_version');
        $this->form->addItem($api_version);

        $html_client = new ilCheckboxInputGUI($this->getPluginObject()->txt('html_client'), 'html_client');
        $html_client->setInfo($this->getPluginObject()->txt('html_client_info'));
        if (version_compare(ilAdobeConnectServer::getSetting('api_version'), '10.0.0', '<')) {
            $html_client->setDisabled(true);
        }
        $this->form->addItem($html_client);
    }

    private function readApiVersion(): void
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();
        $api_version = $xmlAPI->getApiVersion();

        ilAdobeConnectServer::setSetting('api_version', $api_version);
        if (version_compare($api_version, '10.0.0', '<')) {
            ilAdobeConnectServer::setSetting('html_client', 0);
        }
    }

    public function getIliasSettingsValues(): void
    {
        $values = [];
        $values['use_meeting_template'] = ilAdobeConnectServer::getSetting('use_meeting_template') ?: 0;
        $values['template_sco_id'] = ilAdobeConnectServer::getSetting('template_sco_id') ?: 0;

        $values['obj_creation_settings'] = (array) unserialize(
            ilAdobeConnectServer::getSetting('obj_creation_settings'),
            ['allowed_classes' => false]
        ) ?: '0';
        $values['obj_title_suffix'] = ilAdobeConnectServer::getSetting('obj_title_suffix') ?: 0;
        $values['allow_crs_grp_trigger'] = ilAdobeConnectServer::getSetting('allow_crs_grp_trigger') ?: 0;
        $values['show_free_slots'] = ilAdobeConnectServer::getSetting('show_free_slots') ?: 0;
        $values['enable_perm_room'] = ilAdobeConnectServer::getSetting('enable_perm_room', '1') ?: 0;
        $values['default_perm_room'] = ilAdobeConnectServer::getSetting('default_perm_room') ?: 0;
        $values['add_to_desktop'] = ilAdobeConnectServer::getSetting('add_to_desktop') ?: 0;
        $values['content_file_types'] = strlen(
            ilAdobeConnectServer::getSetting('content_file_types')
        ) > 1 ? ilAdobeConnectServer::getSetting(
            'content_file_types'
        ) : 'ppt, pptx, flv, swf, pdf, gif, jpg, png, mp3, html';
        $values['use_user_folders'] = ilAdobeConnectServer::getSetting('use_user_folders') ?: 0;

        $values['crs_admin'] = ilAdobeConnectServer::getSetting('crs_admin') ?: 'mini-host';
        $values['crs_tutor'] = ilAdobeConnectServer::getSetting('crs_tutor') ?: 'mini-host';
        $values['crs_member'] = ilAdobeConnectServer::getSetting('crs_member') ?: 'view';

        $values['grp_admin'] = ilAdobeConnectServer::getSetting('grp_admin') ?: 'mini-host';
        $values['grp_member'] = ilAdobeConnectServer::getSetting('grp_member') ?: 'view';

        $values['api_version'] = ilAdobeConnectServer::getSetting('api_version', '0');
        $values['html_client'] = ilAdobeConnectServer::getSetting('html_client') ?: '0';

        $this->form->setValuesByArray($values);
    }

    public function saveIliasSettings()
    {
        global $DIC;

        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();
        $tpl = $DIC->ui()->mainTemplate();

        if (is_array($_POST['permissions']) || $_POST['permissions'] == null) {
            if ($_POST['permissions'] == null) {
                $permissions = [];
            } else {
                $permissions = $_POST['permissions'];
            }

            $objPerm = new ilXAVCPermissions();
            $objPerm->setPermissions($permissions);
        }

        $this->initIliasSettingsForm();
        if ($this->form->checkInput()) {
            if ($this->form->getItemByPostVar('use_meeting_template')) {
                ilAdobeConnectServer::setSetting(
                    'use_meeting_template',
                    $this->form->getInput('use_meeting_template')
                );
                ilAdobeConnectServer::setSetting('template_sco_id', $this->form->getInput('template_sco_id'));
            }
            ilAdobeConnectServer::setSetting(
                'obj_creation_settings',
                serialize($this->form->getInput('obj_creation_settings'))
            );

            ilAdobeConnectServer::setSetting(
                'allow_crs_grp_trigger',
                (string) (bool) $this->form->getInput('allow_crs_grp_trigger')
            );
            ilAdobeConnectServer::setSetting(
                'obj_title_suffix',
                (string) (int) $this->form->getInput('obj_title_suffix')
            );
            ilAdobeConnectServer::setSetting(
                'show_free_slots',
                (string) (int) $this->form->getInput('show_free_slots')
            );

            $enable_perm_room = (int) $this->form->getInput('enable_perm_room');
            ilAdobeConnectServer::setSetting('enable_perm_room', (string) $enable_perm_room);
            ilAdobeConnectServer::setSetting(
                'default_perm_room',
                $enable_perm_room == 0 ? '0' : (string) (int) $this->form->getInput('default_perm_room')
            );
            ilAdobeConnectServer::setSetting('add_to_desktop', (string) (int) $this->form->getInput('add_to_desktop'));
            ilAdobeConnectServer::setSetting(
                'content_file_types',
                (string) $this->form->getInput('content_file_types')
            );
            ilAdobeConnectServer::setSetting(
                'use_user_folders',
                (string) (int) $this->form->getInput('use_user_folders')
            );

            ilAdobeConnectServer::setSetting('crs_admin', (string) $this->form->getInput('crs_admin'));
            ilAdobeConnectServer::setSetting('crs_tutor', (string) $this->form->getInput('crs_tutor'));
            ilAdobeConnectServer::setSetting('crs_member', (string) $this->form->getInput('crs_member'));

            ilAdobeConnectServer::setSetting('grp_admin', (string) $this->form->getInput('grp_admin'));
            ilAdobeConnectServer::setSetting('grp_member', (string) $this->form->getInput('grp_member'));
            ilAdobeConnectServer::setSetting('html_client', (string) (int) $this->form->getInput('html_client'));

            $this->tpl->setOnScreenMessage('success', $lng->txt('settings_saved'), true);
            $ilCtrl->redirect($this, 'editIliasSettings');
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->getPluginObject()->txt('check_input'));
            $this->form->setValuesByPost();
            return $tpl->setContent($this->form->getHTML());
        }
    }

    public function cancelIliasSettings(): void
    {
        $this->tpl->setOnScreenMessage('info', $this->getPluginObject()->txt('canceled_update_settings'));
        $this->editIliasSettings();
    }

    public static function isDN($a_str)
    {
        return (preg_match("/^[a-z]+([a-z0-9-]*[a-z0-9]+)?(\.([a-z]+([a-z0-9-]*[a-z0-9]+)?)+)*$/", $a_str));
    }

    public static function isIPv4($a_str)
    {
        return (preg_match(
            "/^(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\." .
            "(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$/", $a_str
        ));
    }
}
