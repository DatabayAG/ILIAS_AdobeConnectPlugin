<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */


class ilXAVCParticipantsDataProvider extends ilAdobeConnectTableDatabaseDataProvider
{
    protected function getSelectPart(array $filter): string
    {
        $fields = [
            'rep_robj_xavc_members.user_id',
            'rep_robj_xavc_members.xavc_status',
            'usr_data.lastname',
            'usr_data.firstname',
            'usr_data.login',
            'usr_data.email'

        ];

        return implode(', ', $fields);
    }

    protected function getFromPart(array $filter): string
    {
        $joins = [
            'INNER JOIN usr_data ON usr_data.usr_id = user_id',
        ];

        return 'rep_robj_xavc_members ' . implode(' ', $joins);
    }

    protected function getWherePart(array $filter): string
    {
        $where = [];

        $where[] = ' rep_robj_xavc_members.ref_id = ' . $this->db->quote($this->parent_obj->getObject()->getRefId(), 'integer');

        return implode(' AND ', $where);
    }

    protected function getGroupByPart(): string
    {
        return '';
    }

    protected function getHavingPart(array $filter): string
    {
        return '';
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getOrderByPart(array $params): string
    {
        if (isset($params['order_field'])) {
            if (!is_string($params['order_field'])) {
                throw new InvalidArgumentException('Please provide a valid order field.');
            }

            $fields = [
                'user_id',
                'firstname',
                'lastname',
                'login',
                'email',
                'xavc_status'
            ];

            if (!in_array($params['order_field'], $fields)) {
                $params['order_field'] = 'user_id';
            }

            if (!isset($params['order_direction'])) {
                $params['order_direction'] = 'ASC';
            } else {
                if (!in_array(strtolower($params['order_direction']), ['asc', 'desc'])) {
                    throw new InvalidArgumentException('Please provide a valid order direction.');
                }
            }

            return $params['order_field'] . ' ' . $params['order_direction'];
        }

        return '';
    }

    protected function getAdditionalItems($data): array
    {
        $xavc_participants = $this->parent_obj->getObject()->getParticipants();
        $selected_user_ids = [];

        foreach ($data['items'] as $db_item) {
            $selected_user_ids[] = (int) $db_item['user_id'];
        }

        if ($xavc_participants != null) {
            foreach ($xavc_participants as $participant) {
                $user_id = ilXAVCMembers::_lookupUserId($participant['login']);
                //if the user_id is in the xavc members table in ilias (->$selected_user_ids), all information is already in $data['items'], so we just continue.
                //if the user_id belongs to the technical user, we just continue, because we don't want him to be shown
                if (in_array(
                        (int) $user_id,
                        $selected_user_ids
                    ) || $participant['login'] == ilAdobeConnectServer::getSetting('login')) {
                    continue;
                }

                //when user_id is bigger than 0, he exists. So we get it's information by using ilObjUser
                if ($user_id > 0) {
                    $tmp_user = ilObjectFactory::getInstanceByObjId($user_id, false);
                    if (!$tmp_user) {
                        // Maybe delete entries xavc_members xavc_users tables
                        continue;
                    }

                    $firstname = $tmp_user->getFirstname();
                    $lastname = $tmp_user->getLastname();
                    if ($tmp_user->hasPublicProfile() && $tmp_user->getPref('public_email') == 'y') {
                        $user_mail = $tmp_user->getEmail();
                    } else {
                        $user_mail = '';
                    }
                } else {
                    $firstname = $participant['name'];
                    $lastname = '';
                    $user_mail = '';
                }

                $ac_user['user_id'] = (int) $user_id;
                $ac_user['firstname'] = (string) $firstname?: '';
                $ac_user['lastname'] = (string)  $lastname ?: '';
                $ac_user['login'] = (string)  $participant['login']?: '';
                $ac_user['email'] = (string) $user_mail?: '';
                $ac_user['xavc_status'] = (string) $participant['status']?: '';

                $data['items'][] = $ac_user;
            }
        }

        return $data;
    }
}
