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
                include_once './Modules/Course/classes/class.ilCourseParticipants.php';
                return ilCourseParticipants::_getInstanceByObjId((int) $a_obj_id);

            case 'grp':
                include_once './Modules/Group/classes/class.ilGroupParticipants.php';
                return ilGroupParticipants::_getInstanceByObjId((int) $a_obj_id);

            case 'sess':
                include_once './Modules/Session/classes/class.ilSessionParticipants.php';
                return ilSessionParticipants::_getInstanceByObjId((int) $a_obj_id);

            default:
                include_once 'class.ilAdobeConnectParticipantsNullObject.php';
                return new ilAdobeConnectParticipantsNullObject();
        }
    }
}
