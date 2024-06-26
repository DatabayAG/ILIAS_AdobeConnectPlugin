<?php

class ilXMLApiFactory
{
    protected static ?string $classname = null;
    
    /**
     * @return ilAdobeConnectXMLAPI|ilAdobeConnectDfnXMLAPI
     */
    public static function getApiByAuthMode()
    {
        if (self::$classname === null) {
            if (ilAdobeConnectServer::getSetting('auth_mode') == ilAdobeConnectServer::AUTH_MODE_DFN) {
                self::$classname = 'ilAdobeConnectDfnXMLAPI';
            } else {
                self::$classname = 'ilAdobeConnectXMLAPI';
            }
        }
        // @Todo V9 Fix this!
        return new self::$classname();
    }
}