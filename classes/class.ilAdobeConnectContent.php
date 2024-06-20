<?php

class ilAdobeConnectContent
{
    private ilAdobeConnectContentAttributes $attributes;
    
    /**
     * ilAdobeConnectContent constructor.
     * @param $attributes
     * @throws ilPluginException
     */
    public function __construct($attributes)
    {
        $this->attributes = new ilAdobeConnectContentAttributes($attributes);
    }
    
    /**
     * @return ilAdobeConnectContentAttributes
     */
    public function getAttributes(): ilAdobeConnectContentAttributes
    {
        return $this->attributes;
    }
}
