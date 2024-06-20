<?php

// class should be renamed to ilAdobeConnectSettings
class ilAdobeConnectServer
{
    // User-Assignment-Mode
    public const ASSIGN_USER_EMAIL = 'assign_user_email';
    public const ASSIGN_USER_ILIAS = 'assign_ilias_login';
    public const ASSIGN_USER_DFN_EMAIL = 'assign_dfn_email';
    
    // Auth-Mode
    public const AUTH_MODE_PASSWORD = 'auth_mode_password';
    public const AUTH_MODE_HEADER = 'auth_mode_header';
    public const AUTH_MODE_DFN = 'auth_mode_dfn';
    
    private string $server = '';
    private ?string $port = null;
    private string $presentation_server = '';
    private ?string $presentation_port = null;
    private string $login = '';
    /**
     *  Admin pass. Must belongs to the next groups: hosts, authors and administrators
     */
    private string $pass = '';
    /**
     *  Maximum number of concurrent sessions on the server
     */
    private string $num_max_vc = '0';
    private bool $public_vc_enabled = false;
    
    private ?int $num_public_vc = null;
    
    private int $buffer_seconds_before = 1800; // 30 minutes
    private int $buffer_seconds_after = 1800; // 30 minutes
    
    private int $schedule_lead_time = 172800; // 48 hours
    
    private ?string $user_assignment_mode = null;
    
    /**
     * @var string $auth_mode Authentification mode
     */
    private ?string $auth_mode = null;
    
    /**
     * @var string $x_user_id Header-Variable needed for HTTP-Header-Auth.
     */
    private ?string $x_user_id = null;
    
    private bool $html_client = false;
    private string $api_version = '0';
    
    /**
     *  Singleton instance
     * @var ilAdobeConnectServer
     */
    private static $instance;
    
    private function __construct()
    {
    }
    
