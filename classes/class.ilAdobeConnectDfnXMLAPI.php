<?php

require_once 'class.ilAdobeConnectXMLAPI.php';

/**
 * Class ilAdobeConnectDfnXMLAPI
 */
class ilAdobeConnectDfnXMLAPI extends ilAdobeConnectXMLAPI
{
	/**
	 * @param array $params
	 * @return string
	 */
	protected function getApiUrl($params)
	{
		$server = $this->server;
		if(substr($server, -1) == '/')
		{
			$server = substr($server, 0, -1);
		}

		if(!$this->port || $this->port == '8080')
		{
			$api_url = $server;
		}
		else
		{
			$api_url = $server .':'.$this->port;
		}

		$api_url .= '/lmsapi/xml?' . http_build_query($params);

		return $api_url;
	}

	/**
	 * @param string $login
	 * @param string $email
	 * @param string $pass
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $session
	 * @return bool
	 */
	public function addUser($login, $email, $pass, $first_name, $last_name, $session = null)
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root();

		$url = $this->getApiUrl(array(
			'action' 	=> 'lms-user-create',
			'login' 		=> $login,
			'first-name' => $first_name,
			'last-name' => $last_name,
			'session' => $session
		));

		$ilLog->info("addUser URL: ". $url);

		$xml = $this->sendRequest($url);
		if($xml instanceof \SimpleXMLElement && $xml->status['code'] == 'ok')
		{
			return true;
		}
		else
		{
			$ilLog->error('AdobeConnect addUser Request failed:  '.$url);
			if($xml)
			{
				$ilLog->error('AdobeConnect addUser Response: ' . $xml->asXML());
			}
			return false;
		}
	}

	/**
	 * @param string $login
	 * @param string $session
	 * @return bool|string
	 */
	public function searchUser($login, $session = NULL)
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root();

		$url = $this->getApiUrl(array(
			'login'     => $login,
			'action' 	=> 'lms-user-exists',
			'session' => $session
		));
		
		$xml = $this->sendRequest($url);
		if($xml instanceof \SimpleXMLElement && $xml->status['code'] == 'ok')
		{
			$list = $xml->{'principal-list'};
			$id = (string)$list->principal['principal-id'];

			return $id;
		}
		else
		{
			// user doesn't exist at adobe connect server
			$ilLog->error('AdobeConnect searchUser Request failed:  '.$url);
			if($xml)
			{
				$ilLog->error('AdobeConnect searchUser Response: ' . $xml->asXML());
			}
			return false;
		}
	}
	
	/**
	 * @param null $user
	 * @param null $pass
	 * @param null $session
	 * @return bool|string
	 */
	public function externalLogin($user = null, $pass = null, $session = null )
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root(); 
		$lng = $DIC->language();

		$url = $this->getApiUrl(array(
			'action' 	=> 'lms-user-login',
			'login' 	=> $user,
			'session' => $session
		));

		$xml = $this->sendRequest($url);
		if($xml instanceof \SimpleXMLElement && $xml->status['code'] == 'ok')
		{
			return (string)$xml->cookie;
		}

		$ilLog->error('AdobeConnect lms-user-login Request: '.$url);
		$ilLog->error('AdobeConnect lms-user-login failed:  '.$user);
		ilUtil::sendFailure($lng->txt('login_failed'));
		return false;
	}

	/**
	 * @param String $login
	 * @param String $session
	 * @return null|String
	 */
	public function getPrincipalId($login, $session = null)
	{
		return $this->searchUser($login, $session);
	}

	/**
	 * @param ilObjAdobeConnect $ac_object
	 */
	public function performSSO(ilObjAdobeConnect $ac_object)
	{
		global $DIC;

		$ilSetting = $DIC->settings();
		$settings = ilAdobeConnectServer::_getInstance();

		$ac_object->pluginObj->includeClass('class.ilAdobeConnectUserUtil.php');
		$ilAdobeConnectUser = new ilAdobeConnectUserUtil( $DIC->user()->getId() );
		$ilAdobeConnectUser->ensureAccountExistance();

		$xavc_login = $ilAdobeConnectUser->getXAVCLogin();

		if ($ac_object->isParticipant( $xavc_login ))
		{
			$presentation_url = ilAdobeConnectServer::getPresentationUrl();

			// do not change this!
			$session =$this->externalLogin($xavc_login);

			$_SESSION['xavc_last_sso_sessid'] = $session;
			if($settings->isHtmlClientEnabled() == 1 && $ac_object->isHtmlClientEnabled() == 1)
			{
				$html_client = '&html-view=true';
			}
			$url = $presentation_url.$ac_object->getURL().'?session='.$session.$html_client;

			$GLOBALS['ilLog']->write(sprintf("Generated URL %s for user '%s'", $url, $xavc_login));

			$presentation_url = ilAdobeConnectServer::getPresentationUrl(true);
			$logout_url = $presentation_url.'/api/xml?action=logout';

			if ($ilSetting->get('short_inst_name') != "")
			{
				$title_prefix = $ilSetting->get('short_inst_name');
			}
			else
			{
				$title_prefix = 'ILIAS';
			}

			$sso_tpl = new ilTemplate($ac_object->pluginObj->getDirectory()."/templates/default/tpl.perform_sso.html", true, true);
			$sso_tpl->setVariable('SPINNER_SRC', $ac_object->pluginObj->getDirectory().'/templates/js/spin.js');
			$sso_tpl->setVariable('TITLE_PREFIX', $title_prefix);
			$sso_tpl->setVariable('LOGOUT_URL', str_replace(['http://', 'https://'], '//', $logout_url));
			$sso_tpl->setVariable('URL', $url);
			$sso_tpl->setVariable('INFO_TXT',$ac_object->pluginObj->txt('redirect_in_progress'));
			$sso_tpl->setVariable('OBJECT_TITLE', $ac_object->getTitle());
			$sso_tpl->show();
			exit;
		}
	} 
}
