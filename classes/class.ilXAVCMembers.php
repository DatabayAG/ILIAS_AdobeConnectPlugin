<?php
/**
 * author: Nadia Ahmad <nahmad@databay.de>
 * $Id:$
 */

class ilXAVCMembers
{
	public $user_id = 0;
	public $ref_id = 0;
	public $sco_id = 0;
	public $status = null;
	public $xavc_login = null;
	public $principal_id = null;


	public function setScoId($a_sco_id)
	{
		$this->sco_id = $a_sco_id;
	}	
	
	public function getScoId()
	{
		return $this->sco_id;
	}

	public function setUserId($a_user_id)
	{
		$this->user_id = $a_user_id;
	}
	
	public function getUserId()
	{
		return $this->user_id;
	}
	
	public function setRefId($a_ref_id)
	{
		$this->ref_id = $a_ref_id;
	}

	public function getRefId()
	{
		return $this->ref_id;
	}

	public function setPrincipalId($a_principal_id)
	{
		$this->principal_id = $a_principal_id;
	}

	public function getPrincipalId()
	{
		return $this->principal_id;
	}
	/*
	 * Invite a user to a meeting as
	 * participant, presenter, or host
	 * (with a permission-id of view, mini-host, or host, respectively)
	 */

	public function setPresenterStatus()
	{
		$this->status = 'host';
	}
	public function setModeratorStatus()
	{
		$this->status = 'mini-host';
	}
	public function setParticipantStatus()
	{
		$this->status = 'view';
	}

	public function setStatus($a_status)
	{
		$this->status = $a_status;
	}
	
	public function getStatus()
	{
		return $this->status;
	}
	
	public function __construct($a_ref_id, $a_user_id )
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$this->db = $ilDB;
		$this->ref_id = $a_ref_id;
		$this->user_id = $a_user_id;

		$this->__read();

	}

	private function __read()
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;
		$res = $ilDB->queryf('SELECT * FROM rep_robj_xavc_members
			WHERE user_id = %s AND ref_id = %s',
				array('integer', 'integer'),
				array($this->user_id, $this->ref_id));

		while($row = $ilDB->fetchAssoc($res))
		{
			$this->sco_id = $row['sco_id'];
			$this->status = $row['xavc_status'];
		}		
	}

	public function getXAVCMembers()
	{
		// ILIAS_USERS - XAVC-PARTICIPANT UNIFICATION
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$xavc_members = array();

		$res = $ilDB->queryf('
			SELECT * FROM rep_robj_xavc_members mem
			INNER JOIN rep_robj_xavc_users usr
			WHERE ref_id = %s
			AND mem.user_id = usr.user_id',
				array('integer'), array($this->getRefId()));

		while($row = $ilDB->fetchAssoc($res))
		{
			$xavc_members[] = $row;
		}
		return $xavc_members;
	}

	public function insertXAVCMember()
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;
		
		$ilDB->insert('rep_robj_xavc_members', array(
			'user_id'	=> array('integer', $this->getUserId()),
			'ref_id'	=> array('integer', $this->getRefId()),
			'sco_id'	=> array('integer', $this->getScoId()),
			'xavc_status' => array('text', $this->getStatus())
		));
	}

	public function updateXAVCMember()
	{
		$this->db->update('rep_robj_xavc_members',
			array('xavc_status' => array('text', $this->getStatus())),
			array('user_id'	=> array('integer', $this->getUserId()),
				  'ref_id' => array('integer', $this->getRefId()))
		);
	}

	public static function deleteXAVCMember($a_user_id, $a_ref_id)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$ilDB->manipulateF('
			DELETE FROM rep_robj_xavc_members
			WHERE user_id = %s
			AND ref_id = %s',
			array('integer', 'integer'),
			array($a_user_id, $a_ref_id)
		);
	}

	public static function addXAVCUser($a_user_id, $a_xavc_login)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;
		
		$check = $ilDB->queryF('SELECT * FROM rep_robj_xavc_users WHERE user_id = %s',
							   array('integer'), array($a_user_id));
	
		if($ilDB->numRows($check))
		{
			$ilDB->update('rep_robj_xavc_users',
			array('xavc_login' => array('text', $a_xavc_login)),
			array('user_id' => array('integer', $a_user_id)));
		}
		else
		{
			$ilDB->insert('rep_robj_xavc_users',array(
				'user_id' => array('integer', $a_user_id),
				'xavc_login' => array('text', $a_xavc_login)
			));
		}
	}

	public static function _lookupXAVCLogin($a_user_id)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$xavc_login = null;

		$res = $ilDB->queryf('SELECT xavc_login FROM rep_robj_xavc_users
			WHERE user_id = %s', array('integer'), array($a_user_id));

		while($row = $ilDB->fetchAssoc($res))
		{
			$xavc_login = $row['xavc_login'];
		}
		return $xavc_login;
	}

	public static function _lookupUserId($a_xavc_login)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$user_id = null;

		$res = $ilDB->queryf('SELECT user_id FROM rep_robj_xavc_users
			WHERE xavc_login = %s', array('text'), array($a_xavc_login));

		while($row = $ilDB->fetchAssoc($res))
		{
			$user_id = $row['user_id'];
		}
		return $user_id;
	}
	
	public static function _lookupStatus($a_user_id, $a_ref_id)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;
		
		$xavc_status = null;

		$res = $ilDB->queryf('SELECT xavc_status FROM rep_robj_xavc_members
			WHERE user_id = %s AND ref_id = %s',
				array('integer', 'integer'), array($a_user_id, $a_ref_id));

		while($row = $ilDB->fetchAssoc($res))
		{
			$xavc_status = $row['xavc_status'];
		}
		return $xavc_status;
	}

	public static function _isMember($a_user_id, $a_ref_id)
	{
		/**
		 * @var $ilDB ilDB
		 *
		 */
		global $ilDB;

		$res = $ilDB->queryF('SELECT * FROM rep_robj_xavc_members
			WHERE user_id = %s AND ref_id = %s',
			array('integer','integer'), array($a_user_id , $a_ref_id));


		if($ilDB->numRows($res))
			return true;
		else return false;
	}
	
	public static function getMemberIds($ref_id)
	{
		global $ilDB;

		$res = $ilDB->queryF('SELECT user_id FROM rep_robj_xavc_members WHERE ref_id = %s',
			array('integer'), array($ref_id));

		$member_ids = array();

		while($row = $ilDB->fetchAssoc($res))
		{
			$member_ids[] = $row['user_id'];
		}

		return $member_ids;
	}
}
?>