    public static function _getInstance(): ilAdobeConnectServer
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
            self::$instance->doRead();
        }
        
        return self::$instance;
    }
    
    public function doRead(): void
    {
        $this->server = self::getSetting('server');
        $this->port = self::getSetting('port');
        $this->login = self::getSetting('login');
        $this->pass = self::getSetting('password');
        $this->presentation_server = self::getSetting('presentation_server');
        $this->presentation_port = self::getSetting('presentation_port');
        
        $this->num_max_vc = self::getSetting('num_max_vc');
        
        $this->x_user_id = self::getSetting('x_user_id');
        $this->user_assignment_mode = self::getSetting('user_assignment_mode');
        $this->auth_mode = self::getSetting('auth_mode');
        
        $this->html_client = self::getSetting('html_client');
        $this->api_version = self::getSetting('api_version');
    }
    
    public function commitSettings(): void
    {
        if (self::$instance instanceof self) {
            self::$instance->doRead();
        }
    }
    
    public function setServer(string $a_adobe_server): void
    {
        $this->server = $a_adobe_server;
    }
    
    public function getServer(): string
    {
        return $this->server;
    }
    
    public function setPort(string $a_port): void
    {
        $this->port = $a_port;
    }
    
    public function getPort(): ?string
    {
        return $this->port;
    }
    
    public function setPresentationServer(string $a_adobe_server): void
    {
        $this->presentation_server = $a_adobe_server;
    }
    
    public function getPresentationServer(): string
    {
        return $this->presentation_server;
    }
    
    public function setPresentationPort(string $a_port): void
    {
        $this->presentation_port = $a_port;
    }
    
    public function getPresentationPort(): ?string
    {
        return $this->presentation_port;
    }
    
    public function setLogin(string $a_login): void
    {
        $this->login = $a_login;
    }
    
    public function getLogin(): string
    {
        return $this->login;
    }
    
    public function setPasswd(string $a_password): void
    {
        $this->pass = $a_password;
    }
    
    public function getPasswd(): string
    {
        return $this->pass;
    }
    
    /**
     *  Sets the number of max. VirtualClassrooms running at the server.
     */
    public function setNumMaxVirtualClassrooms($a_num_max_vc): void
    {
        $this->num_max_vc = (string) $a_num_max_vc;
    }
    
    public function getNumMaxVirtualClassrooms(): string
    {
        return $this->num_max_vc;
    }
    
    public function setPublicVcEnabled($a_public_vc_enabled): void
    {
        $this->public_vc_enabled = (bool) $a_public_vc_enabled;
    }
    
    public function getPublicVcEnabled(): bool
    {
        return $this->public_vc_enabled;
    }
    
    public function setNumPublicVc($a_num_public_vc): void
    {
        $this->num_public_vc = (int) $a_num_public_vc;
    }
    
    public function getNumPublicVc(): int
    {
        return (int) $this->num_public_vc;
    }
    
    public function getBufferBefore(): int
    {
        return $this->buffer_seconds_before;
    }
    
    public function getBufferAfter(): int
    {
        return $this->buffer_seconds_after;
    }
    
    public function getScheduleLeadTime(): int
    {
        $tmp = self::getSetting('schedule_lead_time', '');
        if ($tmp !== '') {
            $this->schedule_lead_time = (int) $tmp;
        }
        return $this->schedule_lead_time;
    }
    
    public function setScheduleLeadTime($time): void
    {
        $this->schedule_lead_time = (int) $time;
        $this->setSetting('schedule_lead_time', (string) $time);
    }
    
    public function setAuthMode($auth_mode): void
    {
        $this->auth_mode = (string) $auth_mode;
    }
    
    public function getAuthMode(): ?string
    {
        return $this->auth_mode;
    }
    
    public function setXUserId($x_user_id): void
    {
        $this->x_user_id = $x_user_id;
    }
    
    public function getXUserId(): ?string
    {
        return $this->x_user_id;
    }
    
    public function getApiVersion(): string
    {
        return $this->api_version;
    }
    
    public function setApiVersion(string $api_version = '0'): void
    {
        $this->api_version = $api_version;
    }
    
    
    public function isHtmlClientEnabled(): bool
    {
        return $this->html_client;
    }
    
    public function setUseHtmlClient(bool $html_client): void
    {
        $this->html_client = $html_client;
    }
    
    public function setUserAssignmentMode($user_assignment_mode): void
    {
        $this->user_assignment_mode = $user_assignment_mode;
    }
    
    public function getUserAssignmentMode(): ?string
    {
        return $this->user_assignment_mode;
    }
    
    public static function getSetting(string $a_keyword, string $default_value = ''): string
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        if ($ilDB->tableExists('rep_robj_xavc_settings')) {
            $res = $ilDB->queryF(
                'SELECT value FROM rep_robj_xavc_settings WHERE keyword = %s',
                ['text'],
                [$a_keyword]
            );
            
            if ($row = $ilDB->fetchAssoc($res)) {
                return (string) $row['value'];
            }
        }
        
        return $default_value;
    }
    
    public static function setSetting(string $a_keyword, string $a_value): void
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        if ($ilDB->tableExists('rep_robj_xavc_settings')) {
            $ilDB->manipulateF(
                'DELETE FROM rep_robj_xavc_settings WHERE keyword = %s',
                ['text'],
                [$a_keyword]
            );
            
            $ilDB->insert('rep_robj_xavc_settings', [
                'keyword' => ['text', $a_keyword],
                'value' => ['text', $a_value]
            ]);
        }
    }
    
    public static function getPresentationUrl(bool $api_call = false): string
    {
        if ($api_call) {
            $server = self::getSetting('server');
            $port = self::getSetting('port');
        } else {
            $server = self::getSetting('presentation_server');
            $port = self::getSetting('presentation_port');
        }
        
        if (!$port || $port == '8080') {
            return $server;
        } else {
            return $server . ':' . $port;
        }
    }
    
    public static function getRoleMap(): array
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        $keywords = ['crs_owner', 'crs_admin', 'crs_tutor', 'crs_member', 'grp_owner', 'grp_admin', 'grp_member'];
        
        $res = $ilDB->query('SELECT * FROM rep_robj_xavc_settings WHERE ' .
            $ilDB->in('keyword', $keywords, false, 'text'));
        
        $map = [];
        while ($row = $ilDB->fetchAssoc($res)) {
            $map[$row['keyword']] = $row['value'];
        }
        
        // Fix for: http://www.ilias.de/mantis/view.php?id=12379
        $map['crs_owner'] = 'host';
        $map['grp_owner'] = 'host';
        
        return $map;
    }
}
