<?php

class ilAdobeConnectXMLAPI
{
    private ilLanguage $lng;
    private ilLogger $log;
    private ilObjUser $user;
    private ilGlobalTemplateInterface $tpl;
    protected string $server;
    protected string $port;
    protected ?string $x_user_id = null;
    protected ?ilAdobeConnectServer $adcInfo;
    protected static ?string $breeze_session = null;
    protected string $auth_mode = '';
    protected static array $loginsession_cache = [];

    public function getAuthMode(): string
    {
        return $this->auth_mode;
    }

    protected static array $scocontent_cache = array();

    public function __construct()
    {
        global $DIC;

        $this->adcInfo = ilAdobeConnectServer::_getInstance();
        $this->server = $this->adcInfo->getServer();
        $this->port = $this->adcInfo->getPort();
        $this->x_user_id = $this->adcInfo->getXUserId();
        $this->auth_mode = $this->adcInfo->getAuthMode();
        $this->api_version = $this->adcInfo->getApiVersion();
        $this->proxy();

        $this->lng = $DIC->language();
        $this->log = $DIC->logger()->root();
        $this->user = $DIC->user();
        $this->tpl = $DIC->ui()->mainTemplate();
    }

    public function getXUserId(): ?string
    {
        return $this->x_user_id;
    }

    public function getAdminSession(): ?string
    {
        $session = $this->getBreezeSession();

        if (!$session) {
            /**
             * @todo introduce exception
             */
            return null;
        }

        $success = $this->login($this->adcInfo->getLogin(), $this->adcInfo->getPasswd(), $session);

        if ($success) {
            return $session;
        } else {
            /**
             * @todo introduce exception
             */
            return null;
        }
    }

