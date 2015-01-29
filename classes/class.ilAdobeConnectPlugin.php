<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once 'Services/Repository/classes/class.ilRepositoryObjectPlugin.php';
include_once dirname(__FILE__) . '/class.ilXMLApiFactory.php';

/**
 * Adobe Connect plugin for ILIAs
 * @author Félix Paulano
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilAdobeConnectPlugin extends ilRepositoryObjectPlugin
{
	/**
	 * Return the plugin name
	 * @return String
	 */
	public function getPluginName()
	{
		return "AdobeConnect";
	}

	/**
	 * @param $string a_type
	 * @param $string $a_size
	 * @return string
	 */
	public static function _getIcon($a_type, $a_size)
	{
		return ilPlugin::_getImagePath(
			IL_COMP_SERVICE, 'Repository', 'robj',
			ilPlugin::lookupNameForId(IL_COMP_SERVICE, 'Repository', 'robj', $a_type),
			'icon_' . $a_type . '.svg'
		);
	}
}
