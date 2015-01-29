<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Adobe Connect server attributes
 * @author  Nadia Ahmad <nahmad@databay.de>
 * @version $Id:$
 */

// class should be renamed to ilAdobeConnectSettings 
//@Todo RENAME class to ilAdobeConnectSettings !!!
class ilAdobeConnectServer
{
	// User-Assignment-Mode
	const ASSIGN_USER_EMAIL     = 'assign_user_email';
	const ASSIGN_USER_ILIAS     = 'assign_ilias_login';
	const ASSIGN_USER_SWITCH    = 'assign_breezeSession';
	const ASSIGN_USER_DFN_EMAIL = 'assign_dfn_email';
	
	// Auth-Mode
	const AUTH_MODE_PASSWORD  = 'auth_mode_password';
	const AUTH_MODE_HEADER    = 'auth_mode_header';
	const AUTH_MODE_SWITCHAAI = 'auth_mode_switchaai';
	const AUTH_MODE_DFN       = 'auth_mode_dfn';


	/**
	 *  Server address
	 * @var String
	 */
	private $server;
	/**
	 *  Server Port
	 * @var String
	 */
	private $port = NULL;
	/**
	 *  Presentation Server address
	 * @var String
	 */
	private $presentation_server;
	/**
	 *  Presentation Server Port
	 * @var String
	 */
	private $presentation_port = NULL;
	/**
	 *  Admin login
	 * @var String
	 */
	private $login;
	/**
	 *  Admin pass. Must belongs to the next groups: hosts, authors and administrators
	 * @var String
	 */
	private $pass;
	/**
	 *  Maximum number of concurrent sessions on the server
	 * @var Integer
	 */
	private $num_max_vc;

	/**
	 * @var Boolean
	 */
	private $public_vc_enabled = false;

	/**
	 * @var Integer
	 */
	private $num_public_vc = null;

	private $buffer_seconds_before = 1800; // 30 minutes
	private $buffer_seconds_after = 1800; // 30 minutes

	private $schedule_lead_time = 172800; // 48 hours

	/**
	 * @var null
	 */
	private $user_assignment_mode = null;
	
	/**
	 * @var string $auth_mode Authentification mode
	 */
	private $auth_mode = null;
	
	/**
	 * @var string $x_user_id Header-Variable needed for HTTP-Header-Auth.
	 */
	private $x_user_id = null;
	
	/**
	 *  Singleton instance
	 * @var ilAdobeConnectServer
	 */
	private static $instance;

	/**
	 * Default constructor

	 */
	private function __construct()
	{
	}

	/**
	 *  Return an instance of the class itself
	 * @return ilAdobeConnectServer
	 */
	public static function _getInstance()
	{
		if(!self::$instance instanceof self)
		{
			self::$instance = new self;
			self::$instance->doRead();
		}

		return self::$instance;
	}

	/**
	 *  Reads attributes from the setup.xml file
	 */
	public function doRead()
	{
		$this->server              = self::getSetting('server');
		$this->port                = self::getSetting('port');
		$this->login               = self::getSetting('login');
		$this->pass                = self::getSetting('password');
		$this->presentation_server = self::getSetting('presentation_server');
		$this->presentation_port   = self::getSetting('presentation_port');

		$this->num_max_vc = self::getSetting('num_max_vc');
		
		$this->x_user_id = self::getSetting('x_user_id');
		$this->user_assignment_mode = self::getSetting('user_assignment_mode');
		$this->auth_mode = self::getSetting('auth_mode');
	}

	/**
	 * Commits the current server settings

	 */
	public function commitSettings()
	{
		if(self::$instance instanceof self)
		{
			self::$instance->doRead();
		}
	}

	/**
	 * Sets the server name.
	 * @param String $a_adobe_server
	 */
	public function setServer($a_adobe_server)
	{
		$this->server = $a_adobe_server;
	}

	/**
	 *  Returns the server name. Previously, it calls doRead method
	 * @return String
	 */
	public function getServer()
	{
		return $this->server;
	}

	/**
	 * Sets the server port.
	 * @param String $server_port
	 */
	public function setPort($a_port)
	{
		$this->port = $a_port;
	}

	/**
	 *  Returns the server Porte.
	 * @return String
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * Sets the presentation server name.
	 * @param String $adobe_server
	 */
	public function setPresentationServer($a_adobe_server)
	{
		$this->presentation_server = $a_adobe_server;
	}

	/**
	 *  Returns the presentation server name.
	 * @return String
	 */
	public function getPresentationServer()
	{
		return $this->presentation_server;
	}

	/**
	 * Sets the presentation server port.
	 * @param String $server_port
	 */
	public function setPresentationPort($a_port)
	{
		$this->presentation_port = $a_port;
	}

	/**
	 *  Returns the presentation server Port.
	 * @return String
	 */
	public function getPresentationPort()
	{
		return $this->presentation_port;
	}

	/**
	 *  Sets the admin login.
	 * @param String $login
	 */
	public function setLogin($a_login)
	{
		$this->login = $a_login;
	}

	/**
	 *  Returns the admin login. Previously, it calls doRead method
	 * @return String
	 */
	public function getLogin()
	{
		return $this->login;
	}