    /**
     *  Logs in user on the Adobe Connect server. The session id is caches until the
     *  logout function is called with the session id.
     * @param string $user    Adobe Connect user login
     * @param string $pass    Adobe Connect user password
     * @param string $session Session id
     * @return bool          return true if everything is ok
     */
    public function login(string $user, string $pass, string $session): bool
    {
        if (isset(self::$loginsession_cache[$session]) && self::$loginsession_cache[$session]) {
            return self::$loginsession_cache[$session];
        }

        if (isset($user, $pass, $session)) {
            $url = $this->getApiUrl([
                'action' => 'login',
                'login' => $user,
                'password' => $pass,
                'session' => $session
            ]);

            $context = ([
                'http' => ['timeout' => 4],
                'https' => ['timeout' => 4]
            ]);

            $ctx = $this->proxy($context);
            $xml_string = file_get_contents($url, false, $ctx);
            $xml = simplexml_load_string($xml_string);

            if ($xml->status['code'] == 'ok') {
                self::$loginsession_cache[$session] = true;
                return true;
            } else {
                unset(self::$loginsession_cache[$session]);
                $this->log->write('AdobeConnect login Request: ' . $url);
                if ($xml) {
                    $this->log->write('AdobeConnect login Response: ' . $xml->asXML());
                }
                $this->log->write('AdobeConnect login failed: ' . $user);
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('login_failed'));

                return false;
            }
        } else {
            unset(self::$loginsession_cache[$session]);
            $this->log->write('AdobeConnect login failed due to missing login credentials ...');
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_wrong_login'));
            return false;
        }
    }

    /**
     * Changes the password of the user, identified by username
     */
    public function changeUserPassword(string $username, string $newPassword): bool
    {
        $user_id = $this->searchUser($username, $this->getAdminSession());

        if ($user_id) {
            $url = $this->getApiUrl([
                'action' => 'user-update-pwd',
                'session' => $this->getAdminSession(),
                'password' => $newPassword,
                'password-verify' => $newPassword,
                'user-id' => $user_id
            ]);
            $xml = simplexml_load_file($url);

            return $xml instanceof SimpleXMLElement && $xml->status['code'] == 'ok';
        }
        return false;
    }

    /**
     *  Logs in user on Adobe Connect server using external authentication
     * @ilObjUser $ilUser
     * @param null $user
     * @param null $pass
     * @param null $session
     * @return bool|mixed|null|String
     */
    public function externalLogin($user = null, $pass = null, $session = null)
    {
        if ($this->adcInfo->getAuthMode() == ilAdobeConnectServer::AUTH_MODE_HEADER) {
            $auth_result = $this->useHTTPHeaderAuthentification($user);
        } else // default: auth_mode_password
        {
            $auth_result = $this->usePasswordAuthentication($user);
        }
        return $auth_result;
    }

    /**
     *  Logs out user on the Adobe Connect server
     * @param string $session Session id
     * @return bool          return true if everything is ok
     */
    public function logout(string $session): bool
    {
        $url = $this->getApiUrl([
            'action' => 'logout',
            'session' => $session
        ]);

        $xml = simplexml_load_file($url);

        if ($session == self::$breeze_session) {
            self::$breeze_session = null;
        }

        unset(self::$loginsession_cache[$session]);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect logout Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect logout Response: ' . $xml->asXML());
            }

            return false;
        }
    }

    public function getApiVersion(): ?string
    {
        $url = $this->getApiUrl(['action' => 'common-info']);

        $context = [
            'http' => ['timeout' => 4],
            'https' => ['timeout' => 4]
        ];
        $ctx = $this->proxy($context);
        $xml_string = file_get_contents($url, false, $ctx);
        $xml = simplexml_load_string($xml_string);

        if ($xml && (string) $xml->common->version) {
            $api_version = (string) $xml->common->version;
            return $api_version;
        } else {
            $this->log->write('AdobeConnect getApiVersion Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getApiVersion Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    public function getBreezeSession(bool $useCache = true): ?string
    {
        if (null !== self::$breeze_session && $useCache) {
            return self::$breeze_session;
        }

        $url = $this->getApiUrl(['action' => 'common-info']);

        $context = [
            'http' => ['timeout' => 4],
            'https' => ['timeout' => 4]
        ];
        $ctx = $this->proxy($context);
        $xml_string = file_get_contents($url, false, $ctx);
        $xml = simplexml_load_string($xml_string);

        if ($xml && $xml->common->cookie != "") {
            $session = (string) $xml->common->cookie;
            if (!$useCache) {
                return $session;
            }

            self::$breeze_session = $session;
            return self::$breeze_session;
        } else {
            $this->log->write('AdobeConnect getBreezeSession Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getBreezeSession Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Returns the id associated with the object type parameter
     * @param string $type    Object type
     * @param string $session Session id
     * @return string             Object id
     */
    public function getShortcuts(string $type, string $session): ?string
    {
        $url = $this->getApiUrl([
            'action' => 'sco-shortcuts',
            'session' => $session
        ]);

        $xml = simplexml_load_file($url);
        if (
            $xml instanceof SimpleXMLElement &&
            'ok' == (string) $xml->status['code']
        ) {
            foreach ($xml->shortcuts->sco as $sco) {
                if ($sco['type'] == $type) {
                    $id = (string) $sco['sco-id'];
                }
            }
        }
        return ($id == '' ? null : $id);
    }

    /**
     *  Returns the folder_id
     * @param int    $scoId   sco-id
     * @param string $session Session id
     * @return string             Object id
     */
    public function getFolderId($scoId, string $session): string
    {
        $url = $this->getApiUrl([
            'action' => 'sco-info',
            'session' => $session,
            'sco-id' => (string) $scoId
        ]);

        $xml = simplexml_load_file($url);
        $id = $xml->sco['folder-id'];

        return (string) ($id == '' ? null : $id);
    }

    /**
     *  Adds a new meeting on the Adobe Connect server
     * @param string $name        Meeting name
     * @param string $description Meeting description
     * @param string $start_date  Meeting start date
     * @param string $start_time  Meeting start time
     * @param string $end_date    Meeting end date
     * @param string $end_time    Meeting end time
     * @param string $folder_id   Sco-id of the user's meetings folder
     * @param int    $source_sco_id
     * @param string $ac_language
     * @param string $session     Session id
     * @return array                  Meeting sco-id AND Meeting url-path; NULL if something is wrong
     * @throws ilException
     */
    public function addMeeting(
        $name,
        $description,
        $start_date,
        $start_time,
        $end_date,
        $end_time,
        $folder_id,
        $session,
        $source_sco_id = 0,
        $ac_language = 'de',
        $html_client = false
    ) {
        $api_parameter = [
            'action' => 'sco-update',
            'type' => 'meeting',
            'name' => $name,
            'folder-id' => $folder_id,
            'description' => $description,
            'date-begin' => $start_date . "T" . $start_time,
            'date-end' => $end_date . "T" . $end_time,
            'session' => $session,
            'lang' => $ac_language
        ];

        if ($source_sco_id > 0) {
            $api_parameter['source-sco-id'] = (string) $source_sco_id;
        }

        $url = $this->getApiUrl($api_parameter);
        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            if ($html_client) {
                $html_client_parameter =
                    [
                        'action' => 'acl-field-update',
                        'field-id' => 'meetingHTMLLaunch',
                        'value' => 'true',
                        'acl-id' => (string) $xml->sco['sco-id'],
                        'session' => $session
                    ];

                $result = $this->updateACLField($html_client_parameter);
            }
            return [
                'meeting_id' => (string) $xml->sco['sco-id'],
                'meeting_url' => (string) $xml->sco->{'url-path'}
            ];
        } else {
            $this->log->write('AdobeConnect addMeeting Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect addMeeting Response: ' . $xml->asXML());
                foreach ($xml->status->{'invalid'}->attributes() as $key => $value) {
                    if ($key == 'subcode' && $value == 'duplicate') {
                        throw new ilException('err_duplicate_meeting');
                        return null;
                    }
                }
            }

            return null;
        }
    }

    /**
     * @param array $api_parameter
     * @return bool
     */
    public function updateACLField(array $api_parameter): bool
    {
        $url = $this->getApiUrl($api_parameter);
        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        }

        $this->log->write('AdobeConnect updateACLField Request failed: ' . $url);
        return false;
    }

    /**
     *  Updates an existing meeting
     * @param string $meeting_id
     * @param string $name
     * @param string $description
     * @param string $start_date
     * @param string $start_time
     * @param string $end_date
     * @param string $end_time
     * @param string $session
     * @return bool              Returns true if everything is ok
     */
    public function updateMeeting(
        $meeting_id,
        $name,
        $description,
        $start_date,
        $start_time,
        $end_date,
        $end_time,
        $session,
        $ac_language,
        $html_client = false
    ): bool {
        $api_parameter = [
            'action' => 'sco-update',
            'sco-id' => (string) $meeting_id,
            'name' => (string) $name,
            'description' => (string) $description,
            'date-begin' => $start_date . "T" . $start_time,
            'date-end' => $end_date . "T" . $end_time,
            'session' => (string) $session,
            'lang' => (string) $ac_language
        ];

        if ($html_client) {
            $html_client_parameter =
                [
                    'action' => 'acl-field-update',
                    'field-id' => 'meetingHTMLLaunch',
                    'value' => 'true',
                    'acl-id' => (string) $meeting_id,
                    'session' => (string) $session
                ];
            $result = $this->updateACLField($html_client_parameter);
        }

        $url = $this->getApiUrl($api_parameter);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == 'ok') {
            return true;
        } else {
            $this->log->write('AdobeConnect updateMeeting Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect updateMeeting Response: ' . $xml->asXML());
            }
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('update_meeting_failed'));
            return false;
        }
    }

    /**
     *  Deletes an existing meeting
     * @param string $sco_id
     * @param string $session
     * @return boolean          Returns true if everything is ok
     */
    public function deleteMeeting($sco_id, $session): bool
    {
        $url = $this->getApiUrl([
            'action' => 'sco-delete',
            'sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);

        //'no-data' means current  sco does not exists or sco is already deleted
        if (($xml->status['code'] == 'ok') || ($xml->status['code'] == 'no-data')) {
            return true;
        } else {
            $this->log->write('AdobeConnect deleteMeeting Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect deleteMeeting Response: ' . $xml->asXML());
            }
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('delete_meeting_failed'));
            return false;
        }
    }

    /**
     *  Sets meeting to private
     *    Only registered users and participants can enter (no guests!!)
     * @param integer $a_meeting_id Meeting id
     * @return boolean          Returns true if everything is ok
     */
    public function setMeetingPrivate($a_meeting_id, $session): bool
    {
        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $a_meeting_id,
            'principal-id' => 'public-access',
            'permission-id' => 'denied',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect setMeetingPrivate Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect setMeetingPrivate Response: ' . $xml->asXML());
            }

            return false;
        }
    }

    /**
     *    Everyone can enter!!!
     * @param $a_meeting_id $meeting_id
     * @return bool
     */
    public function setMeetingPublic($a_meeting_id, $session): bool
    {
        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $a_meeting_id,
            'principal-id' => 'public-access',
            'permission-id' => 'view-hidden',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect setMeetingPublic Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect setMeetingPublic Response: ' . $xml->asXML());
            }

            return false;
        }
    }

    /**
     * Only registered users and accepted guests can enter (default)
     * @param int $a_meeting_id
     * @return bool
     */
    public function setMeetingProtected($a_meeting_id, $session): bool
    {
        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $a_meeting_id,
            'principal-id' => 'public-access',
            'permission-id' => 'remove',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect setMeetingProtected Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect setMeetingProtected Response: ' . $xml->asXML());
            }

            return false;
        }
    }

    public function updatePermission($a_meeting_id, $session, $a_permission_id): bool
    {
        $url = $this->getApiUrl(array(
            'action' => 'permissions-update',
            'acl-id' => (string) $a_meeting_id,
            'principal-id' => 'public-access',
            'permission-id' => (string) $a_permission_id,
            'session' => (string) $session
        ));

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect updatePermission Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect updatePermission Response: ' . $xml->asXML());
            }
            return false;
        }
    }

    /**
     *  Gets meeting or content URL
     * @param string $sco_id    Meeting or content id
     * @param string $folder_id Parent folder id
     * @param string $session   Session id
     * @return string                 Meeting or content URL, or NULL if something is wrong
     */
    public function getURL(string $sco_id, string $folder_id, string $session): string
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);
        $xml = $this->getCachedSessionCall($url);
        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'url-path'};
        }

        $this->log->write('AdobeConnect getURL Request: ' . $url);
        if ($xml) {
            $this->log->write('AdobeConnect getURL Response: ' . $xml->asXML());
        }
        return '';
    }

    public function getScoInfo($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl(array(
            'action' => 'sco-info',
            'sco-id' => (string) $sco_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ));

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            $sco_data['date_begin'] = $xml->sco->{'date-begin'};
            $sco_data['date_created'] = $xml->sco->{'date-created'};
            $sco_data['date_end'] = $xml->sco->{'date-end'};
            $sco_data['date_modified'] = $xml->sco->{'date-modified'};
            $sco_data['meetingHTMLLaunch'] = $xml->sco->{'meetingHTMLLaunch'};
            return $sco_data;
        } else {
            $this->log->write('AdobeConnect getStartDate Request: ' . $url);
            $this->log->write('AdobeConnect getStartDate Response: ' . $xml->asXML());

            return null;
        }
    }

    /**
     *  Gets meeting start date
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting start date, or NULL if something is wrong
     */
    public function getStartDate($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl(array(
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ));

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'date-begin'};
        } else {
            $this->log->write('AdobeConnect getStartDate Request: ' . $url);
            $this->log->write('AdobeConnect getStartDate Response: ' . $xml->asXML());

            return null;
        }
    }

    public function isActiveSco($session, $sco_id)
    {
        $url = $this->getApiUrl([
            'action' => 'report-active-meetings',
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);
        $counter = 0;
        $result = [];
        if (is_array($xml->{'report-active-meetings'}->sco)) {
            foreach ($xml->{'report-active-meetings'}->sco as $sco) {
                foreach ($sco->attributes() as $name => $attr) {
                    $result[$counter][(string) $name] = (string) $attr;
                }
                $counter++;
            }

            return $result;
        }
        return 0;
    }

    public function getActiveScos($session)
    {
        $url = $this->getApiUrl([
            'action' => 'report-active-meetings',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);
        $counter = 0;
        $result = [];
        if ($xml->{'report-active-meetings'}->sco) {
            foreach ($xml->{'report-active-meetings'}->sco as $sco) {
                foreach ($sco->attributes() as $name => $attr) {
                    //if($sco['active-participants'] >= 1)
                    //{
                    $result[$counter][(string) $name] = (string) $attr;
                    //}
                }

                $result[$counter]['name'] = (string) $sco->name;
                $result[$counter]['sco_url'] = (string) $sco->{'url-path'};

                $counter++;
            }

            return $result;
        }
        return 0;
    }

    public function getAllScos($session)
    {
        $url = $this->getApiUrl([
            'action' => 'report-bulk-objects',
            'filter-type' => 'meeting',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);
        $result = [];

        if ($xml->{'report-bulk-objects'}) {
            foreach ($xml->{'report-bulk-objects'}->row as $meeting) {
                if ($meeting->{'date-end'} != '') {
                    $result[(string) $meeting['sco-id']]['sco_id'] = (string) $meeting['sco-id'];
                    $result[(string) $meeting['sco-id']]['sco_name'] = (string) $meeting->{'name'};
                    $result[(string) $meeting['sco-id']]['description'] = (string) $meeting->{'description'};
                    $result[(string) $meeting['sco-id']]['sco_url'] = (string) $meeting->{'url'};
                    $result[(string) $meeting['sco-id']]['date_end'] = (string) $meeting->{'date-end'};
                }
            }

            return $result;
        }
        return 0;
    }

    /**
     *  Gets meeting end date
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                  Meeting start date, or NULL if something is wrong
     */
    public function getEndDate($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);
        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'date-end'};
        } else {
            $this->log->write('AdobeConnect getStartDate Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getStartDate Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Gets meeting or content name
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting or content name, or NULL if something is wrong
     */
    public function getName($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'name'};
        } else {
            $this->log->write('AdobeConnect getName Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getName Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Gets meeting or content description
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting or content description, or NULL if something is wrong
     */
    public function getDescription($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'description'};
        } else {
            $this->log->write('AdobeConnect getDescription Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDescription Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Gets meeting or content creation date
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting or content creation date, or NULL if something is wrong
     */
    public function getDateCreated($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'date-created'};
        } else {
            $this->log->write('AdobeConnect getDateCreated Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDateCreated Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Gets meeting or content modification date
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting or content modification date, or NULL if something is wrong
     */
    public function getDateModified($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->{'date-modified'};
        } else {
            $this->log->write('AdobeConnect getDateModified Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDateModified Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Gets content duration
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Content duration, or NULL if something is wrong
     */
    public function getDuration($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->scos->sco->duration;
        } else {
            $this->log->write('AdobeConnect getDuration Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDuration Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Returns all identifiers of content associated with the meeting
     * @param string $meeting_id
     * @param string $session
     * @return array
     */
    public function getContentIds($meeting_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $meeting_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            $ids = [];
            $i = 0;
            $contents = [];
            $records = [];

            foreach ($xml->scos->sco as $sco) {
                if ($sco['source-sco-id'] == ""
                    && $sco['duration'] == ""
                ) {
                    $contents[$i] = (string) $sco['sco-id'];
                    $i++;
                } else {
                    if ($sco['source-sco-id'] == ""
                        && $sco['duration'] != ""
                    ) {
                        $records[$i] = (string) $sco['sco-id'];
                        $i++;
                    }
                }
            }
            return array_merge($contents, $records);
        } else {
            $this->log->write('AdobeConnect getDuration Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDuration Response: ' . $xml->asXML());
            }
            return null;
        }
    }

    /**
     *  Returns all identifiers of record associated with the meeting
     * @param string $meeting_id
     * @param string $session
     * @return array
     */
    public function getRecordIds($meeting_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $meeting_id,
            'session' => (string) $session
        ]);
        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == "ok") {
            $ids = [];
            $i = 0;
            foreach ($xml->scos->sco as $sco) {
                if ($sco['source-sco-id'] == "" && $sco['duration'] != "") {
                    $ids[$i] = (string) $sco['sco-id'];
                    $i++;
                }
            }
            return $ids;
        }
        return null;
    }

    /**
     *  Adds a content associated with the meeting
     * @param string $folder_id
     * @param string $title
     * @param string $description
     * @param string $session
     * @return string
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function addContent($folder_id, $title, $description, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-update',
            'name' => (string) $title,
            'folder-id' => (string) $folder_id,
            'description' => (string) $description,
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);
        if ($xml instanceof SimpleXMLElement && $xml->status['code'] == 'ok') {
            $server = $this->server;
            if (substr($server, -1) == '/') {
                $server = substr($server, 0, -1);
            }
            return $server . "/api/xml?action=sco-upload&sco-id=" . (string) $xml->sco['sco-id'] . "&session=" . $session;
        } else {
            $this->log->write('AdobeConnect addContent Request: ' . $url);

            if ($xml instanceof SimpleXMLElement) {
                $this->log->write('AdobeConnect addContent Response: ' . $xml->asXML());

                if ($xml->status['code'] == 'invalid' &&
                    $xml->status->invalid['subcode'] == 'duplicate') {
                    throw new ilAdobeConnectDuplicateContentException('add_cnt_err_duplicate');
                }
            }

            return null;
        }
    }

    /**
     *  Updates a content
     * @param string $sco_id
     * @param string $title
     * @param string $description
     * @param string $session
     * @return boolean              Returns true if everything is ok
     * @throws ilAdobeConnectDuplicateContentException
     */
    public function updateContent($sco_id, $title, $description, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-update',
            'name' => (string) $title,
            'sco-id' => (string) $sco_id,
            'description' => (string) $description,
            'session' => (string) $session
        ]);
        $xml = simplexml_load_file($url);

        if ($xml instanceof SimpleXMLElement && $xml->status['code'] == 'ok') {
            return true;
        } else {
            $this->log->write('AdobeConnect updateContent Request: ' . $url);

            if ($xml instanceof SimpleXMLElement) {
                $this->log->write('AdobeConnect updateContent Response: ' . $xml->asXML());

                if ($xml->status['code'] == 'invalid' &&
                    $xml->status->invalid['subcode'] == 'duplicate') {
                    throw new ilAdobeConnectDuplicateContentException('add_cnt_err_duplicate');
                }
            }

            return false;
        }
    }

    /**
     *  Deletes a content
     * @param string $sco_id
     * @param string $session
     * @return boolean              Returns true if everything is ok
     */
    public function deleteContent($sco_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-delete',
            'sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return true;
        } else {
            $this->log->write('AdobeConnect deleteContent Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect deleteContent Response: ' . $xml->asXML());
            }
            return false;
        }
    }

    /**
     *  Upload a content on the Adobe Connect server
     * @param        $sco_id
     * @param string $session
     * @return string
     */
    public function uploadContent($sco_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-upload',
            'sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        return $url;
    }

    /**
     * @param string $login
     * @param string $session
     * @return bool|string
     */
    public function searchUser($login, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'principal-list',
            'filter-login' => (string) $login,
            'session' => (string) $session
        ]);
        $xml = simplexml_load_file($url);

        if ($xml instanceof \SimpleXMLElement && $xml->status['code'] == 'ok') {
            if (
                $xml->{'principal-list'} &&
                $xml->{'principal-list'}->{'principal'} &&
                (string) $xml->{'principal-list'}->{'principal'}->attributes()->{'principal-id'} != ""
            ) {
                return (string) $xml->{'principal-list'}->{'principal'}->attributes()->{'principal-id'};
            }
        }

        $this->log->write('AdobeConnect searchUser Request:  ' . $url);
        if ($xml) {
            $this->log->write('AdobeConnect searchUser Response: ' . $xml->asXML());
        }

        return false;
    }

    /**
     *  Adds a user to the Adobe Connect server
     * @param string $login
     * @param string $email
     * @param string $pass
     * @param string $first_name
     * @param string $last_name
     * @param string $session
     * @return bool          Returns true if everything is ok
     */
    public function addUser(
        string $login,
        string $email,
        string $pass,
        string $first_name,
        string $last_name,
        string $session
    ): bool {
        $url = $this->getApiUrl([
            'action' => 'principal-update',
            'login' => $login,
            'email' => $email,
            'password' => $pass,
            'first-name' => $first_name,
            'last-name' => $last_name,
            'type' => 'user',
            'has-children' => 0,
            'session' => $session
        ]);
        $this->log->write('addUser Url: ' . $url);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == 'ok') {
            return true;
        } else {
            $this->log->write('AdobeConnect addUser Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect addUser Response: ' . $xml->asXML());
            }

            return false;
        }
    }

    /**
     *  Return meetings hosts
     * @param string $meeting_id
     * @param string $session
     * @return array
     */
    public function getMeetingsParticipants(string $meeting_id, string $session): array
    {
        $result = [];

        if ($this->auth_mode == ilAdobeConnectServer::AUTH_MODE_HEADER) {
            $host = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-permission-id' => 'host'
            ]);

            $mini_host = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-permission-id' => 'mini-host'
            ]);

            $view = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-permission-id' => 'view'
            ]);

            $denied = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-permission-id' => 'denied'
            ]);
        } else {
            $host = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-type' => 'user',
                'filter-permission-id' => 'host'
            ]);

            $mini_host = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-type' => 'user',
                'filter-permission-id' => 'mini-host'
            ]);

            $view = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-type' => 'user',
                'filter-permission-id' => 'view'
            ]);

            $denied = $this->getApiUrl([
                'action' => 'permissions-info',
                'acl-id' => (string) $meeting_id,
                'session' => (string) $session,
                'filter-type' => 'user',
                'filter-permission-id' => 'denied'
            ]);
        }

        $xml_host = simplexml_load_file($host);
        foreach ($xml_host->permissions->principal as $user) {
            $result[(string) $user->login] = [
                'name' => (string) $user->name,
                'login' => (string) $user->login,
                'status' => 'host'
            ];
        }

        $xml_mini_host = simplexml_load_file($mini_host);
        foreach ($xml_mini_host->permissions->principal as $user) {
            $result[(string) $user->login] = [
                'name' => (string) $user->name,
                'login' => (string) $user->login,
                'status' => 'mini-host'
            ];
        }

        $xml_view = simplexml_load_file($view);
        foreach ($xml_view->permissions->principal as $user) {
            $result[(string) $user->login] = [
                'name' => (string) $user->name,
                'login' => (string) $user->login,
                'status' => 'view'
            ];
        }

        $xml_denied = simplexml_load_file($denied);
        foreach ($xml_denied->permissions->principal as $user) {
            $result[(string) $user->login] = [
                'name' => (string) $user->name,
                'login' => (string) $user->login,
                'status' => 'denied'
            ];
        }

        return $result;
    }

    /**
     *  Add a host to the meeting
     * @param string $meeting_id
     * @param string $login
     * @param string $session
     * @return bool                  Returns true if everything is ok
     */
    public function addMeetingParticipant($meeting_id, $login, $session): bool
    {
        $principal_id = (string) $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $meeting_id,
            'session' => (string) $session,
            'principal-id' => $principal_id,
            'permission-id' => 'view'
        ]);

        $xml = simplexml_load_file($url);

        return $xml->status['code'] == 'ok';
    }

    /**
     *  Add a host to the meeting
     * @param string $meeting_id
     * @param string $login
     * @param string $session
     * @return boolean                  Returns true if everything is ok
     */
    public function addMeetingHost($meeting_id, $login, $session)
    {
        $principal_id = (string) $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $meeting_id,
            'session' => (string) $session,
            'principal-id' => $principal_id,
            'permission-id' => 'host'
        ]);
        $xml = simplexml_load_file($url);

        return $xml->status['code'] == 'ok';
    }

    /**
     *  Add a moderator to the meeting
     * @param string $meeting_id
     * @param string $login
     * @param string $session
     * @return boolean                  Returns true if everything is ok
     */
    public function addMeetingModerator($meeting_id, $login, $session)
    {
        $principal_id = (string) $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $meeting_id,
            'session' => (string) $session,
            'principal-id' => $principal_id,
            'permission-id' => 'mini-host'
        ]);
        $xml = simplexml_load_file($url);

        return $xml->status['code'] == 'ok';
    }

    /**
     * @param $meeting_id
     * @param $login
     * @param $session
     * @param $permission
     * @return bool
     */
    public function updateMeetingParticipant($meeting_id, $login, $session, $permission)
    {
        $principal_id = $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'principal-id' => (string) $principal_id,
            'acl-id' => (string) $meeting_id,
            'session' => (string) $session,
            'permission-id' => (string) $permission
        ]);

        $ctx = $this->proxy([]);
        $result = file_get_contents($url, false, $ctx);
        $xml = simplexml_load_string($result);
        if ($xml->status['code'] == 'ok') {
            return true;
        }
        //		//deactivated for switch to avoid failure-message
        //		else
        //		{
        //			$this->log->write('AdobeConnect updateMeetingParticipant Request: '.$url);
        //			$this->log->write('AdobeConnect updateMeetingParticipant Response: '.$xml->asXML());
        //
        //		#	$this->tpl->setOnScreenMessage('failure', $lng->txt('add_user_failed'));
        //
        //			return false;
        //		}
    }

    /**
     *  Deletes a participant in the meeting
     * @param string $meeting_id
     * @param string $login
     * @param string $session
     * @return bool                  Returns true if everything is ok
     */
    public function deleteMeetingParticipant($meeting_id, $login, $session)
    {
        $principal_id = (string) $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-update',
            'acl-id' => (string) $meeting_id,
            'session' => (string) $session,
            'principal-id' => $principal_id,
            'permission-id' => 'remove'
        ]);
        $xml = simplexml_load_file($url);

        return $xml->status['code'] == 'ok';
    }

    /**
     *  Returns all meeting ids on the Adobe Connect server
     * @param string $session
     * @return array
     */
    public function getMeetingsId($session): array
    {
        $result = [];

        $url = $this->getApiUrl(array(
            'action' => 'report-bulk-objects',
            'filter-type' => 'meeting',
            'session' => $session
        ));
        $xml = simplexml_load_file($url);

        foreach ($xml->{'report-bulk-objects'}->row as $meeting) {
            if ($meeting->{'date-end'} != '') {
                $result[] = (string) $meeting['sco-id'];
            }
        }

        return $result;
    }

    /**
     *  Returns user id
     * @param string $login
     * @param string $session
     * @return string
     */
    public function getPrincipalId($login, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'principal-list',
            'filter-login' => $login,
            'session' => $session
        ]);
        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            return (string) $xml->{'principal-list'}->principal['principal-id'];
        } else {
            $this->log->write('AdobeConnect getPrincipalId Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getPrincipalId Response: ' . $xml->asXML());
            }

            return null;
        }
    }

    /**
     *  Check whether a user is host in a meeting.
     * @param string $login
     * @param string $meeting
     * @param string $session
     * @return bool
     */
    public function isParticipant($login, $meeting, $session)
    {
        $p_id = $this->getPrincipalId($login, $session);

        $url = $this->getApiUrl([
            'action' => 'permissions-info',
            'acl-id' => $meeting,
            'filter-principal-id' => $p_id,
            'session' => $session
        ]);
        $xml = simplexml_load_file($url);

        if (in_array((string) $xml->permissions->principal['permission-id'], ['host', 'mini-host', 'view'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $meeting
     * @param $session
     * @return string
     */
    public function getPermissionId($meeting, $session): string
    {
        $url2 = $this->getApiUrl([
            'action' => 'permissions-info',
            'acl-id' => $meeting,
            'principal-id' => 'public-access',
            'session' => $session
        ]);

        $xml2 = simplexml_load_file($url2);
        $permission_id = (string) $xml2->permission['permission-id'];

        // ADOBE CONNECT API BUG!!  if access-level is "PROTECTED" the api does not return a proper permission_id. it returns an empty string
        if ($permission_id === '') {
            return 'remove';
        } else {
            return $permission_id;
        }
    }

    /**
     * @param $a_sco_id
     * @param $session
     * @return array
     */
    public function getActiveUsers($a_sco_id, $session): array
    {
        $url = $this->getApiUrl([
            'action' => 'report-bulk-consolidated-transactions',
            'filter-type' => 'meeting',
            'session' => $session,
            'filter-sco-id' => $a_sco_id
        ]);

        $xml = simplexml_load_file($url);

        if ($xml->status['code'] == "ok") {
            foreach ($xml->{'report-bulk-consolidated-transactions'}->row as $meeting) {
                if ($meeting->{'status'} == 'in-progress') {
                    $result[] = (string) $meeting->{'user-name'};
                }
            }
            return $result;
        } else {
            $this->log->write('AdobeConnect getActiveUsers Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getActiveUsers Response: ' . $xml->asXML());
            }
        }

        return [];
    }

    /**
     * Generates an url encoded string for api calls
     * @param array $params Query parameters passed as an array structure
     * @return    string
     * @access    private
     */
    protected function getApiUrl(array $params): string
    {
        $server = $this->server;
        if (substr($server, -1) == '/') {
            $server = substr($server, 0, -1);
        }

        if (!$this->port || $this->port == '8080') {
            $api_url = $server;
        } else {
            $api_url = $server . ':' . $this->port;
        }

        $api_url .= '/api/xml?' . http_build_query($params);

        return $api_url;
    }

    /**
     * Performs a cached call based on a static cache.
     * @param string $url
     * @return SimpleXMLElement
     */
    protected function getCachedSessionCall($url)
    {
        $hash = $url;
        if (isset(self::$scocontent_cache[$hash])) {
            return self::$scocontent_cache[$hash];
        }

        $xml = simplexml_load_file($url);

        self::$scocontent_cache[$hash] = $xml;

        return $xml;
    }

    /**
     * @param $user
     * @return bool|mixed
     */
    private function useHTTPHeaderAuthentification($user)
    {
        $x_user_id = $this->getXUserId();

        $headers = join(
            "\r\n",
            [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding: gzip,deflate',
                'Cache-Control: max-age=0',
                'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                'Keep-Alive: 300',
                'Connection: keep-alive',
                $x_user_id . ': ' . $user
            ]
        );

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $headers
            ],
            'https' => [
                'method' => 'GET',
                'header' => $headers
            ],
        ];

        $url = $this->getApiUrl([
            'action' => 'login',
            'external-auth' => 'use'
        ]);

        $ctx = $this->proxy($opts);
        $result = file_get_contents($url, false, $ctx);

        $xml = simplexml_load_string($result);
        if ($xml instanceof SimpleXMLElement && $xml->status['code'] == 'ok') {
            foreach ($http_response_header as $header) {
                if (strpos(strtolower($header), 'set-cookie') === 0) {
                    $matches = [];
                    preg_match('/set-cookie\\s*:\\s*breezesession=([a-z0-9]+);?/i', $header, $matches);

                    if ($matches[1]) {
                        return $matches[1];
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param $user
     * @return null|string
     */
    private function usePasswordAuthentication($user)
    {
        $this->log->write("Adobe Connect " . __METHOD__ . ": Entered frontend user authentication.");

        if (!($pwd = $this->user->getPref('xavc_pwd'))) {
            if ($this->changeUserPassword($user, $pwd = md5(uniqid(microtime(), true)))) {
                $this->user->setPref('xavc_pwd', $pwd);
                $this->user->writePrefs();
            } else {
                $this->log->write(
                    "Adobe Connect " . __METHOD__ . ": No password found in user preferences (Id: " . $this->user->getId(
                    ) . " | " . $this->user->getLogin(
                    ) . "). Could not change password for user '{$user}' on Adobe Connect server."
                );
                return null;
            }
        }

        $session = $this->getBreezeSession(false);
        if ($this->login($user, $pwd, $session)) {
            $this->log->write(
                "Adobe Connect " . __METHOD__ . ": Successfully authenticated session (Id: " . $this->user->getId(
                ) . " | " . $this->user->getLogin() . ")."
            );
            return $session;
        } else {
            $this->log->write(
                "Adobe Connect " . __METHOD__ . ": First login attempt not permitted (Id: " . $this->user->getId(
                ) . " | " . $this->user->getLogin(
                ) . "). Will change random password for user '{$user}' on Adobe Connect server."
            );
            if ($this->changeUserPassword($user, $pwd = md5(uniqid(microtime(), true)))) {
                $this->user->setPref('xavc_pwd', $pwd);
                $this->user->writePrefs();

                if ($this->login($user, $pwd, $session)) {
                    $this->log->write(
                        "Adobe Connect " . __METHOD__ . ": Successfully authenticated session (Id: " . $this->user->getId(
                        ) . " | " . $this->user->getLogin() . ")."
                    );
                    return $session;
                } else {
                    $this->log->write(
                        "Adobe Connect " . __METHOD__ . ": Second login attempt not permitted (Id: " . $this->user->getId(
                        ) . " | " . $this->user->getLogin(
                        ) . "). Password changed for user '{$user}' on Adobe Connect server."
                    );
                }
            } else {
                $this->log->write(
                    "Adobe Connect " . __METHOD__ . ": Login not permitted (Id: " . $this->user->getId(
                    ) . " | " . $this->user->getLogin(
                    ) . "). Could not change password for user '{$user}' on Adobe Connect server."
                );
            }
            return null;
        }
    }

    /**
     *  Gets meeting or content modification date
     * @param string $sco_id
     * @param string $folder_id
     * @param string $session
     * @return string                 Meeting or content modification date, or NULL if something is wrong
     */
    public function getDateEnd(string $sco_id, string $folder_id, string $session): string
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => $folder_id,
            'filter-sco-id' => $sco_id,
            'session' => $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        if ($xml->status['code'] == 'ok') {
            return (string) $xml->scos->sco->{'date-end'};
        } else {
            $this->log->write('AdobeConnect getDateEnd Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getDateEnd Response: ' . $xml->asXML());
            }
        }

        return '';
    }

    /**
     * @param $login
     * @param $session
     * @return null|string
     */
    public function lookupUserFolderId($login, $session)
    {
        $umf_id = $this->getShortcuts('user-meetings', $session);

        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => $umf_id,
            'filter-name' => $login,
            'session' => $session
        ]);

        $xml = simplexml_load_file($url);

        $id = null;
        if (
            $xml instanceof SimpleXMLElement &&
            'ok' == (string) $xml->status['code']
        ) {
            foreach ($xml->scos->sco as $sco) {
                if ($sco['type'] == 'folder') {
                    $id = (string) $sco['sco-id'];
                }
            }
        }

        return $id;
    }

    /**
     * @param $login
     * @param $session
     * @return null|string
     */
    public function createUserFolder($login, $session)
    {
        $umf_id = $this->getShortcuts('user-meetings', $session);

        $url = $this->getApiUrl([
            'action' => 'sco-update',
            'folder-id' => $umf_id,
            'type' => 'folder',
            'name' => $login,
            'session' => $session
        ]);

        $xml = simplexml_load_file($url);
        $id = null;

        if ($xml->status['code'] == "ok") {
            return (string) $xml->sco['sco-id'];
        } else {
            $this->log->write('AdobeConnect createUserFolder Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect createUserFolder Response: ' . $xml->asXML());
            }
        }
        return null;
    }

    /**
     * @param $folder_id
     * @param $session
     * @return array
     */
    public function getScosByFolderId($folder_id, $session): array
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => $folder_id,
            'session' => $session
        ]);

        $xml = simplexml_load_file($url);

        $result = [];
        if ($xml instanceof SimpleXMLElement && 'ok' == (string) $xml->status['code']) {
            foreach ($xml->scos->sco as $meeting) {
                if ($meeting['type'] == 'meeting') {
                    $result[(string) $meeting['sco-id']]['sco_id'] = (string) $meeting['sco-id'];
                    $result[(string) $meeting['sco-id']]['sco_name'] = (string) $meeting->{'name'};
                    $result[(string) $meeting['sco-id']]['description'] = (string) $meeting->{'description'};
                    $result[(string) $meeting['sco-id']]['sco_url'] = (string) $meeting->{'url'};
                    $result[(string) $meeting['sco-id']]['date_end'] = (string) $meeting->{'date-end'};
                }
            }
        }
        return $result;
    }

    /**
     * @param $sco_id
     * @param $folder_id
     * @param $session
     * @return array|null
     */
    public function getScoData($sco_id, $folder_id, $session)
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);

        $data = [];
        if ($xml->status['code'] == "ok") {
            $data['start_date'] = (string) $xml->scos->sco->{'date-begin'};
            $data['end_date'] = (string) $xml->scos->sco->{'date-end'};
        } else {
            $this->log->write('AdobeConnect getStartDate Request: ' . $url);
            if ($xml) {
                $this->log->write('AdobeConnect getStartDate Response: ' . $xml->asXML());
            }

            return null;
        }

        return $data;
    }

    /**
     * lookup content-attribute 'icon'
     * if icon == 'archive' the content is a record
     */
    public function getContentIconAttribute($sco_id, $folder_id, $session): string
    {
        $url = $this->getApiUrl([
            'action' => 'sco-contents',
            'sco-id' => (string) $folder_id,
            'filter-sco-id' => (string) $sco_id,
            'session' => (string) $session
        ]);

        $xml = $this->getCachedSessionCall($url);
        $icon = '';

        if ($xml->status['code'] == "ok") {
            foreach ($xml->scos->sco as $sco) {
                $icon = (string) $sco['icon'];
            }
        }
        return $icon;
    }

    /**
     * @param $pluginObj
     * @return array
     */
    public function getTemplates($pluginObj): array
    {
        $txt_shared_meeting_templates = $pluginObj->txt('shared_meeting_templates');
        $txt_my_meeting_templates = $pluginObj->txt('my_meeting_templates');

        $session = $this->getAdminSession();
        $url_1 = $this->getApiUrl([
            'action' => 'sco-shortcuts',
            'session' => (string) $session
        ]);

        $xml = simplexml_load_file($url_1);
        $templates = [];
        if (is_array($xml->shortcuts->sco)) {
            foreach ($xml->shortcuts->sco as $folder) {
                if (($folder['type'] == 'shared-meeting-templates') || $folder['type'] == 'my-meeting-templates') {
                    $sco_id = (string) $folder['sco-id'];
                    $txt_folder_name = $folder['type'] == 'shared-meeting-templates' ? $txt_shared_meeting_templates : $txt_my_meeting_templates;
                    $url_2 = $this->getApiUrl([
                        'action' => 'sco-contents',
                        'sco-id' => (string) $sco_id,
                        'session' => (string) $session

                    ]);
                    $xml_2 = simplexml_load_file($url_2);

                    if (is_array($xml_2->scos->sco)) {
                        foreach ($xml_2->scos->sco as $sco) {
                            $template_sco_id = (string) $sco['sco-id'];
                            $templates[$template_sco_id] = (string) $sco->{'name'} . ' (' . $txt_folder_name . ')';
                        }
                    }
                }
            }
        }
        asort($templates);
        return $templates;
    }

    /**
     * @param $ctx
     * @return stream context || null
     */
    protected function proxy($ctx = null)
    {
        if (ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = ilProxySettings::_getInstance()->getHost();
            $proxyPort = ilProxySettings::_getInstance()->getPort();
            $proxyURL = 'tcp://' . $proxyPort != '' ? $proxyHost . ':' . $proxyPort : $proxyHost;

            $proxySingleContext = [
                'proxy' => $proxyURL,
                'request_fulluri' => true,
            ];

            $proxyContext = [
                'http' => $proxySingleContext,
                'https' => $proxySingleContext
            ];

            if ($ctx == null) {
                $proxyStreamContext = stream_context_get_default($proxyContext);
                libxml_set_streams_context($proxyStreamContext);
            } elseif (is_array($ctx)) {
                $mergedProxyContext = array_merge_recursive(
                    $proxyContext,
                    $ctx
                );

                return stream_context_create($mergedProxyContext);
            }
        } elseif (is_array($ctx) && count($ctx)) {
            return stream_context_create($ctx);
        }

        return null;
    }
}
