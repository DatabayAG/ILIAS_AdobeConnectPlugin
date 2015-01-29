<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * A meeting content
 *
 * @author Nadia Ahmad <nahmad@databay.de>
 * @author Felix Paulano
 */
class ilAdobeConnectContent
{
    /**
     *  Content attributes
     *
     * @var ilAdobeConnectContentAttributes
     */
    private $attributes;

    /**
     *  Constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes)
    {
		/**
		 * @var $pluginObj ilPlugin
		 *
		 */
		$pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
		$pluginObj->includeClass('class.ilAdobeConnectContentAttributes.php');

        $this->attributes = new ilAdobeConnectContentAttributes($attributes);
    }

    /**
     *  Return $attributes attribute
     *
     * @return  ilAdobeConnectContentAttributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

}