	/**
	 *  Sets the admin password.
	 * @param String $a_password
	 */
	public function setPasswd($a_password)
	{
		$this->pass = $a_password;
	}

	/**
	 *  Returns the admin password. Previously, it calls doRead method
	 * @return String
	 */
	public function getPasswd()
	{
		return $this->pass;
	}

	/**
	 *  Sets the number of max. VirtualClassrooms running at the server.
	 * @param String $num_max_vc
	 */

	public function setNumMaxVirtualClassrooms($a_num_max_vc)
	{
		$this->num_max_vc = $a_num_max_vc;
	}

	public function getNumMaxVirtualClassrooms()
	{
		return $this->num_max_vc;
	}

	public function setPublicVcEnabled($a_public_vc_enabled)
	{
		$this->public_vc_enabled = $a_public_vc_enabled;
	}

	public function getPublicVcEnabled()
	{
		return $this->public_vc_enabled;
	}

	public function setNumPublicVc($a_num_public_vc)
	{
		$this->num_public_vc = $a_num_public_vc;
	}

	public function getNumPublicVc()
	{
		return $this->num_public_vc;
	}

	public function getBufferBefore()
	{
		return $this->buffer_seconds_before;
	}

	public function getBufferAfter()
	{
		return $this->buffer_seconds_after;
	}

	public function getScheduleLeadTime()
	{
		if(!is_null($tmp = $this->getSetting('schedule_lead_time')))
		{
			$this->schedule_lead_time = $tmp;
		}
		return $this->schedule_lead_time;
	}

	public function setScheduleLeadTime($time)
	{
		$this->schedule_lead_time = $time;
		$this->setSetting('schedule_lead_time', $time);
	}

	public function setAuthMode($auth_mode)
	{
		$this->auth_mode = $auth_mode;
	}

	public function getAuthMode()
	{
		return $this->auth_mode;
	}

	public function setXUserId($x_user_id)
	{
		$this->x_user_id = $x_user_id;
	}

	public function getXUserId()
	{
		return $this->x_user_id;
	}

	/**
	 * @param null $user_assignment_mode
	 */
	public function setUserAssignmentMode($user_assignment_mode)
	{
		$this->user_assignment_mode = $user_assignment_mode;
	}

	/**
	 * @return null
	 */
	public function getUserAssignmentMode()
	{
		return $this->user_assignment_mode;
	}

	public static function getSetting($a_keyword)
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		if($ilDB->tableExists('rep_robj_xavc_settings'))
		{
			$res = $ilDB->queryF('SELECT value FROM rep_robj_xavc_settings WHERE keyword = %s',
				array('text'), array($a_keyword));

			$row = $ilDB->fetchAssoc($res);

			return $row['value'];
		}

		return null;
	}

	public static function setSetting($a_keyword, $a_value)
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		if($ilDB->tableExists('rep_robj_xavc_settings'))
		{
			$ilDB->manipulateF("DELETE FROM rep_robj_xavc_settings WHERE keyword = %s",
				array('text'), array($a_keyword));

			$ilDB->insert("rep_robj_xavc_settings", array(
				"keyword" => array("text", $a_keyword),
				"value"   => array("text", $a_value)
			));
		}
		return true;
	}


	public static function getPresentationUrl($api_call = false)
	{
		if($api_call)
		{
			$server = self::getSetting('server');
			$port   = self::getSetting('port');
		}
		else
		{
			$server = self::getSetting('presentation_server');
			$port   = self::getSetting('presentation_port');
		}

		if(!$port || $port == '8080')
			return $server;
		else
			return $server . ':' . $port;
	}
	
	public static function getRoleMap()
	{
		global $ilDB;
		
		$keywords = array('crs_owner', 'crs_admin', 'crs_tutor', 'crs_member', 'grp_owner', 'grp_admin', 'grp_member');
		
		$res = $ilDB->query('SELECT * FROM rep_robj_xavc_settings WHERE '.
		$ilDB->in('keyword', $keywords, false, 'text'));
		
		$map = array();
		while($row = $ilDB->fetchAssoc($res))
		{
			$map[$row['keyword']] = $row['value'];
		}

		// Fix for: http://www.ilias.de/mantis/view.php?id=12379
		$map['crs_owner'] = 'host';
		$map['grp_owner'] = 'host';

		return $map;
	}


	/*
	 * @param int $auth_mode_key
	 */
	public static function useSwitchaaiAuthMode($auth_mode_key)
	{
		require_once './Services/Authentication/classes/class.ilAuthUtils.php';
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		if($auth_mode_key == AUTH_SHIBBOLETH) {
			return true;
		}

		$res = $ilDB->queryF('SELECT value FROM rep_robj_xavc_settings WHERE keyword = %s',
			array('text'), array('auth_mode_switchaai_account_type'));

		$row = $ilDB->fetchAssoc($res);
		$arr_switch_auth_modes = unserialize($row['value']);
		$result = false;
		if (is_array($arr_switch_auth_modes)) {
			$result = in_array($auth_mode_key,$arr_switch_auth_modes);
		}
		return $result;
	}
}