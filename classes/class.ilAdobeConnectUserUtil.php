<?php
/**
 * @author Nadia Ahmad <nahmad@databay.de>
 * 
*/
class ilAdobeConnectUserUtil
{
    /**
     *  User id
     *
     * @var String
     */
    private $id;
    /**
     *  User email address
     *
     * @var String
     */
    private $email;
    /**
     *  User login
     *
     * @var String
     */
    private $login;
    /**
     *  User password
     *
     * @var String
     */
    private $pass;
    /**
     *  User first name
     *
     * @var String
     */
    private $first_name;
    /**
     *  User second name
     *
     * @var String
     */
    private $last_name;

	/**
	 *  Adobe Connect Login
	 *
	 * @var String
	 */
	private $xavc_login;


    /**
     *  Constructor
     *
     * @global ilDB $ilDB
     * @param String $user_id
     */
    public function __construct($user_id)
    {
		$this->id = $user_id;
		$this->readAdobeConnectUserData();
	}

	public function ensureAccountExistance()
	{
//		$auth_mode = ilAdobeConnectServer::getSetting('auth_mode');
//		 here we can decide in future if it is needed to check and/or create accounts at an AC-Server or not
		
		$this->ensureLocalAccountExistance();

        //In the SWITCH aai case, we don't have enough permissions to search for users
        //Therefore, we just try to add the user to the meeting, regardless of whether the account exists already or not
        if(ilAdobeConnectServer::getSetting('user_assignment_mode') != ilAdobeConnectServer::ASSIGN_USER_SWITCH)
        {
		    $this->ensureAdobeConnectAccountExistance();
        }
	}
	
	private function readAdobeConnectUserData()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$result = $ilDB->queryF("SELECT email,login,passwd,firstname,lastname FROM usr_data WHERE usr_id= %s",
			array('integer'), array($this->getId()));

		while($record = $ilDB->fetchAssoc($result))
		{
			$this->email      = $record['email'];
			$this->login      = $record['login'];
			$this->pass       = $record['passwd'];
			$this->first_name = $record['firstname'];
			$this->last_name  = $record['lastname'];
		}

		$login_res = $ilDB->queryf('
			SELECT xavc_login FROM rep_robj_xavc_users WHERE user_id = %s',
			array('integer'), array($this->getId()));

