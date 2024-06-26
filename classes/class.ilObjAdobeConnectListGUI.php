<?php

class ilObjAdobeConnectListGUI extends ilObjectPluginListGUI
{
    public function initType(): void
    {
        $this->setType('xavc');
    }

    public function getGuiClass(): string
    {
        return 'ilObjAdobeConnectGUI';
    }

    public function initCommands(): array
    {
        $this->link_enabled = false;
        $this->copy_enabled = false;

        $command_array = [];
        $command_array[] = [
            'permission' => 'read',
            'cmd' => 'showContent',
            'lang_var' => 'content'
        ];

        $command_array[] = [
            'permission' => 'read',
            'cmd' => 'showContent',
            'txt' => $this->txt('access'),
            'default' => true
        ];

        $command_array[] = [
            'permission' => 'write',
            'cmd' => 'editProperties',
            'txt' => $this->txt('properties'),
            'default' => false
        ];

        return $command_array;
    }

    public function insertCommands(
        bool $use_async = false,
        bool $get_async_commands = false,
        string $async_url = '',
        bool $header_actions = false
    ): string {
        global $DIC;
        $ilUser = $DIC->user();

        if (
            !ilObjAdobeConnectAccess::_hasMemberRole($ilUser->getId(), $this->ref_id) &&
            !ilObjAdobeConnectAccess::_hasAdminRole($ilUser->getId(), $this->ref_id)
        ) {
            /**
             * $this->commands is initialized only once. appending the join-button
             * at this point will produce N buttons for the Nth item
             */
            $this->commands = array_reverse(
                array_merge(
                    $this->initCommands(),
                    [
                        [
                            'permission' => 'visible',
                            'cmd' => 'join',
                            'txt' => $this->txt('join'),
                            'default' => true
                        ],
                        [
                            'permission' => 'visible',
                            'cmd' => 'join',
                            'txt' => $this->txt('join'),
                            'default' => false
                        ]
                    ]
                )
            );

            $this->info_screen_enabled = false;
        } else {
            $this->commands = $this->initCommands();
        }

        return parent::insertCommands($use_async, $get_async_commands, $async_url);
    }

    public function getProperties(): array
    {
        $props = [];

        $objectData = ilObjAdobeConnect::getObjectData($this->obj_id);

        if ($objectData->permanent_room == 1) {
            $props[] = [
                'alert' => false,
                'value' => $this->plugin->txt('permanent_room')
            ];
        } else {
            if ((int) $objectData->start_date > time() || (int) $objectData->end_date < time()) {
                $props[] = [
                    'alert' => false,
                    'value' => $this->plugin->txt('meeting_not_available')
                ];
            } else {
                $props[] = [
                    'alert' => false,
                    'property' => $this->txt('start_date'),
                    'value' => ilDatePresentation::formatDate(new ilDateTime($objectData->start_date, IL_CAL_UNIX))
                ];

                $props[] = [
                    'alert' => false,
                    'property' => $this->txt('duration'),
                    'value' => ilDatePresentation::formatPeriod(
                        new ilDateTime($objectData->start_date, IL_CAL_UNIX),
                        new ilDateTime($objectData->end_date, IL_CAL_UNIX)
                    )
                ];
            }
        }
        return $props;
    }
}
