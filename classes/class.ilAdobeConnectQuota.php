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
	private $currentMeetings;

	/**
	 * Stores the current number of public and scheduled rooms in the
	 * field $currentState[$type . '_meetings'].
	 * @var int[]
	 */
	private $currentState = array();


	public function __construct()
	{
		$this->adcInfo = ilAdobeConnectServer::_getInstance();
	}

	private function fetchCurrentMeetings()
	{
		$api     = ilXMLApiFactory::getApiByAuthMode();
		$session = $api->getAdminSession();
		return $api->getActiveScos($session);
	}

	private function buildCurrentMeetings(array $data)
	{
		global $DIC;
		$ilDB = $DIC->database();
 		
		$ids = array();
		$currentMeetings = array();
		foreach($data as $sco)
		{
			$scoObject                            = new stdClass();
			$scoObject->name                      = $sco['name'];
			$currentMeetings[(int)$sco['sco-id']] = $scoObject;

			$ids[] = (int)$sco['sco-id'];
		}

		$currentState                     = new stdClass();
		$currentState->public_meetings    = 0;
		$currentState->scheduled_meetings = 0;

		$query = 'SELECT sco_id, "scheduled" AS type FROM rep_robj_xavc_data WHERE ' . $ilDB->in('sco_id', $ids, false, 'integer');
		$rset  = $ilDB->query($query);
		while($row = $ilDB->fetchObject($rset))
		{
			$id                         = $row->sco_id;
			$currentMeetings[$id]->type = $row->type;
			$currentState->{$row->type . '_meetings'} += 1;
		}

		$this->currentState = $currentState;

		return $currentMeetings;
	}

	private function getCurrentMeetings($forceReload = false)
	{
		if($forceReload || !isset($this->currentMeetings))
		{
			$m = $this->fetchCurrentMeetings();
			if(is_array($m) && $m)
			{
				$this->currentMeetings = $this->buildCurrentMeetings($m);
			}
			else
			{
				$this->currentMeetings = array();
			}
		}
		return $this->currentMeetings;
	}

	/**
	 * Returns true if there is an available slot for starting a scheduled meeting.
	 * The number of available slots includes the buffer as set in the administration.
	 * @return boolean
	 */
	public function mayStartScheduledMeeting($sco_id)
	{
		$scheduledSlots = (int)$this->adcInfo->getSetting('ac_interface_objects');
		if((int)$scheduledSlots <= 0)
		{
			return true;
		}
		$bufferSlots = (int)$this->adcInfo->getSetting('ac_interface_objects_buffer');
		$this->getCurrentMeetings();
		
		return ($bufferSlots + $scheduledSlots) > $this->currentState->scheduled_meetings || array_key_exists($sco_id, $this->currentMeetings);
	}

	public function checkConcurrentMeetingDates(ilDateTime $endDate, ilDateTime $startDate = null, $ignoreId = null)
	{
		global $DIC;
		$ilDB = $DIC->database();

		if($startDate == null)
		{
			$startDate = new ilDateTime(time(), IL_CAL_UNIX);
		}

		$sim = array();

		$srv = ilAdobeConnectServer::_getInstance();

		$new_start_date = $startDate->getUnixTime() - $srv->getBufferBefore();
		$new_end_date   = $endDate->getUnixTime() + $srv->getBufferAfter();

		$query = array(
			'SELECT * FROM rep_robj_xavc_data',
			'WHERE (',
			'(%s > start_date AND %s < end_date) OR',
			'(%s > start_date AND %s < end_date) OR',
			'(%s < start_date AND %s > end_date)',
			')'
		);

		$types  = array('integer', 'integer', 'integer', 'integer', 'integer', 'integer');
		$values = array($new_start_date, $new_start_date, $new_end_date, $new_end_date, $new_start_date, $new_end_date);

		if($ignoreId !== null)
		{
			$query[]  = 'AND id <> %s';
			$types[]  = 'integer';
			$values[] = $ignoreId;
		}

		$res = $ilDB->queryF(join(' ', $query), $types, $values);

		while($row = $ilDB->fetchObject($res))
		{
			if(ilObject::_hasUntrashedReference($row->id))
				$sim[] = $row;
		}

		return $sim;
	}
}