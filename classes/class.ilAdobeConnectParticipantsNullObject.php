<?php

include_once('./Services/Membership/classes/class.ilParticipants.php');

/**
 * Class ilAdobeConnectParticipantsNullObject
 */
class ilAdobeConnectParticipantsNullObject extends ilParticipants
{
	public function __construct()
	{
	}
	
	public function getParticipants()
	{
		return array();
	}
	
	public function getMembers()
	{
		return array();
	}
	
	public function getAdmins()
	{
		return array();
	}
	
	public function getTutors()
	{
		return array();
	}
}