<?php

/**
 * Class ilAdobeConnectContent
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilAdobeConnectContent
{
    /**
     * @var ilAdobeConnectContentAttributes
     */
    private $attributes;
    
    /**
     * ilAdobeConnectContent constructor.
     * @param $attributes
     * @throws ilPluginException
     */
    public function __construct($attributes)
    {
        /**
         * @var $pluginObj ilPlugin
         */
        $pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
        $pluginObj->includeClass('class.ilAdobeConnectContentAttributes.php');
        
        $this->attributes = new ilAdobeConnectContentAttributes($attributes);
    }
    
    /**
     * @return ilAdobeConnectContentAttributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}