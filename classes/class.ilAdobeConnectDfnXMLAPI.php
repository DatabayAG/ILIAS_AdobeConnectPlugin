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
}
