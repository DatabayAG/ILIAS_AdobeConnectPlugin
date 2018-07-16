<?php

require_once "Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect/classes/class.ilXMLApiFactory.php";
require_once "Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect/classes/class.ilAdobeConnectServer.php";

/**
 * Class ilAdobeConnectSessionHandler
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilAdobeConnectSessionHandler
{
	/**
	 * @var
	 */
	protected static $instances = array();
	protected static $instance;


	private function __construct()
	{
	}

	public static function _getInstance()
	{

		if(!self::$instance instanceof self)
		{
			self::$instance = new self;

			if(!isset(self::$instances['admin_session']))
			{
				$xmlAPI      = ilXMLApiFactory::getApiByAuthMode();
				$tmp_session = $xmlAPI->getBreezeSession(false);

				$server_instance = ilAdobeConnectServer::_getInstance();
				$login           = $server_instance->getLogin();
				$pass            = $server_instance->getPasswd();

				$xmlAPI->login($login, $pass, $tmp_session);

				self::$instances['admin_session'] = $xmlAPI->getBreezeSession(false);
			}
		}
		return self::$instance ;

	}

	public static function getAdminInstanceSession()
	{
		return self::$instances['admin_session'];
	}

	public static function getUserInstanceSession()
	{
		return self::$instances['user_session'];
	}
}
