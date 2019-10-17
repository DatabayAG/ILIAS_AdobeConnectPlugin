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
	public function addUser($login, $email, $pass, $first_name, $last_name, $session)
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root();

		$url = $this->getApiUrl(array(
			'action' 		=> 'lms-user-create',
			'login' 		=> $login,
			'first-name' 	=> $first_name,
			'last-name' 	=> $last_name,
			'session' 		=> $session
		));

		$ilLog->write("addUser URL: ". $url);

		$xml = simplexml_load_file($url);

		if($xml->status['code'] == 'ok')
		{
			return true;
		}
		else
		{
			$ilLog->write('AdobeConnect addUser Request:  '.$url);
			if($xml)
			{
				$ilLog->write('AdobeConnect addUser Response: ' . $xml->asXML());
			}
			return false;
		}
	}

	/**
	 * @param string $login
	 * @param string $session
	 * @return bool|string
	 */
	public function searchUser($login, $session)
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root();

		$url = $this->getApiUrl(array(
			'login'     => $login,
			'action' 	=> 'lms-user-exists',
			'session' 	=> $session
		));
		$xml = simplexml_load_file($url);

		if($xml->status['code'] == 'ok')
		{
			$list = $xml->{'principal-list'};

			$id = (string)$list->principal['principal-id'];

			return $id;
		}
		else
		{
			// user doesn't exist at adobe connect server
			$ilLog->write('AdobeConnect searchUser Request:  '.$url);
			if($xml)
			{
				$ilLog->write('AdobeConnect searchUser Response: ' . $xml->asXML());
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
			'session' 	=> $session
		));

		$context = array(
			'http' => array('timeout' => 4),
			'https' => array('timeout' => 4)
		);

		$ctx = $this->proxy($context);
		$xml_string = file_get_contents($url, false, $ctx);
		$xml = simplexml_load_string($xml_string);

		if($xml->status['code'] == 'ok')
		{
			return (string)$xml->cookie;
		}

		$ilLog->write('AdobeConnect lms-user-login Request: '.$url);
		$ilLog->write('AdobeConnect lms-user-login failed:  '.$user);
		ilUtil::sendFailure($lng->txt('login_failed'));
		return false;
	}

	/**
	 * @param string $user
	 * @param string $pass
	 * @param string $session
	 * @return bool
	 */
	public function login($user, $pass, $session)
	{
		global  $DIC;
		$ilLog = $DIC->logger()->root();
		$lng = $DIC->language();

		if(isset(self::$loginsession_cache[$session]))
		{
			return true;
		}

		$url = $this->getApiUrl(array(
			'action' 		=> 'login',
			'login' 		=> $user,
			'password' 		=> $pass,
			'session' 		=> $session
		));

		$context = array(
			'http' => array(
				'timeout' => 4
			),
			'https' => array(
				'timeout' => 4
			)
		);

		$ctx = $this->proxy($context);
		$xml_string = file_get_contents($url, false, $ctx);
		$xml = simplexml_load_string($xml_string);

		if($xml->status['code'] == 'ok')
		{
			self::$loginsession_cache[$session] = true;
			return true;
		}
		else
		{
			unset(self::$loginsession_cache[$session]);
			$ilLog->write('AdobeConnect login Request: '.$url);
			if($xml)
			{
				$ilLog->write('AdobeConnect login Response: ' . $xml->asXML());
			}
			$ilLog->write('AdobeConnect login failed: '.$user);
			ilUtil::sendFailure($lng->txt('login_failed'));
			return false;
		}
	}

	/**
	 * @param String $session
	 * @return bool|void
	 */
	public function logout($session)
	{
	}

	/**
	 * @param String $login
	 * @param String $session
	 * @return null|String
	 */
	public function getPrincipalId($login, $session)
	{
		return $this->searchUser($login, $session);
	}
}
