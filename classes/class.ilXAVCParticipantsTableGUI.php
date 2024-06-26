<?php

class ilXAVCParticipantsTableGUI extends ilAdobeConnectTableGUI
{
    public ilCtrl $ctrl;
    protected array $local_roles = [];

    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->setId('xavc_participants');

        $this->setDefaultOrderDirection('ASC');
        $this->setDefaultOrderField('');
        $this->setExternalSorting(false);
        $this->setExternalSegmentation(false);

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->readLocalRoles();

        $this->setEnableNumInfo(true);

        $this->setTitle($a_parent_obj->pluginObj->txt("participants"));
        $this->addColumns();
        $this->addCommandButtons();
        $this->addMultiCommands();

        $this->setSelectAllCheckbox('usr_id[]');
        $this->setShowRowsSelector(true);

        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
        $this->setRowTemplate(
            $a_parent_obj->pluginObj->getDirectory() . '/templates/default/tpl.xavc_active_user_row.html'
        );
    }

    private function readLocalRoles(): void
    {
        global $DIC;

        $roles = $DIC->rbac()->review()->getLocalRoles($this->parent_obj->getObject()->getRefId());
        foreach ($roles as $role_id) {
            $role_title = ilObject::_lookupTitle($role_id);
            if (strpos($role_title, 'admin')) {
                $this->local_roles[$role_id] = [
                    'role_id' => $role_id,
                    'role_title' => $DIC->language()->txt('administrator')
                ];
            } elseif (strpos($role_title, 'member')) {
                $this->local_roles[$role_id] = [
                    'role_id' => $role_id,
                    'role_title' => $DIC->language()->txt('member')
                ];
            }
        }
    }

    private function addMultiCommands(): void
    {
        global $DIC;
        $ilUser = $DIC->user();
        $lng = $DIC->language();

        $is_owner = $ilUser->getId() == $this->parent_obj->getObject()->getOwner();

        if ($is_owner || ilXAVCPermissions::hasAccess(
                $ilUser->getId(),
                $this->parent_obj->ref_id,
                AdobeConnectPermissions::PERM_CHANGE_ROLE
            )) {
            $this->addMultiCommand('updateParticipants', $lng->txt('update'));
        }
        if ($is_owner || ilXAVCPermissions::hasAccess(
                $ilUser->getId(),
                $this->parent_obj->ref_id,
                AdobeConnectPermissions::PERM_ADD_PARTICIPANTS
            )) {
            $this->addMultiCommand('detachMember', $lng->txt('delete'));
        }
    }

    private function addCommandButtons(): void
    {
    }

    protected function prepareRow(array &$row): void
    {
        global $DIC;

        if ((int) $row['user_id']) {
            $this->ctrl->setParameter($this->parent_obj, 'usr_id', '');
            if ($row['user_id'] == $this->parent_obj->getObject()->getOwner()) {
                $row['checkbox'] = ilLegacyFormElementsUtil::formCheckbox(false, 'usr_id[]', $row['user_id'], false);
            } else {
                $row['checkbox'] = ilLegacyFormElementsUtil::formCheckbox(
                    false,
                    'usr_id[]',
                    $row['user_id'],
                    (int) $row['user_id'] ? false : true
                );
            }

            $assigned_roles = $DIC->rbac()->review()->assignedRoles($row['user_id']);
            foreach ($this->local_roles as $local_role) {
                $this->tpl->setCurrentBlock('roles');

                $this->tpl->setVariable('USER_ID', $row['user_id']);
                $this->tpl->setVariable('ROLE_ID', $local_role['role_id']);
                $this->tpl->setVariable('ROLE_NAME', $local_role['role_title']);

                if (in_array($local_role['role_id'], $assigned_roles)) {
                    $this->tpl->setVariable('ROLE_CHECKED', 'selected="selected"');
                }
                $this->tpl->parseCurrentBlock();
            }
        } else {
            $row['checkbox'] = '';
        }

        $user_name = '';
        if (strlen($row['lastname']) > 0) {
            $user_name .= $row['lastname'] . ', ';
        }
        if (strlen($row['firstname']) > 0) {
            $user_name .= $row['firstname'];
        }
        $row['user_name'] = $user_name;

        if ($row['xavc_status']) {
            $xavc_options = [
                'host' => $this->parent_obj->pluginObj->txt('presenter'),
                'mini-host' => $this->parent_obj->pluginObj->txt('moderator'),
                'view' => $this->parent_obj->pluginObj->txt('participant'),
                'denied' => $this->parent_obj->pluginObj->txt('denied')
            ];

            if ($row['xavc_status']) {
                $row['xavc_status'] = ilLegacyFormElementsUtil::formSelect(
                    $row['xavc_status'],
                    'xavc_status[' . $row['user_id'] . ']',
                    $xavc_options
                );

                if ($row['user_id'] == $this->parent_obj->getObject()->getOwner()) {
                    $row['xavc_status'] .= ' (' . $this->lng->txt('owner') . ')  ';
                }
            } else {
                $row['xavc_status'] = $this->parent_obj->pluginObj->txt('user_only_exists_at_ac_server');
            }
        }
    }

    public function initFilter(): void
    {
    }

    private function addColumns(): void
    {
        $this->addColumn('', '', '1px', true);
        $this->addColumn($this->lng->txt('name'), 'user_name');
        $this->optionalColumns = $this->getSelectableColumns();
        $this->visibleOptionalColumns = $this->getSelectedColumns();

        foreach ($this->visibleOptionalColumns as $column) {
            $this->addColumn($this->optionalColumns[$column]['txt'], $column);
        }

        $this->addColumn($this->parent_obj->pluginObj->txt('user_status'), 'xavc_status');
        $this->addColumn($this->parent_obj->pluginObj->txt('local_roles'), 'xavc_roles');
    }

    public function getSelectableColumns(): array
    {
        return [
            'login' => ['txt' => $this->lng->txt('login'), 'default' => true],
            'email' => ['txt' => $this->lng->txt('email'), 'default' => false]
        ];
    }

    protected function formatCellValue($column, array $row): string
    {
        if (array_key_exists($column, $row)) {
            return (string) $row[$column];
        }

        return '';
    }

    public function numericOrdering(string $field): bool
    {
        $sortables = [];

        if (in_array($field, $sortables)) {
            return true;
        }

        return false;
    }

    /**
     * @return string[]
     */
    protected function getStaticData(): array
    {
        return ['checkbox', 'user_name', 'login', 'xavc_status', 'xavc_roles'];
    }
}
