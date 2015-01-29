<?php

include_once("./Services/Repository/classes/class.ilRepositoryObjectPlugin.php");
include_once dirname(__FILE__) ."/class.ilXMLApiFactory.php";

 
/**
* Adobe Connect plugin for ILIAs
*
* @author Félix Paulano
*
*/
class ilAdobeConnectPlugin extends ilRepositoryObjectPlugin
{
    /**
     *  Return the plugin name
     *
     * @return String
     */
	public function getPluginName()
	{
		return "AdobeConnect";
	}
}
