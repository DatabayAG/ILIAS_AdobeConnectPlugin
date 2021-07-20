<?php

/**
 * Meeting contents
 *
 * @author Felix Paulano
 */
class ilAdobeConnectContents
{
    /**
     * array of contents
     *
     * @var  array $contents ilAdobeConnectContent
     */
    private $contents;
    
    /**
     * Default constructor
     *
     */
    public function __construct()
    {
        $this->contents = array();
    }
    
    /**
     *  Add a content to the container
     *
     * @param array $attributes
     */
    public function addContent($attributes)
    {
        /**
         * @var $pluginObj ilPlugin
         *
         */
        $pluginObj = ilPlugin::getPluginObject('Services', 'Repository', 'robj', 'AdobeConnect');
        $pluginObj->includeClass('class.ilAdobeConnectContent.php');
        
        $this->contents[] = new ilAdobeConnectContent($attributes);
    }
    
    /**
     *
     * @param array $search_criteria
     * @return array
     */
    public function search($search_criteria = null)
    {
        $results = array();
        /**
         * @var $content  ilAdobeConnectContent
         */
        foreach ($this->contents as $content) {
            if ($search_criteria != null) {
                if ($content->getAttributes()->match($search_criteria)) {
                    $results[] = $content;
                }
            } else {
                $results[] = $content;
            }
        }
        return $results;
    }
    
    /**
     *
     * @return array
     */
    public function getContents()
    {
        return $this->contents;
    }
}