		while($row = $ilDB->fetchAssoc($login_res))
		{
			$this->xavc_login = $row['xavc_login'];
		}
		if($GLOBALS['DEBUGACSSO'])
		{
			global $ilLog;
			$ilLog->write(__METHOD__ . ': Found adobe user login ' . $this->xavc_login . ' by ILIAS usr_id ' . $this->getId());
		}
	}
	
	private function ensureLocalAccountExistance()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

        //check if is valid xavc_login
		if(!$this->xavc_login)
		{
			// generate valid xavc_login_name with assignment_mode-setting
			$this->xavc_login = self::generateXavcLoginName($this->getId());

			// insert generated login-name into xavc_users
			$ilDB->insert('rep_robj_xavc_users',
				array(
					'user_id'    => array('integer', $this->getId()),
					'xavc_login' => array('text', $this->xavc_login)
				));
		}
	}

	private function ensureAdobeConnectAccountExistance()
	{
		// check if this login exists at ac-server  
		$search = $this->searchUser($this->xavc_login);

        // if does not exist, create account at ac-server
		if(!$search)
		{
			$this->addUser();
		}
	}
	
	public function ensureUserFolderExistance($a_xavc_login = "")
	{
		$xmlAPI = ilXMLApiFactory::getApiByAuthMode();
		$session = $xmlAPI->getBreezeSession();

		$instance = ilAdobeConnectServer::_getInstance();
		$login = $instance->getLogin();
		$pass = $instance->getPasswd();
		
		if(ilAdobeConnectServer::getSetting('use_user_folders') == 1)
		{
			if($a_xavc_login)
			{
				$xavc_login = $a_xavc_login;
			}
			else
			{
				$xavc_login = $this->getXAVCLogin();
			}

			if ($session != NULL && $xmlAPI->login($login, $pass, $session))
			{
				$folder_id = $xmlAPI->lookupUserFolderId($xavc_login, $session);
				
				if($folder_id == NULL)
				{
					$folder_id = $xmlAPI->createUserFolder($xavc_login, $session);
				}
				
				return $folder_id;
			}
		}
		else
		{
			if ($session != NULL && $xmlAPI->login($login, $pass, $session))
			{
				$folder_id = $xmlAPI->getShortcuts('my-meetings', $session);
				return $folder_id;
			}
			else
			{
				return NULL;
			}
		}
	}
	
    /**
     *  Returns user id
     *
     * @return String
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     *  Returns user email
     *
     * @return String
     */
    public function getEmail()
    {
        return $this->email;
    }
    /**
     *  Returns user login
     *
     * @return String
     */
    public function getLogin()
    {
        return $this->login;
    }
    /**
     *  Returns user password
     *
     * @return String
     */
    public function getPass()
    {
        return $this->pass;
    }
    /**
     *  Returns user first name
     *
     * @return String
     */
    public function getFirstName()
    {
        return $this->first_name;
    }
    /**
     *  Returns user last name
     *
     * @return String
     */
    public function getLastName()
    {
        return $this->last_name;
    }
	public function setXAVCLogin($a_xavc_login)
	{
		$this->xavc_login = $a_xavc_login;
	}

	public function getXAVCLogin()
	{
		return $this->xavc_login;
	}
    /**
     *  Adds user to the Adobe Connect server
     *
     * @param String $subject
     * @param String $message
     */
    public function addUser()
    {
		$xmlAPI = ilXMLApiFactory::getApiByAuthMode();
		$session = $xmlAPI->getBreezeSession();

        $instance = ilAdobeConnectServer::_getInstance();
        $login = $instance->getLogin();
        $pass = $instance->getPasswd();

        $user_pass = $this->generatePass();
        
        if ($session != NULL && $xmlAPI->login($login, $pass, $session))
        {
			$xmlAPI->addUser($this->getXAVCLogin(), $this->email, $user_pass, $this->first_name, $this->last_name, $session);
			$xmlAPI->logout($session);
        }
		// kkoch: 13.03.2012: die Meldung über das Login eines neuen Nutzers bitte komplett unterdrücken
		/*
		 * 
		 *	include_once("./Services/Mail/classes/class.ilMail.php");
		 * $subject = $this->txt('new_ac_user_subject');
		 * $message = $this->txt('new_ac_user_mail');
		 * 
		 * $mail = new ilMail(ANONYMOUS_USER_ID);
		 * $message = $message.' '.$user_pass;
		 * 
		 * $mail->sendMail($this->email, '', '', $subject, $message, NULL, array('normal'));
		 * 
		 * */
    }

	/**Search user on the Adobe Connect server
	 * 
	 * @param string $a_xavc_login
	 * @return bool|string
	 */
	public function searchUser($a_xavc_login = '')
    {
    	if($a_xavc_login)
    	{
    		$xavc_login = $a_xavc_login;
    	}
    	else
    	{
    		$xavc_login = $this->getXAVCLogin();
    	}
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();

        $instance = ilAdobeConnectServer::_getInstance();
        $login = $instance->getLogin();
        $pass = $instance->getPasswd();

        if ($session != NULL && $xmlAPI->login($login, $pass, $session))
        {
			$search = $xmlAPI->searchUser($xavc_login, $session);
            $xmlAPI->logout($session);
            return $search;
        }
    }

	/**
	 *  Log in user on the Adobe Connect server
	 * @return String     Returns NULL if user doesn't exist on the server
	 */
	public function loginUser()
	{
		$xmlAPI  = ilXMLApiFactory::getApiByAuthMode();
		$session = $xmlAPI->getBreezeSession();

		return $xmlAPI->externalLogin($this->getXAVCLogin(), null, $session);
	}

    /**
     *  Log out user on the Adobe Connect server
     *
     * @param String $session
     */
    public function logoutUser($session)
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $xmlAPI->logout($session);
    }

    /**
     *  Generates a new password
     *
     * @return String   Generated pass
     */
    private function generatePass()
    {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        mt_srand(microtime() * 1000000);

		$password = '';
        for($i = 0; $i < 8; $i++)
        {
            $key = mt_rand(0,strlen($caracteres)-1);
            $password = $password . $caracteres{$key};
        }

        return $password;
    }

	/** Generates the login name for a user depending on assignment_mode setting
	 * 
	 * @param integer $user_id user_id 
	 */
	public static function generateXavcLoginName($user_id)
	{
        // set default when there is no setting set: assign_user_email
		$assignment_mode = ilAdobeConnectServer::getSetting('user_assignment_mode')
			? ilAdobeConnectServer::getSetting('user_assignment_mode')
			: 'assign_user_email';


		switch ($assignment_mode)
		{
			case 'assign_user_email':
				$xavc_login = IL_INST_ID.'_'.$user_id.'_'.ilObjUser::_lookupEmail($user_id);
				break;

			case 'assign_ilias_login':
				$xavc_login = IL_INST_ID.'_'.$user_id.'_'.ilObjUser::_lookupLogin($user_id);
				break;

            //The SWITCH aai/DFN case, only return e-mail address
			case 'assign_dfn_email':
			case 'assign_breezeSession':
				$xavc_login = ilObjUser::_lookupEmail($user_id);
				break;
		}
		
		return $xavc_login;
	}
}
?>