<?php

class ilAdobeConnectContainerParticipants extends ilParticipants
{
    /**
     * @param int $a_obj_id
     * @return ilAdobeConnectParticipantsNullObject|ilCourseParticipants|ilGroupParticipants|ilParticipants|ilSessionParticipants|mixed
     */
    public static function getInstanceByObjId(int $a_obj_id): ilParticipants
    {
        $type = ilObject::_lookupType((int) $a_obj_id);

        switch ($type) {
            case 'crs':
                return ilCourseParticipants::_getInstanceByObjId((int) $a_obj_id);

            case 'grp':
                return ilGroupParticipants::_getInstanceByObjId((int) $a_obj_id);

            case 'sess':
                return ilSessionParticipants::_getInstanceByObjId((int) $a_obj_id);

            default:
                return new ilAdobeConnectParticipantsNullObject();
        }
    }
}
