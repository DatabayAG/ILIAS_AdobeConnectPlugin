<?php

class ilAdobeConnectQuota
{

    /**
     * @var ilAdobeConnectServer
     */
    private $adcInfo;

    /**
     * List of all running meetings (as reported by the server) that are managed
     * by the current ILIAS client
     * @var array
     */
    private ?array $currentMeetings = null;

    /**
     * Stores the current number of public and scheduled rooms in the
     * field $currentState[$type . '_meetings'].
     * @var int[]
     */
    private array $currentState = [];

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->adcInfo = ilAdobeConnectServer::_getInstance();
    }

    private function fetchCurrentMeetings(): array
    {
        $api = ilXMLApiFactory::getApiByAuthMode();
        $session = $api->getAdminSession();
        return $api->getActiveScos($session);
    }

    private function buildCurrentMeetings(array $data): array
    {
        $ids = [];
        $currentMeetings = [];
        foreach ($data as $sco) {
            $scoObject = new stdClass();
            $scoObject->name = $sco['name'];
            $currentMeetings[(int) $sco['sco-id']] = $scoObject;

            $ids[] = (int) $sco['sco-id'];
        }

        $currentState = new stdClass();
        $currentState->public_meetings = 0;
        $currentState->scheduled_meetings = 0;

        $query = 'SELECT sco_id, "scheduled" AS type FROM rep_robj_xavc_data WHERE ' .
            $this->db->in(
                'sco_id',
                $ids,
                false,
                'integer'
            );

        $rset = $this->db->query($query);
        while ($row = $this->db->fetchObject($rset)) {
            $id = $row->sco_id;
            $currentMeetings[$id]->type = $row->type;
            $currentState->{$row->type . '_meetings'} += 1;
        }

        $this->currentState = $currentState;

        return $currentMeetings;
    }

    private function getCurrentMeetings(bool $forceReload = false): array
    {
        if ($forceReload || $this->currentMeetings == null || !is_array($this->currentMeetings)) {
            $m = $this->fetchCurrentMeetings();
            if (is_array($m) && $m) {
                $this->currentMeetings = $this->buildCurrentMeetings($m);
            }
        }
        return $this->currentMeetings;
    }

    /**
     * Returns true if there is an available slot for starting a scheduled meeting.
     * The number of available slots includes the buffer as set in the administration.
     * @return bool
     */
    public function mayStartScheduledMeeting($sco_id): bool
    {
        $scheduledSlots = (int) $this->adcInfo->getSetting('ac_interface_objects');
        if ((int) $scheduledSlots <= 0) {
            return true;
        }
        $bufferSlots = (int) $this->adcInfo->getSetting('ac_interface_objects_buffer');

        return ($bufferSlots + $scheduledSlots) > $this->currentState->scheduled_meetings
            || array_key_exists($sco_id, $this->getCurrentMeetings());
    }

    public function checkConcurrentMeetingDates(
        ilDateTime $endDate,
        ilDateTime $startDate = null,
        $ignoreId = null
    ): array {
        if ($startDate == null) {
            $startDate = new ilDateTime(time(), IL_CAL_UNIX);
        }

        $sim = [];

        $srv = ilAdobeConnectServer::_getInstance();

        $new_start_date = $startDate->getUnixTime() - $srv->getBufferBefore();
        $new_end_date = $endDate->getUnixTime() + $srv->getBufferAfter();

        $query = [
            'SELECT * FROM rep_robj_xavc_data',
            'WHERE (',
            '(%s > start_date AND %s < end_date) OR',
            '(%s > start_date AND %s < end_date) OR',
            '(%s < start_date AND %s > end_date)',
            ')'
        ];

        $types = ['integer', 'integer', 'integer', 'integer', 'integer', 'integer'];
        $values = [$new_start_date, $new_start_date, $new_end_date, $new_end_date, $new_start_date, $new_end_date];

        if ($ignoreId !== null) {
            $query[] = 'AND id <> %s';
            $types[] = 'integer';
            $values[] = $ignoreId;
        }

        $res = $this->db->queryF(join(' ', $query), $types, $values);

        while ($row = $this->db->fetchObject($res)) {
            if (ilObject::_hasUntrashedReference($row->id)) {
                $sim[] = $row;
            }
        }

        return $sim;
    }
}
