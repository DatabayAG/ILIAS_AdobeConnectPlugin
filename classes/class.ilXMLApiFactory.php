<?php
include_once dirname(__FILE__) . "/class.ilAdobeConnectServer.php";
/**
 * Class ilXMLApiFactory
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilXMLApiFactory
{
	/**
	 * @var string
	 */
	protected static $classname;

	/**
	 * @return ilSwitchAaiXMLAPI|ilAdobeConnectXMLAPI|ilAdobeConnectDfnXMLAPI
	 */
	public static function getApiByAuthMode()
	{
		if(self::$classname === NULL)
		{
			if(ilAdobeConnectServer::getSetting('auth_mode') == ilAdobeConnectServer::AUTH_MODE_SWITCHAAI)
			{
				self::$classname = 'ilSwitchAaiXMLAPI';
			}
			else if(ilAdobeConnectServer::getSetting('auth_mode') == ilAdobeConnectServer::AUTH_MODE_DFN)
			{
				self::$classname = 'ilAdobeConnectDfnXMLAPI';
			}
			else
			{
				self::$classname = 'ilAdobeConnectXMLAPI';
			}
		}

		include_once dirname(__FILE__) . '/class.' . self::$classname . '.php';
		$objXMLApi = new self::$classname();

		return $objXMLApi;
	}
}