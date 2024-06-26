<?php

class ilAdobeConnectUserUtil
{
    private int $id = 0;
    private string $email = '';
    private string $login = '';
    private string $pass = '';
    private string $first_name = '';
    private string $last_name = '';
    private string $xavc_login = '';
    
    public function __construct(int $user_id)
    {
        $this->id = $user_id;
        $this->readAdobeConnectUserData();
    }
    
    public function ensureAccountExistence(): void
    {
        $this->ensureLocalAccountExistence();
        $this->ensureAdobeConnectAccountExistence();
    }
    
    private function readAdobeConnectUserData(): void
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        $result = $ilDB->queryF(
            'SELECT email,login,passwd,firstname,lastname FROM usr_data WHERE usr_id= %s',
            ['integer'],
            [$this->getId()]
        );
        
        while ($record = $ilDB->fetchAssoc($result)) {
            $this->email = $record['email'];
            $this->login = $record['login'];
            $this->pass = $record['passwd'];
            $this->first_name = $record['firstname'];
            $this->last_name = $record['lastname'];
        }
        
        $login_res = $ilDB->queryf(
            'SELECT xavc_login FROM rep_robj_xavc_users WHERE user_id = %s',
            ['integer'],
            array($this->getId())
        );
        
        while ($row = $ilDB->fetchAssoc($login_res)) {
            $this->xavc_login = $row['xavc_login'];
            $this->ensureAccountExistence();
        }
    }
    
    private function ensureLocalAccountExistence(): void
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        // generate valid xavc_login_name with assignment_mode-setting
        $expected_login_name = self::generateXavcLoginName($this->getId());
        
        //check if is valid xavc_login
        if (!$this->xavc_login && $expected_login_name) { //|| $this->xavc_login != $expected_login_name)
            $this->xavc_login = $expected_login_name;

            $ilDB->replace(
                'rep_robj_xavc_users',
                [
                    'user_id' => ['integer', $this->getId()]
                ],
                [
                    'xavc_login' => ['text', $this->xavc_login]
                ]
            );
        }
    }
    
    private function ensureAdobeConnectAccountExistence(): void
    {
        // check if this login exists at ac-server
        $search = $this->searchUser($this->xavc_login);
        
        // if does not exist, create account at ac-server
        if (!$search) {
            $this->addUser();
        }
    }
    
    public function ensureUserFolderExistence($a_xavc_login = '')
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();
        
        $instance = ilAdobeConnectServer::_getInstance();
        $login = $instance->getLogin();
        $pass = $instance->getPasswd();
        
        if ((string) ilAdobeConnectServer::getSetting('use_user_folders') == '1') {
            if ($a_xavc_login) {
                $xavc_login = $a_xavc_login;
            } else {
                $xavc_login = $this->getXAVCLogin();
            }
            
            if ($session != null && $xmlAPI->login($login, $pass, $session)) {
                $folder_id = $xmlAPI->lookupUserFolderId($xavc_login, $session);
                
                if ($folder_id == null) {
                    $folder_id = $xmlAPI->createUserFolder($xavc_login, $session);
                }
                
                return $folder_id;
            }
        } else {
            if ($session != null && $xmlAPI->login($login, $pass, $session)) {
                $folder_id = $xmlAPI->getShortcuts('my-meetings', $session);
                if (!$folder_id) {
                    $folder_id = $xmlAPI->getShortcuts('meetings', $session);
                }
                return $folder_id;
            } else {
                return null;
            }
        }
    }
    
    /**
     *  Returns user id
     */
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    /**
     *  Returns user login
     */
    public function getLogin(): string
    {
        return $this->login;
    }
    
    /**
     *  Returns user password
     */
    public function getPass(): string
    {
        return $this->pass;
    }
    
    public function getFirstName(): string
    {
        return $this->first_name;
    }
    
    public function getLastName(): string
    {
        return $this->last_name;
    }
    
    public function setXAVCLogin($a_xavc_login): void
    {
        $this->xavc_login = $a_xavc_login;
    }
    
    public function getXAVCLogin(): string
    {
        return $this->xavc_login;
    }
    
    /**
     *  Adds user to the Adobe Connect server
     */
    public function addUser(): void
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();
        
        $instance = ilAdobeConnectServer::_getInstance();
        $login = $instance->getLogin();
        $pass = $instance->getPasswd();
        
        $user_pass = $this->generatePass();
        
        if ($session != null && $xmlAPI->login($login, $pass, $session)) {
            $xmlAPI->addUser(
                $this->getXAVCLogin(),
                $this->email,
                $user_pass,
                $this->first_name,
                $this->last_name,
                $session
            );
            $xmlAPI->logout($session);
        }
    }
    
    /**Search user on the Adobe Connect server
     * @return bool|string
     */
    public function searchUser($a_xavc_login = '')
    {
        if ($a_xavc_login) {
            $xavc_login = $a_xavc_login;
        } else {
            $xavc_login = $this->getXAVCLogin();
        }
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();
        
        $instance = ilAdobeConnectServer::_getInstance();
        $login = $instance->getLogin();
        $pass = $instance->getPasswd();
        
        if ($session != null && $xmlAPI->login($login, $pass, $session)) {
            $search = $xmlAPI->searchUser($xavc_login, $session);
            $xmlAPI->logout($session);
            return $search;
        }
    }
    
    /**
     *  Log in user on the Adobe Connect server
     * @return string     Returns NULL if user doesn't exist on the server
     */
    public function loginUser()
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $session = $xmlAPI->getBreezeSession();
        
        return $xmlAPI->externalLogin($this->getXAVCLogin(), null, $session);
    }
    
    /**
     *  Log out user on the Adobe Connect server
     */
    public function logoutUser(string $session): void
    {
        $xmlAPI = ilXMLApiFactory::getApiByAuthMode();
        $xmlAPI->logout($session);
    }
    
    /**
     *  Generates a new password
     */
    private function generatePass(): string
    {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        mt_srand(time() * 1000000);
        
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $key = mt_rand(0, strlen($caracteres) - 1);
            $password = $password . $caracteres[$key];
        }
        
        return $password;
    }
    
    /** Generates the login name for a user depending on assignment_mode setting
     */
    public static function generateXavcLoginName(int $user_id): string
    {
        // set default when there is no setting set: assign_user_email
        $assignment_mode = ilAdobeConnectServer::getSetting('user_assignment_mode')
            ? ilAdobeConnectServer::getSetting('user_assignment_mode')
            : 'assign_user_email';
        
        switch ($assignment_mode) {
            case 'assign_user_email':
                $xavc_login = IL_INST_ID . '_' . $user_id . '_' . ilObjUser::_lookupEmail((int)$user_id);
                break;
            
            case 'assign_ilias_login':
                $xavc_login = IL_INST_ID . '_' . $user_id . '_' . ilObjUser::_lookupLogin((int)$user_id);
                break;
            
            case 'assign_dfn_email':
            case 'assign_breezeSession':
                $xavc_login = ilObjUser::_lookupEmail((int)$user_id);
                break;
        }
        
        return (string) $xavc_login;
    }
}
